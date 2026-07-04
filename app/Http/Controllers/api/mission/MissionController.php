<?php

namespace App\Http\Controllers\api\Mission;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mission\StoreMissionRequest;
use App\Http\Requests\Mission\UpdateMissionRequest;
use App\Http\Resources\Mission\MissionResource;
use App\Http\Resources\Mission\MissionDetailResource;
use App\Http\Resources\Mission\MissionCategoryResource;
use App\Models\Mission;
use App\Models\MissionCategory;
use App\Models\MissionView;
use App\Jobs\Mission\DiffuseMissionJob;
use App\Jobs\Mission\RecordMissionViewJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MissionController extends Controller
{
    // ── Liste des missions ────────────────────────────────────────────────

    /**
     * GET /api/missions
     *
     * Paramètres de requête :
     *   lat, lng          : position utilisateur (décimal)
     *   radius_km         : rayon de recherche (défaut 25, max 200)
     *   category          : id de catégorie
     *   remuneration_type : fixed|daily_rate|hourly_rate|negotiable|in_kind|volunteer
     *   status            : published (défaut)
     *   sort              : recent|distance|popular
     *   search            : recherche textuelle sur titre/description
     *   author_id         : filtrer par auteur
     *   page              : pagination (défaut 1)
     *   per_page          : items par page (défaut 15, max 50)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'lat'              => 'nullable|numeric|between:-90,90',
            'lng'              => 'nullable|numeric|between:-180,180',
            'radius_km'        => 'nullable|integer|min:1|max:200',
            'category'         => 'nullable|integer|exists:mission_categories,id',
            'remuneration_type' => 'nullable|in:fixed,daily_rate,hourly_rate,negotiable,in_kind,volunteer',
            'sort'             => 'nullable|in:recent,distance,popular',
            'search'           => 'nullable|string|max:100',
            'author_id'        => 'nullable|integer',
            'per_page'         => 'nullable|integer|min:1|max:50',
        ]);

        $query = Mission::active()
            ->with(['author:id,firstname', 'category:id,name,slug,icon,color'])
            ->withCount('applications');

        // Géolocalisation
        $hasGeo = $request->filled('lat') && $request->filled('lng');
        if ($hasGeo) {
            $lat    = (float) $request->lat;
            $lng    = (float) $request->lng;
            $radius = $request->integer('radius_km', 25);

            $query->nearby($lat, $lng, $radius)
                ->withDistance($lat, $lng);
        }

        // Filtres
        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }

        if ($request->filled('remuneration_type')) {
            $query->where('remuneration_type', $request->remuneration_type);
        }

        if ($request->filled('author_id')) {
            $query->where('author_id', $request->integer('author_id'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Tri
        match ($request->get('sort', 'recent')) {
            'distance' => $hasGeo
                ? $query->orderBy('distance_km')
                : $query->orderByDesc('created_at'),
            'popular'  => $query->orderByDesc('applications_count')
                ->orderByDesc('views_count'),
            default    => $query->orderByDesc('created_at'),
        };

        $perPage = $request->integer('per_page', 15);

        return MissionResource::collection($query->paginate($perPage));
    }

    // ── Créer une mission ─────────────────────────────────────────────────

    /**
     * POST /api/missions
     */
    public function store(StoreMissionRequest $request): MissionDetailResource
    {
        $data = $request->validated();

        // Extraire les coordonnées avant la création (non dans fillable directement)
        $latitude  = $data['latitude']  ?? null;
        $longitude = $data['longitude'] ?? null;
        unset($data['latitude'], $data['longitude']);

        // Créer la mission
        $mission = $request->user()->missions()->create($data);

        // Définir le point géographique si fourni
        if ($latitude && $longitude) {
            $mission->setLocationPoint($latitude, $longitude);
        }

        // Lancer la diffusion si publiée directement
        if ($mission->status === Mission::STATUS_PUBLISHED) {
            DiffuseMissionJob::dispatch($mission)->onQueue('notifications');
        }

        return new MissionDetailResource($mission->load(['author', 'category']));
    }

    // ── Détail d'une mission ──────────────────────────────────────────────

    /**
     * GET /api/missions/{ulid}
     */
    public function show(Request $request, string $ulid): MissionDetailResource
    {
        $mission = Mission::where('ulid', $ulid)
            ->with([
                'author' => function ($query) {
                    $query->select('id', 'firstname')
                        ->with('profile:id,user_id,avatar');
                },
                'category:id,name,slug,icon,color',
                'reviews.reviewer' => function ($query) {
                    $query->select('id', 'firstname')
                        ->with('profile:id,user_id,avatar');
                },
            ])
            ->withCount(['applications', 'applications as accepted_count' => function ($q) {
                $q->where('status', 'accepted');
            }])
            ->firstOrFail();

        // Enregistrer la vue en asynchrone
        RecordMissionViewJob::dispatch(
            $mission->id,
            $request->user()?->id,
            md5($request->ip())
        )->onQueue('low');

        // Ajouter les coordonnées PostGIS
        $latLng = $mission->getLatLng();
        if ($latLng) {
            $mission->lat = $latLng['lat'];
            $mission->lng = $latLng['lng'];
        }

        // Vérifier si l'utilisateur a déjà postulé
        if ($request->user()) {
            $mission->user_application = $mission->applications()
                ->where('applicant_id', $request->user()->id)
                ->first();
        }

        return new MissionDetailResource($mission);
    }

    // ── Modifier une mission ──────────────────────────────────────────────

    /**
     * PUT /api/missions/{ulid}
     */
    public function update(UpdateMissionRequest $request, string $ulid): MissionDetailResource
    {
        $mission = Mission::where('ulid', $ulid)
            ->where('author_id', $request->user()->id)
            ->firstOrFail();

        abort_if(!$mission->isEditable(), 422, 'Cette mission ne peut plus être modifiée.');

        $data = $request->validated();

        $latitude  = $data['latitude']  ?? null;
        $longitude = $data['longitude'] ?? null;
        unset($data['latitude'], $data['longitude']);

        $mission->update($data);

        if ($latitude && $longitude) {
            $mission->setLocationPoint($latitude, $longitude);
        }

        // Notifier les candidats existants si la mission est modifiée
        if ($mission->applications()->exists()) {
            \App\Jobs\Mission\NotifyApplicantsOfMissionUpdateJob::dispatch($mission)
                ->onQueue('notifications');
        }

        return new MissionDetailResource($mission->fresh(['author', 'category']));
    }

    // ── Changer le statut ─────────────────────────────────────────────────

    /**
     * PATCH /api/missions/{ulid}/status
     * Body: { status: "published"|"suspended"|"cancelled"|"completed"|"archived" }
     */
    public function updateStatus(Request $request, string $ulid): MissionDetailResource
    {
        $request->validate([
            'status' => 'required|in:published,suspended,cancelled,completed,archived',
        ]);

        $mission = Mission::where('ulid', $ulid)
            ->where('author_id', $request->user()->id)
            ->firstOrFail();

        $newStatus = $request->status;

        // Transitions autorisées
        $allowed = [
            Mission::STATUS_DRAFT       => ['published'],
            Mission::STATUS_PUBLISHED   => ['suspended', 'cancelled', 'completed'],
            Mission::STATUS_SUSPENDED   => ['published', 'cancelled'],
            Mission::STATUS_FILLED      => ['in_progress', 'cancelled'],
            Mission::STATUS_IN_PROGRESS => ['completed', 'cancelled'],
            Mission::STATUS_COMPLETED   => ['archived'],
        ];

        $current = $mission->status;
        abort_unless(
            in_array($newStatus, $allowed[$current] ?? []),
            422,
            "Transition '{$current}' → '{$newStatus}' non autorisée."
        );

        // Timestamps spéciaux
        $extra = match ($newStatus) {
            'filled'      => ['filled_at'    => now()],
            'completed'   => ['completed_at' => now()],
            default       => [],
        };

        $mission->update(array_merge(['status' => $newStatus], $extra));

        // Diffuser si re-publiée depuis suspension
        if ($newStatus === Mission::STATUS_PUBLISHED) {
            DiffuseMissionJob::dispatch($mission)->onQueue('notifications');
        }

        // Planifier les évaluations si terminée
        if ($newStatus === Mission::STATUS_COMPLETED) {
            \App\Jobs\Mission\ScheduleReviewPromptsJob::dispatch($mission)
                ->onQueue('notifications');
        }

        return new MissionDetailResource($mission->fresh(['author', 'category']));
    }

    // ── Supprimer ─────────────────────────────────────────────────────────

    /**
     * DELETE /api/missions/{ulid}
     */
    public function destroy(Request $request, string $ulid): JsonResponse
    {
        $mission = Mission::where('ulid', $ulid)
            ->where('author_id', $request->user()->id)
            ->firstOrFail();

        abort_if(
            in_array($mission->status, [Mission::STATUS_IN_PROGRESS]),
            422,
            'Impossible de supprimer une mission en cours.'
        );

        // Supprimer les fichiers joints liés
        $this->cleanupMissionFiles($mission);

        $mission->delete(); // SoftDelete

        return response()->json(['message' => 'Mission supprimée.']);
    }

    // ── Points carte ──────────────────────────────────────────────────────

    /**
     * GET /api/missions/map
     * Retourne uniquement id/ulid/titre/catégorie/coordonnées pour la carte.
     * Paramètres : lat, lng, radius_km (défaut 50)
     */
    public function mapPoints(Request $request): JsonResponse
    {
        $request->validate([
            'lat'       => 'required|numeric|between:-90,90',
            'lng'       => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|integer|min:1|max:500',
        ]);

        $lat    = (float) $request->lat;
        $lng    = (float) $request->lng;
        $radius = $request->integer('radius_km', 50);

        $points = DB::select("
            SELECT
                m.id, m.ulid, m.title,
                m.applications_count,
                ST_Y(m.location_point::geometry) AS lat,
                ST_X(m.location_point::geometry) AS lng,
                mc.slug  AS category_slug,
                mc.icon  AS category_icon,
                mc.color AS category_color,
                ROUND((ST_Distance(
                    m.location_point::geography,
                    ST_MakePoint(?, ?)::geography
                ) / 1000)::numeric, 1) AS distance_km
            FROM missions m
            LEFT JOIN mission_categories mc ON m.category_id = mc.id
            WHERE m.status = 'published'
              AND m.deleted_at IS NULL
              AND (m.expires_at IS NULL)
              AND m.location_point IS NOT NULL
              AND ST_DWithin(
                    m.location_point::geography,
                    ST_MakePoint(?, ?)::geography,
                    ?
                  )
            ORDER BY distance_km
        ", [$lng, $lat, $lng, $lat, $radius * 1000]);

        return response()->json(['data' => $points]);
    }

    // ── Catégories ────────────────────────────────────────────────────────

    /**
     * GET /api/missions/categories
     */
    // public function categories(): JsonResponse
    // {
    //     $categories = MissionCategory::active()->get();

    //     return response()->json(['data' => MissionCategoryResource::collection($categories)]);
    // }

    // Remplacez par :
    public function categories(): JsonResponse
    {
        $categories = MissionCategory::active()->get();

        // Solution temporaire sans le Resource
        return response()->json(['data' => $categories]);
    }

    // ── Vue ───────────────────────────────────────────────────────────────

    /**
     * POST /api/missions/{ulid}/view
     */
    public function recordView(Request $request, string $ulid): JsonResponse
    {
        $mission = Mission::where('ulid', $ulid)->firstOrFail();

        RecordMissionViewJob::dispatch(
            $mission->id,
            $request->user()?->id,
            md5($request->ip())
        )->onQueue('low');

        return response()->json(['message' => 'ok']);
    }

    // ── Mes missions (auteur) ─────────────────────────────────────────────

    /**
     * GET /api/missions/my
     */
    public function my(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status'   => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = Mission::forAuthor($request->user()->id)
            ->with([
                'category:id,name,slug,icon,color',
                'author:id,firstname,lastname',
                'author.profile:id,user_id,avatar'
            ])
            ->withCount(['applications', 'applications as pending_count' => function ($q) {
                $q->where('status', 'pending');
            }])
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }


        return MissionResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    // ── Utilitaire fichiers ───────────────────────────────────────────────

    private function cleanupMissionFiles(Mission $mission): void
    {
        $applications = $mission->applications()->get();

        foreach ($applications as $application) {
            foreach ($application->attachment_paths ?? [] as $path) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
