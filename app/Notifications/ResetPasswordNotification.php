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

        $html = view('emails.auth-code', [
            'title' => 'Reinitialisation du mot de passe',
            'preheader' => 'Votre code de reinitialisation AgriPulse.',
            'badge' => 'Compte',
            'accent' => '#154212',
            'firstname' => $firstname,
            'intro' => 'Nous avons recu une demande de reinitialisation de mot de passe pour votre compte AgriPulse.',
            'code' => $this->code,
            'validity' => '15 minutes',
            'ignoreText' => "Si vous n'avez pas demande cette reinitialisation, vous pouvez ignorer cet email.",
        ])->render();

        app(BrevoMailService::class)->send(
            $notifiable->email,
            $firstname,
            'Reinitialisation du mot de passe',
            $html
        );
    }
}
