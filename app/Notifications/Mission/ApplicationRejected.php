<?php

namespace App\Notifications\Mission;

use App\Models\MissionApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ApplicationRejected extends Notification implements ShouldQueue
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
            ->subject("📝 Candidature non retenue — {$mission->title}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Nous vous remercions pour votre candidature à la mission **{$mission->title}**.")
            ->line("---");

        // ✅ Motif de rejet si disponible
        if ($this->application->rejection_reason) {
            $mail->line("")
                ->line("**Motif du rejet :**")
                ->line("> {$this->application->rejection_reason}");
        }

        $mail->line("")
            ->line("💡 **Conseils pour vos prochaines candidatures :**")
            ->line("• Assurez-vous que votre profil est complet")
            ->line("• Mettez en avant vos compétences clés")
            ->line("• Adaptez votre message à chaque mission");

        return $mail
            ->action('Découvrir d\'autres missions', url("http://localhost:3000/missions"))
            ->line("")
            ->line("Nous vous souhaitons bonne chance pour vos futures candidatures ! 🌱");
    }

    public function toArray($notifiable): array
    {
        return [
            'type'              => 'application_rejected',
            'mission_ulid'      => $this->application->mission->ulid,
            'mission_title'     => $this->application->mission->title,
            'rejection_reason'  => $this->application->rejection_reason,
            'url'               => "http://localhost:3000/missions",
        ];
    }

    public function toFcm($notifiable): array
    {
        return [
            'title' => '📝 Candidature non retenue',
            'body'  => "Votre candidature pour {$this->application->mission->title} n'a pas été retenue.",
            'data'  => [
                'ulid'  => $this->application->mission->ulid,
                'type'  => 'application_rejected'
            ],
        ];
    }
}
