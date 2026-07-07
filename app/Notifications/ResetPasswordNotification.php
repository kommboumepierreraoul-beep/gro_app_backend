<?php

namespace App\Notifications;

use App\Services\BrevoMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code
    ) {}

    public function via(object $notifiable): array
    {
        return [];
    }

    public function sendWithBrevo(object $notifiable): void
    {
        $firstname = $notifiable->firstname ?? $notifiable->name ?? 'Utilisateur';

        $html = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto'>
            <h2 style='color:#16a34a;'>AgriPulse</h2>

            <p>Bonjour <strong>{$firstname}</strong>,</p>

            <p>Nous avons reçu une demande de réinitialisation de mot de passe.</p>

            <p>Utilise le code ci-dessous :</p>

            <div style='
                background:#f3f4f6;
                padding:20px;
                text-align:center;
                font-size:32px;
                font-weight:bold;
                letter-spacing:6px;
                border-radius:8px;
                margin:20px 0;
            '>
                {$this->code}
            </div>

            <p>Ce code est valide pendant <strong>15 minutes</strong>.</p>

            <p>Si tu n'as pas demandé cette réinitialisation, ignore simplement cet email.</p>

            <hr>

            <p style='color:#666;font-size:13px'>
                Cordialement,<br>
                L'équipe AgriPulse
            </p>
        </div>
        ";

        app(BrevoMailService::class)->send(
            $notifiable->email,
            $firstname,
            'Réinitialisation du mot de passe',
            $html
        );
    }
}
