<?php

namespace App\Notifications\Mission;

use App\Models\MissionApplication;
use App\Services\BrevoMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ApplicationAccepted extends Notification implements ShouldQueue
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
        $startDate = $mission->start_date ? $mission->start_date->format('d/m/Y') : 'Non precisee';
        $location = $mission->location_label ?? 'Non precise';
        $missionUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/')
            . "/missions/{$mission->ulid}";
        $contacts = collect($mission->contact_methods ?? [])
            ->filter(fn ($contact) => in_array($contact['type'] ?? null, ['whatsapp', 'email'], true) && !empty($contact['value']))
            ->values()
            ->all();

        $html = view('emails.mission-application-accepted', compact(
            'mission',
            'name',
            'startDate',
            'location',
            'contacts',
            'missionUrl'
        ))->render();

        app(BrevoMailService::class)->send(
            $notifiable->email,
            $name,
            "Candidature acceptee - {$mission->title}",
            $html
        );
    }

    public function toArray($notifiable): array
    {
        $mission = $this->application->mission;

        return [
            'type' => 'application_accepted',
            'mission_ulid' => $mission->ulid,
            'mission_title' => $mission->title,
            'url' => rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/')
                . "/missions/{$mission->ulid}",
        ];
    }

    public function toFcm($notifiable): array
    {
        return [
            'title' => 'Candidature acceptee',
            'body' => "Vous avez ete selectionne(e) pour : {$this->application->mission->title}",
            'data' => [
                'ulid' => $this->application->mission->ulid,
                'type' => 'application_accepted',
            ],
        ];
    }
}
