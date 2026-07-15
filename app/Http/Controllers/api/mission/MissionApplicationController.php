<?php

namespace App\Http\Controllers\Api\Mission;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mission\StoreMissionApplicationRequest;
use App\Models\Mission;
use App\Models\MissionApplication;
use App\Models\MissionReminder;
use App\Notifications\Mission\NewApplicationReceived;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MissionApplicationController extends Controller
{
    // ── Postuler à une mission ────────────────────────────────────────────

    /**
     * POST /api/applications
     *
     * Champs attendus (multipart/form-data) :
     *   mission_ulid      string  requis
     *   method            string  requis  (form|app_message|whatsapp|email)
     *   form_responses    json    optionnel  {"q1": true, "q2": "valeur"}
     *   motivation        string  optionnel
     *   attachments[]     file[]  optionnel  (max 5 Mo chacun, types autorisés)
     *
     * Toute la validation métier (mission ouverte, pas déjà candidat,
     * champs requis du formulaire, pièces jointes autorisées, etc.)
     * est centralisée dans StoreMissionApplicationRequest.
     *
     * Flux email déclenché :
     *   → NewApplicationReceived (mail + database) envoyé à l'AUTEUR
     *     via App\Mail\NewApplicationMail (template emails.missions.new-application)
     */
    public function store(StoreMissionApplicationRequest $request): JsonResponse
    {
        $mission       = $request->getMission();
        $formResponses = $request->getFormResponses();
        $user          = $request->user();

        // Upload des pièces jointes (hors transaction : I/O fichier)
        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachmentPaths[] = $file->store(
                    "missions/{$mission->ulid}/attachments/{$user->id}",
                    'public'
                );
            }
        }

        try {
            $application = DB::transaction(function () use ($request, $mission, $user, $formResponses, $attachmentPaths) {
                // Créer ou réactiver la candidature (si précédemment withdrawn)
                $application = MissionApplication::updateOrCreate(
                    [
                        'mission_id'   => $mission->id,
                        'applicant_id' => $user->id,
                    ],
                    [
                        'method'           => $request->input('method'),
                        'form_responses'   => $formResponses,
                        'motivation'       => $request->input('motivation'),
                        'attachment_paths' => $attachmentPaths,
                        'status'           => MissionApplication::STATUS_PENDING,
                        'withdrawn_at'     => null,
                        'rejected_at'      => null,
                        'accepted_at'      => null,
                        'rejection_reason' => null,
                        'author_note'      => null,
                    ]
                );

                // Planifier les rappels si start_date défini
                if ($mission->start_date) {
                    $this->scheduleReminders($application);
                }

                return $application;
            });
        } catch (\Throwable $e) {
            // Rollback fichiers uploadés si la transaction échoue
            foreach ($attachmentPaths as $path) {
                Storage::disk('public')->delete($path);
            }
            Log::error("Erreur création candidature mission #{$mission->id} : {$e->getMessage()}");
            abort(500, 'Une erreur est survenue lors de l\'envoi de votre candidature.');
        }

        // ── Notifier l'auteur (email + in-app) ──────────────────────────────
        // → envoie NewApplicationMail à $mission->author->email
        try {
            $mission->author->notify(new NewApplicationReceived($application->load(['applicant', 'mission.category'])));
        } catch (\Throwable $e) {
            Log::warning("Notification candidature mission #{$mission->id} non envoyee : {$e->getMessage()}");
        }

        return response()->json([
            'message' => 'Candidature envoyée avec succès.',
            'data'    => $application->load('mission.category'),
        ], 201);
    }

    // ── Liste des candidatures (côté auteur) ──────────────────────────────

    /**
     * GET /api/missions/{ulid}/applications
     *
     * Paramètres :
     *   status   : pending|accepted|rejected|withdrawn|confirmed
     *   sort     : recent|name
     *   per_page : défaut 20
     */
    public function index(Request $request, string $ulid): JsonResponse
    {
        $mission = Mission::where('ulid', $ulid)
            ->where('author_id', $request->user()->id)
            ->firstOrFail();

        $query = $mission->applications()
            ->with([
                'applicant' => function ($query) {
                    $query->select('id', 'firstname', 'lastname', 'email')
                        ->with(['profile:id,user_id,avatar']);
                },
                'mission' => function ($query) {
                    $query->select(
                        'id',
                        'ulid',
                        'title',
                        'status',
                        'start_date',
                        'location_label',
                        'remuneration_type',
                        'remuneration_amount',
                        'remuneration_currency',
                        'category_id',
                        'author_id'
                    )
                        ->with(['category:id,name,slug,icon,color'])
                        ->with(['author' => function ($q) {
                            $q->select('id', 'firstname', 'lastname', 'email')
                                ->with(['profile:id,user_id,avatar']);
                        }]);
                },
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->integer('per_page', 20);
        $applications = $query->paginate($perPage);

        // Transformer les données pour inclure l'avatar
        $applications->getCollection()->transform(function ($application) {
            if ($application->applicant && $application->applicant->profile) {
                $application->applicant->avatar = $application->applicant->profile->avatar;
                unset($application->applicant->profile);
            }
            if ($application->mission && $application->mission->author) {
                $author = $application->mission->author;
                if ($author->profile) {
                    $author->avatar = $author->profile->avatar;
                }
                unset($author->profile);
            }
            return $application;
        });

        // Stats agrégées
        $stats = $mission->applications()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'data'  => $applications->items(),
            'meta'  => [
                'current_page' => $applications->currentPage(),
                'last_page'    => $applications->lastPage(),
                'total'        => $applications->total(),
            ],
            'stats' => [
                'pending'   => $stats['pending']   ?? 0,
                'accepted'  => $stats['accepted']  ?? 0,
                'rejected'  => $stats['rejected']  ?? 0,
                'withdrawn' => $stats['withdrawn']  ?? 0,
            ],
        ]);
    }

    // ── Accepter ──────────────────────────────────────────────────────────

    /**
     * PATCH /api/missions/{ulid}/applications/{appId}/accept
     *
     * Flux email déclenché :
     *   → ApplicationAccepted (mail + database + fcm) envoyé au CANDIDAT
     *     via App\Mail\ApplicationAcceptedMail (template emails.missions.application-accepted)
     */
    public function accept(Request $request, string $ulid, int $appId): JsonResponse
    {
        $mission = Mission::where('ulid', $ulid)
            ->where('author_id', $request->user()->id)
            ->firstOrFail();

        $application = $mission->applications()
            ->where('id', $appId)
            ->where('status', MissionApplication::STATUS_PENDING)
            ->with(['applicant', 'mission.author'])
            ->firstOrFail();

        // accept() met à jour le statut ET notifie le candidat (email + in-app)
        $application->accept();

        // Vérifier si la mission est pleine → passer à filled
        $mission->refresh();
        if ($mission->isFull()) {
            $mission->update([
                'status'    => Mission::STATUS_FILLED,
                'filled_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Candidature acceptée.',
            'data'    => $application->fresh('applicant'),
        ]);
    }

    // ── Refuser ───────────────────────────────────────────────────────────

    /**
     * PATCH /api/missions/{ulid}/applications/{appId}/reject
     * Body: { reason?: string }
     *
     * Flux email déclenché :
     *   → ApplicationRejected (mail + database) envoyé au CANDIDAT
     *     via App\Mail\ApplicationRejectedMail (template emails.missions.application-rejected)
     */
    public function reject(Request $request, string $ulid, int $appId): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $mission = Mission::where('ulid', $ulid)
            ->where('author_id', $request->user()->id)
            ->firstOrFail();

        $application = $mission->applications()
            ->where('id', $appId)
            ->whereIn('status', [
                MissionApplication::STATUS_PENDING,
                MissionApplication::STATUS_ACCEPTED,
            ])
            ->with(['applicant', 'mission'])
            ->firstOrFail();

        // reject() met à jour le statut ET notifie le candidat (email + in-app)
        $application->reject($request->reason);

        // Si la mission était filled et qu'on retire un accepté → republier
        if ($mission->status === Mission::STATUS_FILLED && $application->isRejected()) {
            $mission->update(['status' => Mission::STATUS_PUBLISHED, 'filled_at' => null]);
        }

        return response()->json([
            'message' => 'Candidature refusée.',
            'data'    => $application->fresh('applicant'),
        ]);
    }

    // ── Note interne auteur ───────────────────────────────────────────────

    /**
     * PATCH /api/missions/{ulid}/applications/{appId}/note
     * Body: { note: string }
     */
    public function addNote(Request $request, string $ulid, int $appId): JsonResponse
    {
        $request->validate(['note' => 'required|string|max:1000']);

        $mission = Mission::where('ulid', $ulid)
            ->where('author_id', $request->user()->id)
            ->firstOrFail();

        $application = $mission->applications()->findOrFail($appId);
        $application->update(['author_note' => $request->note]);

        return response()->json(['message' => 'Note sauvegardée.']);
    }

    // ── Mes candidatures (côté candidat) ─────────────────────────────────

    /**
     * GET /api/applications/my
     *
     * Paramètres :
     *   status   : pending|accepted|rejected|withdrawn
     *   per_page : défaut 15
     */
    public function my(Request $request): JsonResponse
    {
        $request->validate([
            'status'   => 'nullable|in:pending,accepted,rejected,withdrawn,confirmed',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = MissionApplication::where('applicant_id', $request->user()->id)
            ->with([
                'applicant' => function ($query) {
                    $query->select('id', 'firstname', 'lastname', 'email')
                        ->with(['profile:id,user_id,avatar']);
                },
                'mission:id,ulid,title,status,start_date,location_label,remuneration_type,remuneration_amount,remuneration_currency',
                'mission.category:id,name,slug,icon,color',
                'mission.author' => function ($query) {
                    $query->select('id', 'firstname', 'lastname', 'email')
                        ->with(['profile:id,user_id,avatar']);
                },
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->integer('per_page', 15);
        $applications = $query->paginate($perPage);

        $applications->getCollection()->transform(function ($application) {
            if ($application->applicant) {
                $application->applicant->avatar = $application->applicant->profile?->avatar;
                $application->applicant->name = $application->applicant->name
                    ?: trim(implode(' ', array_filter([$application->applicant->firstname, $application->applicant->lastname]))) ?: null;
                unset($application->applicant->profile);
            }

            if ($application->mission && $application->mission->author) {
                $author = $application->mission->author;
                $author->avatar = $author->profile?->avatar;
                $author->name = $author->name
                    ?: trim(implode(' ', array_filter([$author->firstname, $author->lastname]))) ?: null;
                unset($author->profile);
            }

            return $application;
        });

        return response()->json($applications);
    }

    // ── Retirer une candidature ───────────────────────────────────────────

    /**
     * DELETE /api/applications/{id}
     */
    public function withdraw(Request $request, int $id): JsonResponse
    {
        $application = MissionApplication::where('id', $id)
            ->where('applicant_id', $request->user()->id)
            ->whereIn('status', [
                MissionApplication::STATUS_PENDING,
                MissionApplication::STATUS_ACCEPTED,
            ])
            ->firstOrFail();

        // Supprimer les pièces jointes si présentes
        foreach ($application->attachment_paths ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }

        $application->withdraw();

        // Supprimer les rappels liés
        MissionReminder::where('mission_id', $application->mission_id)
            ->where('user_id', $request->user()->id)
            ->where('sent', false)
            ->delete();

        return response()->json(['message' => 'Candidature retirée.']);
    }

    // ── Utilitaires ───────────────────────────────────────────────────────


    /**
     * Planifier les rappels automatiques pour un candidat accepté.
     * Rappels : J-2, J-1, H-2 avant la mission + review J+1.
     */
    private function scheduleReminders(MissionApplication $application): void
    {
        $mission = $application->mission;

        if (!$mission->start_date) return;

        // Créer une DateTime à partir de la date de début
        // Par défaut, on assume 08:00 si pas d'heure précisée
        $startAt = $mission->start_date->startOfDay()->addHours(8);

        $schedule = [
            MissionReminder::TYPE_48H => $startAt->copy()->subHours(48),
            MissionReminder::TYPE_24H => $startAt->copy()->subHours(24),
            MissionReminder::TYPE_2H  => $startAt->copy()->subHours(2),
        ];

        foreach ($schedule as $type => $remindAt) {
            if ($remindAt->isFuture()) {
                MissionReminder::updateOrCreate(
                    [
                        'mission_id' => $mission->id,
                        'user_id'    => $application->applicant_id,
                        'type'       => $type,
                    ],
                    [
                        'remind_at' => $remindAt,
                        'sent'      => false,
                        'sent_at'   => null,
                    ]
                );
            }
        }

        // Rappel évaluation J+1
        $reviewAt = $mission->start_date->addDays(1)->startOfDay()->addHours(10);
        MissionReminder::updateOrCreate(
            [
                'mission_id' => $mission->id,
                'user_id'    => $application->applicant_id,
                'type'       => MissionReminder::TYPE_REVIEW_PROMPT,
            ],
            ['remind_at' => $reviewAt, 'sent' => false]
        );
    }
}
