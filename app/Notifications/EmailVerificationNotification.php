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

        $html = view('emails.auth-code', [
            'title' => 'Verification de votre adresse email',
            'preheader' => 'Votre code de verification AgriPulse.',
            'badge' => 'Securite',
            'firstname' => $firstname,
            'intro' => 'Utilisez le code ci-dessous pour verifier votre adresse email et securiser votre compte AgriPulse.',
            'code' => $this->code,
            'validity' => '10 minutes',
            'ignoreText' => "Si vous n'avez pas cree de compte, vous pouvez ignorer cet email.",
        ])->render();

        app(BrevoMailService::class)->send(
            $notifiable->email,
            $firstname,
            'Verification de votre adresse email',
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
