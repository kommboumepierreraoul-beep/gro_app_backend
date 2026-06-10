<?php

namespace App\Http\Controllers\Api\Mission;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\MissionApplication;
use App\Models\MissionReminder;
use App\Notifications\Mission\NewApplicationReceived;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mission_ulid'   => 'required|string',
            'method'         => 'required|in:form,app_message,whatsapp,email',
            'form_responses' => 'nullable|string', // JSON string depuis FormData
            'motivation'     => 'nullable|string|max:2000',
            'attachments'    => 'nullable|array|max:5',
            'attachments.*'  => [
                'file',
                'max:5120', // 5 Mo
                'mimes:jpg,jpeg,png,pdf,doc,docx',
            ],
        ]);

        // Récupérer la mission
        $mission = Mission::where('ulid', $data['mission_ulid'])
            ->with('author')
            ->firstOrFail();

        // Vérifications métier
        abort_if(
            !$mission->isOpen(),
            422,
            'Cette mission n\'accepte plus de candidatures.'
        );

        abort_if(
            $mission->isOwnedBy($request->user()),
            422,
            'Vous ne pouvez pas postuler à votre propre mission.'
        );

        // Vérifier si déjà candidat (hors withdrawn)
        $existing = MissionApplication::where('mission_id', $mission->id)
            ->where('applicant_id', $request->user()->id)
            ->whereNotIn('status', [MissionApplication::STATUS_WITHDRAWN])
            ->first();

        abort_if($existing, 422, 'Vous avez déjà postulé à cette mission.');

        // Valider les réponses au formulaire de l'auteur
        $formResponses = [];
        if ($data['form_responses'] ?? null) {
            $formResponses = json_decode($data['form_responses'], true) ?? [];
            $this->validateFormResponses($mission, $formResponses);
        }

        // Upload des pièces jointes
        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            abort_if(
                !$mission->allow_attachments,
                422,
                'Cette mission n\'accepte pas de pièces jointes.'
            );

            foreach ($request->file('attachments') as $file) {
                $path = $file->store(
                    "missions/{$mission->ulid}/attachments/{$request->user()->id}",
                    'public'
                );
                $attachmentPaths[] = $path;
            }
        }

        // Créer ou réactiver la candidature (si précédemment withdrawn)
        $application = MissionApplication::updateOrCreate(
            [
                'mission_id'   => $mission->id,
                'applicant_id' => $request->user()->id,
            ],
            [
                'method'           => $data['method'],
                'form_responses'   => $formResponses,
                'motivation'       => $data['motivation'] ?? null,
                'attachment_paths' => $attachmentPaths,
                'status'           => MissionApplication::STATUS_PENDING,
                'withdrawn_at'     => null,
                'rejected_at'      => null,
                'accepted_at'      => null,
                'rejection_reason' => null,
                'author_note'      => null,
            ]
        );

        // Notifier l'auteur
        $mission->author->notify(new NewApplicationReceived($application->load('applicant')));

        // Planifier les rappels si start_date défini
        if ($mission->start_date) {
            $this->scheduleReminders($application);
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
            ->with(['applicant' => function ($query) {
                $query->select('id', 'firstname', 'email')
                    ->with('profile');  // Charge le profile avec l'avatar
            }])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->integer('per_page', 20);

        $applications = $query->paginate($perPage);

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
     */
    public function accept(Request $request, string $ulid, int $appId): JsonResponse
    {
        $mission = Mission::where('ulid', $ulid)
            ->where('author_id', $request->user()->id)
            ->firstOrFail();

        $application = $mission->applications()
            ->where('id', $appId)
            ->where('status', MissionApplication::STATUS_PENDING)
            ->firstOrFail();

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
            ->firstOrFail();

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
                'mission:id,ulid,title,status,start_date,location_label,remuneration_type,remuneration_amount,remuneration_currency',
                'mission.category:id,name,slug,icon,color',
                'mission.author:id,firstname,avatar',  // avatar vient de l'accesseur
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->integer('per_page', 15);

        return response()->json($query->paginate($perPage));
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
     * Valider les réponses du candidat par rapport au formulaire défini par l'auteur.
     */
    private function validateFormResponses(Mission $mission, array $responses): void
    {
        foreach ($mission->application_form ?? [] as $field) {
            if (($field['required'] ?? false) && empty($responses[$field['id']])) {
                abort(422, "Le champ \"{$field['label']}\" est requis.");
            }
        }
    }

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
