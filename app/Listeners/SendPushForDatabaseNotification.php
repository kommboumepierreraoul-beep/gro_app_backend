<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

class SendPushForDatabaseNotification
{
    public function __construct(private PushNotificationService $pushNotificationService) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || !($event->notifiable instanceof User)) {
            return;
        }

        try {
            $data = $this->notificationData($event);

            if (empty($data)) {
                return;
            }

            $title = (string) ($data['title'] ?? $this->titleFromType($data['type'] ?? null));
            $body = (string) ($data['message'] ?? $data['body'] ?? 'Nouvelle notification');
            $url = (string) ($data['url'] ?? '/notifications');

            $this->pushNotificationService->sendToUser(
                $event->notifiable,
                $title,
                $body,
                $url
            );
        } catch (\Throwable $error) {
            Log::warning('Database notification push failed', [
                'notification' => get_class($event->notification),
                'notifiable_id' => $event->notifiable->id ?? null,
                'error' => $error->getMessage(),
            ]);
        }
    }

    private function notificationData(NotificationSent $event): array
    {
        if (is_array($event->response)) {
            return $event->response;
        }

        if (method_exists($event->notification, 'toDatabase')) {
            return (array) $event->notification->toDatabase($event->notifiable);
        }

        if (method_exists($event->notification, 'toArray')) {
            return (array) $event->notification->toArray($event->notifiable);
        }

        return [];
    }

    private function titleFromType(?string $type): string
    {
        return match ($type) {
            'new_application' => 'Nouvelle candidature',
            'application_accepted' => 'Candidature acceptee',
            'application_rejected' => 'Candidature non retenue',
            'mission_reminder' => 'Rappel mission',
            'mission_updated' => 'Mission mise a jour',
            'new_mission' => 'Nouvelle mission',
            'order_confirmed' => 'Commande confirmee',
            'order_shipped' => 'Commande expediee',
            'order_completed' => 'Commande terminee',
            'product_approved' => 'Produit approuve',
            'product_rejected' => 'Produit rejete',
            'shop_approved' => 'Boutique approuvee',
            'shop_rejected' => 'Boutique a corriger',
            'dispute_created' => 'Nouveau litige',
            'dispute_message' => 'Nouveau message litige',
            'dispute_resolved' => 'Litige resolu',
            default => 'AgriPulse',
        };
    }
}
