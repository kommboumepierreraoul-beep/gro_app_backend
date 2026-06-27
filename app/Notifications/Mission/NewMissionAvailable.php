<?php

namespace App\Notifications\Mission;

use App\Models\Mission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewMissionAvailable extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Mission $mission) {}

    public function via($notifiable): array
    {
        // Push FCM si token disponible, sinon in-app uniquement
        $channels = ['database'];
        if ($notifiable->fcm_token ?? null) {
            $channels[] = 'fcm'; // Nécessite laravel-notification-channels/fcm
        }
        return $channels;
    }

    public function toArray($notifiable): array
    {
        return [
            'type'               => 'new_mission',
            'mission_ulid'       => $this->mission->ulid,
            'mission_title'      => $this->mission->title,
            'category_slug'      => $this->mission->category?->slug,
            'location_label'     => $this->mission->location_label,
            'remuneration_label' => $this->mission->remuneration_label,
            'author_name'        => $this->mission->author->name,
            'url'                => "/missions/{$this->mission->ulid}",
        ];
    }

    // Pour push FCM
    public function toFcm($notifiable): array
    {
        return [
            'title' => "Nouvelle mission : {$this->mission->title}",
            'body'  => $this->mission->location_label
                ? "📍 {$this->mission->location_label} · {$this->mission->remuneration_label}"
                : $this->mission->remuneration_label,
            'data'  => ['ulid' => $this->mission->ulid, 'type' => 'mission'],
        ];
    }
}
