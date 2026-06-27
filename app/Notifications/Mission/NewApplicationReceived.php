<?php

namespace App\Notifications\Mission;

use App\Mail\NewApplicationMail;
use App\Models\MissionApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewApplicationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private MissionApplication $application) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Utilise le Mailable dédié NewApplicationMail (template Markdown).
     */
    public function toMail($notifiable)
    {
        return (new NewApplicationMail($this->application))
            ->to($notifiable->email);
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
            'title'          => 'Nouvelle candidature',
            'body'           => "{$this->application->applicant->name} a postulé à \"{$this->application->mission->title}\"",
            'url'            => "/missions/{$this->application->mission->ulid}/applications",
        ];
    }
}
