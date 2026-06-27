<?php

namespace App\Notifications\Mission;

use App\Models\MissionApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ApplicationAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private MissionApplication $application) {}

    public function via($notifiable): array
    {
        $channels = ['mail', 'database'];
        if ($notifiable->fcm_token ?? null) $channels[] = 'fcm';
        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $mission = $this->application->mission;

        $mail = (new MailMessage)
            ->subject("🎉 Félicitations ! Candidature acceptée — {$mission->title}")
            ->greeting("Félicitations {$notifiable->name} !")
            ->line("Votre candidature pour la mission **{$mission->title}** a été **acceptée**.")
            ->line("---");

        if ($mission->start_date) {
            $mail->line("📅 **Date de début :** " . $mission->start_date->format('d/m/Y'));
        }

        if ($mission->location_label) {
            $mail->line("📍 **Lieu :** {$mission->location_label}");
        }

        $mail->line("💰 **Rémunération :** {$mission->remuneration_label}");

        if ($mission->remuneration_conditions) {
            $mail->line("📋 **Conditions :** {$mission->remuneration_conditions}");
        }

        // Contacts
        foreach ($mission->contact_methods ?? [] as $contact) {
            match ($contact['type']) {
                'whatsapp' => $mail->line("📱 **WhatsApp :** {$contact['value']}"),
                'email'    => $mail->line("✉️ **Email :** {$contact['value']}"),
                default    => null,
            };
        }

        return $mail
            ->action('Voir la mission', url("http://localhost:3000/missions/{$mission->ulid}"))
            ->line('Bonne chance et bonne mission avec AgriPulse ! 🌱');
    }

    public function toArray($notifiable): array
    {
        return [
            'type'          => 'application_accepted',
            'mission_ulid'  => $this->application->mission->ulid,
            'mission_title' => $this->application->mission->title,
            'url'           => "http://localhost:3000/missions/{$this->application->mission->ulid}",
        ];
    }

    public function toFcm($notifiable): array
    {
        return [
            'title' => '🎉 Candidature acceptée !',
            'body'  => "Vous avez été sélectionné(e) pour : {$this->application->mission->title}",
            'data'  => ['ulid' => $this->application->mission->ulid, 'type' => 'application_accepted'],
        ];
    }
}
