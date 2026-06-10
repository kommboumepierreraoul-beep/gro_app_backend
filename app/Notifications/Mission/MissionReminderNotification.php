<?php

namespace App\Notifications\Mission;

// ============================================================
// MissionReminderNotification.php — Rappels avant mission
// ============================================================


use App\Models\MissionReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MissionReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private array $messages = [
        MissionReminder::TYPE_48H           => ['⏰ Mission dans 48h', 'Votre mission approche dans 2 jours.'],
        MissionReminder::TYPE_24H           => ['⏰ Mission demain !', 'Votre mission est prévue demain.'],
        MissionReminder::TYPE_2H            => ['🚨 Mission dans 2h !', 'Votre mission commence dans 2 heures !'],
        MissionReminder::TYPE_REVIEW_PROMPT => ['⭐ Évaluez votre mission', 'Comment s\'est passée votre mission ? Laissez un avis.'],
    ];

    public function __construct(private MissionReminder $reminder) {}

    public function via($notifiable): array
    {
        $channels = ['database'];
        if ($notifiable->fcm_token ?? null) $channels[] = 'fcm';
        return $channels;
    }

    public function toArray($notifiable): array
    {
        [$title, $body] = $this->messages[$this->reminder->type] ?? ['📋 Rappel mission', ''];

        return [
            'type'         => 'mission_reminder',
            'reminder_type' => $this->reminder->type,
            'mission_ulid' => $this->reminder->mission->ulid,
            'mission_title' => $this->reminder->mission->title,
            'start_date'   => $this->reminder->mission->start_date?->toDateString(),
            'title'        => $title,
            'body'         => "{$body} — {$this->reminder->mission->title}",
            'url'          => "/missions/{$this->reminder->mission->ulid}",
        ];
    }

    public function toFcm($notifiable): array
    {
        [$title, $body] = $this->messages[$this->reminder->type] ?? ['📋 Rappel', ''];
        return [
            'title' => $title,
            'body'  => "{$this->reminder->mission->title} — {$body}",
            'data'  => [
                'ulid' => $this->reminder->mission->ulid,
                'type' => 'reminder',
            ],
        ];
    }
}
