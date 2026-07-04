<?php

namespace App\Notifications\Mission;

use App\Models\MissionApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class NewApplicationReceived extends Notification implements ShouldQueue
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
        $applicant = $this->application->applicant;

        $mail = (new MailMessage)
            ->subject("📝 Nouvelle candidature — {$mission->title}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Un nouveau candidat a postulé à votre mission **{$mission->title}**.")
            ->line("---");

        // ✅ Informations du candidat
        $mail->line("")
            ->line("**👤 Candidat :**")
            ->line("- **Nom :** {$applicant->name}")
            ->line("- **Email :** {$applicant->email}");

        // ✅ Méthode de contact
        if ($this->application->method) {
            $mail->line("- **Méthode de contact :** " . $this->getMethodLabel($this->application->method));
        }

        // ✅ Motivation
        if ($this->application->motivation) {
            $mail->line("")
                ->line("**💬 Message de motivation :**")
                ->line("> {$this->application->motivation}");
        }

        // ✅ Détails de la mission
        $mail->line("")
            ->line("---")
            ->line("**📋 Détails de la mission :**")
            ->line("- **Titre :** {$mission->title}");

        if ($mission->start_date) {
            $mail->line("- **📅 Date de début :** " . $mission->start_date->format('d/m/Y'));
        }

        if ($mission->location_label) {
            $mail->line("- **📍 Lieu :** {$mission->location_label}");
        }

        $mail->line("- **💰 Rémunération :** {$mission->remuneration_label}");

        // ✅ Actions
        return $mail
            ->action('Voir les candidatures', url("http://localhost:3000/missions/{$mission->ulid}/applications"))
            ->line("")
            ->line("📌 Vous pouvez accepter ou refuser cette candidature depuis votre espace.")
            ->salutation("L'équipe GRO");
    }

    public function toArray($notifiable): array
    {
        $mission = $this->application->mission;
        $applicant = $this->application->applicant;

        return [
            'type'             => 'new_application',
            'icon'             => '📝',
            'title'            => 'Nouvelle candidature',
            'message'          => "{$applicant->name} a postulé à \"{$mission->title}\"",

            // Mission
            'mission_ulid'     => $mission->ulid,
            'mission_title'    => $mission->title,

            // Candidat
            'applicant_id'     => $applicant->id,
            'applicant_name'   => $applicant->name,
            'applicant_email'  => $applicant->email,

            // Candidature
            'application_id'   => $this->application->id,
            'method'           => $this->application->method,

            // Actions
            'url'              => "/missions/{$mission->ulid}/applications",
            'action_label'     => 'Voir les candidatures',

            // Meta
            'is_read'          => false,
            'created_at'       => now()->toISOString(),
        ];
    }

    public function toFcm($notifiable): array
    {
        $mission = $this->application->mission;
        $applicant = $this->application->applicant;

        return [
            'title' => '📝 Nouvelle candidature',
            'body'  => "{$applicant->name} a postulé à \"{$mission->title}\"",
            'data'  => [
                'type'           => 'new_application',
                'mission_ulid'   => $mission->ulid,
                'application_id' => $this->application->id,
            ],
        ];
    }

    /**
     * Obtenir le libellé de la méthode de contact
     */
    private function getMethodLabel(string $method): string
    {
        return match ($method) {
            'form'          => 'Formulaire en ligne',
            'app_message'   => 'Message sur l\'application',
            'whatsapp'      => 'WhatsApp',
            'email'         => 'Email',
            default         => $method,
        };
    }
}
