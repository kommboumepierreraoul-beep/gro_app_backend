<?php

namespace App\Notifications\Mission;

use App\Models\MissionApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ApplicationRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private MissionApplication $application) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'             => 'application_rejected',
            'mission_ulid'     => $this->application->mission->ulid,
            'mission_title'    => $this->application->mission->title,
            'rejection_reason' => $this->application->rejection_reason,
            'url'              => "/missions",
        ];
    }
}
