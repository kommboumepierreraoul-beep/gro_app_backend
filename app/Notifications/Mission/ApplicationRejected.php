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

        $reasonHtml = '';

        if ($this->application->rejection_reason) {
            $reasonHtml = "
                <div style='background:#fff7ed; padding:14px; border-radius:8px; margin:18px 0;'>
                    <p><strong>Motif du rejet :</strong></p>
                    <p>{$this->application->rejection_reason}</p>
                </div>
            ";
        }

        $missionsUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/')
            . "/missions";

        $html = "
        <div style='font-family: Arial, sans-serif; max-width: 650px; margin: auto;'>
            <h2 style='color:#16a34a;'>Candidature non retenue</h2>

            <p>Bonjour <strong>{$name}</strong>,</p>

            <p>
                Nous vous remercions pour votre candidature à la mission
                <strong>{$mission->title}</strong>.
            </p>

            {$reasonHtml}

            <div style='background:#f3f4f6; padding:16px; border-radius:8px; margin:20px 0;'>
                <p><strong>Conseils pour vos prochaines candidatures :</strong></p>
                <ul>
                    <li>Assurez-vous que votre profil est complet.</li>
                    <li>Mettez en avant vos compétences clés.</li>
                    <li>Adaptez votre message à chaque mission.</li>
                </ul>
            </div>

            <p>
                <a href='{$missionsUrl}' style='
                    display:inline-block;
                    background:#16a34a;
                    color:white;
                    padding:12px 18px;
                    text-decoration:none;
                    border-radius:6px;
                '>
                    Découvrir d'autres missions
                </a>
            </p>

            <p>Nous vous souhaitons bonne chance pour vos futures candidatures !</p>

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
            "Candidature non retenue — {$mission->title}",
            $html
        );
    }

    public function toArray($notifiable): array
    {
        $mission = $this->application->mission;

        return [
            'type'             => 'application_rejected',
            'mission_ulid'     => $mission->ulid,
            'mission_title'    => $mission->title,
            'rejection_reason' => $this->application->rejection_reason,
            'url'              => rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/') . "/missions",
        ];
    }

    public function toFcm($notifiable): array
    {
        return [
            'title' => 'Candidature non retenue',
            'body'  => "Votre candidature pour {$this->application->mission->title} n'a pas été retenue.",
            'data'  => [
                'ulid' => $this->application->mission->ulid,
                'type' => 'application_rejected',
            ],
        ];
    }
}
