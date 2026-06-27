<?php

namespace App\Listeners;

use App\Events\ContentModerated;
use App\Models\CommunityNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyUserModeration implements ShouldQueue
{
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public array $backoff = [5, 10, 30];

    /**
     * Handle the event.
     */
    public function handle(ContentModerated $event): void
    {
        if (!$event->userId) {
            return;
        }

        try {
            $messages = [
                'approved' => 'Votre contenu a été approuvé et est maintenant visible.',
                'rejected' => 'Votre contenu a été rejeté. Motif: ' . ($event->reason ?? 'Non conforme aux règles de la communauté.'),
                'review' => 'Votre contenu est en cours de vérification par un modérateur.',
                'pending' => 'Votre contenu est en attente d\'analyse par l\'IA.',
            ];

            $message = $messages[$event->status] ?? 'Le statut de votre contenu a changé.';

            // Créer une notification dans la base de données
            $notification = CommunityNotification::create([
                'user_id' => $event->userId,
                'type' => 'moderation_' . $event->status,
                'title' => $this->getNotificationTitle($event->status),
                'content' => $message,
                'data' => [
                    'content_type' => $event->contentType,
                    'content_id' => $event->contentId,
                    'status' => $event->status,
                    'reason' => $event->reason,
                    'scores' => $event->scores,
                    'timestamp' => $event->timestamp,
                ],
            ]);

            Log::info('Notification de modération créée', [
                'user_id' => $event->userId,
                'notification_id' => $notification->id,
                'status' => $event->status,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la notification de modération', [
                'error' => $e->getMessage(),
                'user_id' => $event->userId,
                'status' => $event->status,
            ]);
        }
    }

    /**
     * Get the notification title based on status.
     */
    private function getNotificationTitle(string $status): string
    {
        return match ($status) {
            'approved' => '✅ Contenu approuvé',
            'rejected' => '❌ Contenu rejeté',
            'review' => '🔍 Contenu en vérification',
            'pending' => '⏳ Contenu en analyse',
            default => '📢 Mise à jour de modération',
        };
    }

    /**
     * Handle a job failure.
     */
    public function failed(ContentModerated $event, \Throwable $exception): void
    {
        Log::error('Échec de la notification de modération', [
            'user_id' => $event->userId,
            'status' => $event->status,
            'error' => $exception->getMessage(),
        ]);
    }
}
