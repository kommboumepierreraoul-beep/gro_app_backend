<?php

namespace App\Services\Mission;

use App\Models\Mission;
use App\Models\User;
use App\Notifications\Mission\NewMissionAvailable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class MissionDiffusionService
{
    /**
     * Diffusion prioritaire :
     *   Cercle 1 — abonnés de l'auteur
     *   Cercle 1 — utilisateurs dans le rayon géographique
     *
     * Le job ExpandMissionDiffusionJob prend le relais si non pourvue après 48h.
     */
    public function diffuse(Mission $mission): void
    {
        $author = $mission->author;

        Log::info("Diffusion mission #{$mission->id} : {$mission->title}");

        // ── Cercle 1a : abonnés de l'auteur ──────────────────────────────
        // On suppose une relation followers/following sur User.
        // Adapter selon votre système social (ex: table `followers`).
        $followerIds = collect();
        if (method_exists($author, 'followers')) {
            $followerIds = $author->followers()->pluck('users.id');
        }

        // ── Cercle 1b : utilisateurs à proximité ─────────────────────────
        $nearbyIds = collect();

        if ($mission->location_point && $mission->diffusion_scope === Mission::SCOPE_RADIUS) {
            // Requête PostGIS : utilisateurs dont la dernière position connue
            // est dans le rayon de la mission.
            // On suppose une colonne `location_point` sur la table users
            // (ou une table user_locations séparée).
            $nearbyIds = User::whereRaw(
                "location_point IS NOT NULL
                 AND ST_DWithin(
                       location_point::geography,
                       (SELECT location_point FROM missions WHERE id = ?),
                       ?
                 )",
                [$mission->id, $mission->diffusion_radius_km * 1000]
            )
                ->whereNotIn('id', $followerIds->toArray())
                ->where('id', '!=', $author->id)
                ->pluck('id');
        }

        // ── Union des deux cercles ────────────────────────────────────────
        $allTargetIds = $followerIds->merge($nearbyIds)->unique()->filter();

        if ($allTargetIds->isEmpty()) {
            Log::info("Aucun destinataire trouvé pour la mission #{$mission->id}");
            return;
        }

        Log::info("Mission #{$mission->id} : {$allTargetIds->count()} destinataires");

        // Notifier par chunks pour éviter la surcharge mémoire
        User::whereIn('id', $allTargetIds)
            ->where('id', '!=', $author->id)
            ->select(['id', 'name', 'email'])
            ->chunk(100, function ($users) use ($mission) {
                Notification::send($users, new NewMissionAvailable($mission));
            });
    }

    /**
     * Diffusion élargie : toute la plateforme.
     * Déclenché si la mission n'est pas pourvue après 48h.
     */
    public function expand(Mission $mission): void
    {
        // Ne diffuser que si toujours publiée
        if ($mission->status !== Mission::STATUS_PUBLISHED) {
            Log::info("Expansion annulée : mission #{$mission->id} statut={$mission->status}");
            return;
        }

        Log::info("Expansion diffusion mission #{$mission->id} → toute la plateforme");

        User::where('id', '!=', $mission->author_id)
            ->select(['id', 'name', 'email'])
            ->chunk(200, function ($users) use ($mission) {
                Notification::send($users, new NewMissionAvailable($mission));
            });
    }
}
