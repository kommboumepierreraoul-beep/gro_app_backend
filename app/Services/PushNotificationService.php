<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private WebPush $webPush;

    public function __construct()
    {
        if (!class_exists(WebPush::class)) {
            return;
        }

        $this->webPush = new WebPush([
            'VAPID' => [
                'subject'    => config('services.vapid.subject'),
                'publicKey'  => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ]);
    }

    public function sendToUser(User $user, string $title, string $message, string $url = '/'): void
    {
        if (!isset($this->webPush)) {
            Log::warning('Push notifications skipped: minishlink/web-push is not installed.');
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $user->id)->get();

        if ($subscriptions->isEmpty()) return;

        $payload = json_encode([
            'title'   => $title,
            'body'    => $message,
            'url'     => $url,
            'icon'    => '/icon-192.png',
            'badge'   => '/icon-192.png',
        ]);

        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys'     => [
                        'p256dh' => $sub->p256dh,
                        'auth'   => $sub->auth,
                    ],
                ]);
                $this->webPush->queueNotification($subscription, $payload);
            } catch (\Exception $e) {
                Log::error('Push subscription error', ['error' => $e->getMessage()]);
            }
        }

        foreach ($this->webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                Log::warning('Push failed', ['reason' => $report->getReason()]);
                // Supprimer les abonnements expirés
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint', $report->getRequest()->getUri()->__toString())->delete();
                }
            }
        }
    }
}
