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
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $applicant = $this->application->applicant;
        $mission   = $this->application->mission;

        $mail = (new MailMessage)
            ->subject("📬 Nouvelle candidature — {$mission->title}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("**{$applicant->name}** a postulé à votre mission **{$mission->title}**.")
            ->line("---");

        // Résumé des réponses au formulaire
        if (!empty($this->application->form_responses)) {
            $mail->line("**Réponses au formulaire :**");
            foreach ($mission->application_form ?? [] as $field) {
                $val = $this->application->form_responses[$field['id']] ?? '—';
                if (is_bool($val)) $val = $val ? 'Oui' : 'Non';
                $mail->line("• {$field['label']} : **{$val}**");
            }
            $mail->line("---");
        }

        if ($this->application->motivation) {
            $mail->line("**Motivation :** {$this->application->motivation}");
        }

        if (!empty($this->application->attachment_paths)) {
            $count = count($this->application->attachment_paths);
            $mail->line("📎 {$count} pièce(s) jointe(s) disponible(s) dans l'application.");
        }

        return $mail
            ->action('Voir la candidature', url("/missions/{$mission->ulid}/applications"))
            ->line('Vous pouvez accepter ou refuser cette candidature depuis votre tableau de bord GRO.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type'           => 'new_application',
            'mission_ulid'   => $this->application->mission->ulid,
            'mission_title'  => $this->application->mission->title,
            'applicant_id'   => $this->application->applicant_id,
            'applicant_name' => $this->application->applicant->name,
            'application_id' => $this->application->id,
            'url'            => "/missions/{$this->application->mission->ulid}/applications",
        ];
    }
}
