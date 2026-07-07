<?php

namespace App\Notifications;

use App\Services\BrevoMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class EmailVerificationNotification extends Notification implements ShouldQueue
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
        $firstname = $notifiable->firstname ?? 'Utilisateur';

        $html = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto;'>
                <h2 style='color: #16a34a;'>AgriPulse</h2>

                <p>Bonjour <strong>{$firstname}</strong>,</p>

                <p>
                    Utilise le code ci-dessous pour vérifier ton adresse email.
                </p>

                <p>
                    Ce code est valide pendant <strong>10 minutes</strong>.
                </p>

                <div style='
                    background: #f3f4f6;
                    padding: 20px;
                    text-align: center;
                    font-size: 32px;
                    font-weight: bold;
                    letter-spacing: 6px;
                    border-radius: 8px;
                    margin: 20px 0;
                '>
                    {$this->code}
                </div>

                <p>
                    Si tu n'as pas créé de compte, ignore simplement cet email.
                </p>

                <hr>

                <p style='font-size: 13px; color: #666;'>
                    Cordialement,<br>
                    L'équipe AgriPulse
                </p>
            </div>
        ";

        app(BrevoMailService::class)->send(
            $notifiable->email,
            $firstname,
            'Vérification de votre adresse email',
            $html
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'code' => $this->code,
        ];
    }
}
