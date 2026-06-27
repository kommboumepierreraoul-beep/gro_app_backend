<?php

namespace App\Listeners;

use App\Events\ContentModerated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendModerationWebhook implements ShouldQueue
{
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Handle the event.
     */
    public function handle(ContentModerated $event): void
    {
        $webhookUrl = config('moderation.webhook_url');

        if (!$webhookUrl) {
            return;
        }

        try {
            $response = Http::timeout(10)
                ->retry(3, 1000)
                ->post($webhookUrl, [
                    'event' => 'content.moderated',
                    'data' => [
                        'content_type' => $event->contentType,
                        'content_id' => $event->contentId,
                        'status' => $event->status,
                        'reason' => $event->reason,
                        'user_id' => $event->userId,
                        'scores' => $event->scores,
                        'timestamp' => $event->timestamp,
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('Webhook de modération échoué', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'envoi du webhook', [
                'error' => $e->getMessage(),
                'webhook_url' => $webhookUrl,
            ]);
        }
    }
}
