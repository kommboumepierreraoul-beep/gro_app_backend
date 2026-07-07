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

        $startDate = $mission->start_date
            ? $mission->start_date->format('d/m/Y')
            : 'Non précisée';

        $location = $mission->location_label ?? 'Non précisé';

        $contactsHtml = '';

        foreach ($mission->contact_methods ?? [] as $contact) {
            if (($contact['type'] ?? null) === 'whatsapp') {
                $contactsHtml .= "<p><strong>WhatsApp :</strong> {$contact['value']}</p>";
            }

            if (($contact['type'] ?? null) === 'email') {
                $contactsHtml .= "<p><strong>Email :</strong> {$contact['value']}</p>";
            }
        }

        $missionUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/')
            . "/missions/{$mission->ulid}";

        $html = "
        <div style='font-family: Arial, sans-serif; max-width: 650px; margin: auto;'>
            <h2 style='color:#16a34a;'>Félicitations {$name} !</h2>

            <p>
                Votre candidature pour la mission
                <strong>{$mission->title}</strong> a été <strong>acceptée</strong>.
            </p>

            <div style='background:#f3f4f6; padding:16px; border-radius:8px; margin:20px 0;'>
                <p><strong>Date de début :</strong> {$startDate}</p>
                <p><strong>Lieu :</strong> {$location}</p>
                <p><strong>Rémunération :</strong> {$mission->remuneration_label}</p>
        ";

        if ($mission->remuneration_conditions) {
            $html .= "<p><strong>Conditions :</strong> {$mission->remuneration_conditions}</p>";
        }

        if ($contactsHtml) {
            $html .= "
                <hr>
                <h3>Contacts</h3>
                {$contactsHtml}
            ";
        }

        $html .= "
            </div>

            <p>
                <a href='{$missionUrl}' style='
                    display:inline-block;
                    background:#16a34a;
                    color:white;
                    padding:12px 18px;
                    text-decoration:none;
                    border-radius:6px;
                '>
                    Voir la mission
                </a>
            </p>

            <p>Bonne chance et bonne mission avec AgriPulse !</p>

            <hr>

            <p style='font-size:13px;color:#666;'>
                Cordialement,<br>
                L'équipe AgriPulse
            </p>
        </div>
        ";

        app(BrevoMailService::class)->send(
            $notifiable->email,
            $name,
            "Félicitations ! Candidature acceptée — {$mission->title}",
            $html
        );
    }

    public function toArray($notifiable): array
    {
        $mission = $this->application->mission;

        return [
            'type'          => 'application_accepted',
            'mission_ulid'  => $mission->ulid,
            'mission_title' => $mission->title,
            'url'           => rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/')
                . "/missions/{$mission->ulid}",
        ];
    }

    public function toFcm($notifiable): array
    {
        return [
            'title' => 'Candidature acceptée !',
            'body'  => "Vous avez été sélectionné(e) pour : {$this->application->mission->title}",
            'data'  => [
                'ulid' => $this->application->mission->ulid,
                'type' => 'application_accepted',
            ],
        ];
    }
}
