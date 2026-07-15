<?php

namespace App\Notifications\Mission;

use App\Models\MissionApplication;
use App\Services\BrevoMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ApplicationRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private MissionApplication $application) {}

    public function via($notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->fcm_token ?? null) {
            $channels[] = 'fcm';
        }

        return $channels;
    }

    public function sendWithBrevo($notifiable): void
    {
        $mission = $this->application->mission;
        $name = $notifiable->name ?? $notifiable->firstname ?? 'Utilisateur';
        $reason = $this->application->rejection_reason;
        $missionsUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/')
            . '/missions';

        $html = view('emails.mission-application-rejected', compact(
            'mission',
            'name',
            'reason',
            'missionsUrl'
        ))->render();

        app(BrevoMailService::class)->send(
            $notifiable->email,
            $name,
            "Candidature non retenue - {$mission->title}",
            $html
        );
    }

    public function toArray($notifiable): array
    {
        $mission = $this->application->mission;

        return [
            'type' => 'application_rejected',
            'mission_ulid' => $mission->ulid,
            'mission_title' => $mission->title,
            'rejection_reason' => $this->application->rejection_reason,
            'url' => rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/') . '/missions',
        ];
    }

    public function toFcm($notifiable): array
    {
        return [
            'title' => 'Candidature non retenue',
            'body' => "Votre candidature pour {$this->application->mission->title} n'a pas ete retenue.",
            'data' => [
                'ulid' => $this->application->mission->ulid,
                'type' => 'application_rejected',
            ],
        ];
    }
}
