<?php

namespace App\Listeners;

use App\Events\ContentModerated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdateModerationStats implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(ContentModerated $event): void
    {
        try {
            // Mettre à jour les statistiques en cache
            $cacheKey = 'moderation_stats_' . date('Y-m-d');
            $stats = Cache::get($cacheKey, [
                'total' => 0,
                'approved' => 0,
                'rejected' => 0,
                'review' => 0,
                'pending' => 0,
            ]);

            $stats['total']++;
            $stats[$event->status] = ($stats[$event->status] ?? 0) + 1;

            Cache::put($cacheKey, $stats, now()->addDay());

            // Statistiques globales
            $globalKey = 'moderation_stats_global';
            $globalStats = Cache::get($globalKey, [
                'total' => 0,
                'by_type' => [
                    'post' => ['total' => 0, 'approved' => 0, 'rejected' => 0, 'review' => 0, 'pending' => 0],
                    'comment' => ['total' => 0, 'approved' => 0, 'rejected' => 0, 'review' => 0, 'pending' => 0],
                    'message' => ['total' => 0, 'approved' => 0, 'rejected' => 0, 'review' => 0, 'pending' => 0],
                ],
            ]);

            $globalStats['total']++;

            if (isset($globalStats['by_type'][$event->contentType])) {
                $globalStats['by_type'][$event->contentType]['total']++;
                $globalStats['by_type'][$event->contentType][$event->status] =
                    ($globalStats['by_type'][$event->contentType][$event->status] ?? 0) + 1;
            }

            Cache::put($globalKey, $globalStats, now()->addDay());
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour des stats', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
