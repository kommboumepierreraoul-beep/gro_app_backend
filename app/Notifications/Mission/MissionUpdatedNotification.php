<?php

namespace App\Notifications\Mission;

use App\Models\Mission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MissionUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Mission $mission) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'          => 'mission_updated',
            'mission_ulid'  => $this->mission->ulid,
            'mission_title' => $this->mission->title,
            'message'       => 'La mission a été mise à jour par l\'auteur.',
            'url'           => "/missions/{$this->mission->ulid}",
        ];
    }
}
