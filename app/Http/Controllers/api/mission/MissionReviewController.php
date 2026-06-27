<?php

namespace App\Http\Controllers\Api\mission;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\MissionApplication;
use App\Models\MissionReview;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MissionReviewController extends Controller
{
    /**
     * POST /api/missions/{ulid}/reviews
     * Body: { rating: int, comment?: string, direction: string, reviewee_id?: int }
     *
     * Règles :
     *  - Auteur peut évaluer un candidat accepté (author_to_applicant)
     *  - Candidat accepté peut évaluer la mission/auteur (applicant_to_author)
     *  - La mission doit être completed
     */
    public function store(Request $request, string $ulid): JsonResponse
    {
        $data = $request->validate([
            'rating'      => 'required|integer|min:1|max:5',
            'comment'     => 'nullable|string|max:1000',
            'direction'   => 'required|in:author_to_applicant,applicant_to_author',
            'reviewee_id' => 'required_if:direction,author_to_applicant|integer|exists:users,id',
        ]);

        $mission = Mission::where('ulid', $ulid)->firstOrFail();

        abort_unless(
            $mission->status === Mission::STATUS_COMPLETED,
            422,
            'Les évaluations ne sont disponibles que pour les missions terminées.'
        );

        $user = $request->user();

        // Vérifier les droits selon la direction
        if ($data['direction'] === MissionReview::DIR_AUTHOR_TO_APPLICANT) {
            abort_unless($mission->isOwnedBy($user), 403, 'Seul l\'auteur peut évaluer un candidat.');

            $revieweeId = $data['reviewee_id'];

            // Vérifier que le reviewee a bien été accepté sur cette mission
            abort_unless(
                MissionApplication::where('mission_id', $mission->id)
                    ->where('applicant_id', $revieweeId)
                    ->where('status', MissionApplication::STATUS_ACCEPTED)
                    ->exists(),
                422,
                'Cet utilisateur n\'a pas été accepté sur cette mission.'
            );
        } else {
            // applicant_to_author : le candidat évalue l'auteur
            abort_unless(
                MissionApplication::where('mission_id', $mission->id)
                    ->where('applicant_id', $user->id)
                    ->where('status', MissionApplication::STATUS_ACCEPTED)
                    ->exists(),
                403,
                'Vous n\'avez pas été accepté sur cette mission.'
            );

            $revieweeId = $mission->author_id;
        }

        // Empêcher les doublons
        $existing = MissionReview::where('mission_id', $mission->id)
            ->where('reviewer_id', $user->id)
            ->where('direction', $data['direction'])
            ->first();

        abort_if($existing, 422, 'Vous avez déjà soumis une évaluation pour cette mission.');

        $review = MissionReview::create([
            'mission_id'  => $mission->id,
            'reviewer_id' => $user->id,
            'reviewee_id' => $revieweeId,
            'direction'   => $data['direction'],
            'rating'      => $data['rating'],
            'comment'     => $data['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Évaluation soumise.',
            'data'    => $review->load('reviewer:id,firstname,avatar'),
        ], 201);
    }

    /**
     * GET /api/missions/{ulid}/reviews
     */
    public function index(string $ulid): JsonResponse
    {
        $mission = Mission::where('ulid', $ulid)->firstOrFail();

        $reviews = $mission->reviews()
            ->with('reviewer:id,firstname,avatar')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $reviews]);
    }
}
