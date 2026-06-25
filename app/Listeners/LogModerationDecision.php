<?php

namespace App\Listeners;

use App\Events\ContentModerated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogModerationDecision implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(ContentModerated $event): void
    {
        try {
            // Log dans le fichier de logs
            Log::info('Décision de modération', [
                'content_type' => $event->contentType,
                'content_id' => $event->contentId,
                'status' => $event->status,
                'reason' => $event->reason,
                'user_id' => $event->userId,
                'scores' => $event->scores,
                'timestamp' => $event->timestamp,
            ]);

            // Le audit log est déjà créé par les modèles
            // Cette listener est juste pour le logging supplémentaire

        } catch (\Exception $e) {
            Log::error('Erreur lors du logging de modération', [
                'error' => $e->getMessage(),
                'content_id' => $event->contentId,
            ]);
        }
    }
}
