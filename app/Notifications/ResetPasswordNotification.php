<?php

namespace App\Notifications;

use App\Services\BrevoMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $code
    ) {}

    /**
     * Envoie directement l'email via Brevo API.
     */
    public function send(object $notifiable): void
    {
        $brevo = app(BrevoMailService::class);

        $html = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto'>
            <h2 style='color:#16a34a;'>AgriPulse</h2>

            <p>Hello <strong>{$notifiable->firstname}</strong>,</p>

            <p>
                We received a request to reset your password.
            </p>

            <p>
                Use the verification code below:
            </p>

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

            <p>
                This code is valid for <strong>15 minutes</strong>.
            </p>

            <p>
                If you did not request a password reset, you can safely ignore this email.
            </p>

            <hr>

            <p style='color:#666;font-size:13px'>
                Regards,<br>
                AgriPulse Team
            </p>
        </div>
        ";

        $brevo->send(
            $notifiable->email,
            $notifiable->firstname,
            'Reset Your Password',
            $html
        );
    }
}
