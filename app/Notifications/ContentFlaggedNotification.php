<?php
// app/Notifications/ContentFlaggedNotification.php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\ModerationLog;

class ContentFlaggedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $moderationLog;
    protected $content;

    /**
     * Create a new notification instance.
     */
    public function __construct(ModerationLog $moderationLog, $content)
    {
        $this->moderationLog = $moderationLog;
        $this->content = $content;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $contentType = class_basename($this->content);
        $contentId = $this->content->id;

        return (new MailMessage)
            ->subject('Contenu signalé - Action requise')
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Un contenu a été signalé par notre système de modération automatique.')
            ->line('**Type de contenu :** ' . $contentType)
            ->line('**ID du contenu :** ' . $contentId)
            ->line('**Score de risque :** ' . $this->moderationLog->score)
            ->line('**Raison :** ' . ($this->moderationLog->reason ?? 'Non spécifiée'))
            ->when($this->moderationLog->categories, function ($mail) {
                $categories = implode(', ', $this->moderationLog->categories);
                return $mail->line('**Catégories :** ' . $categories);
            })
            ->action('Voir le contenu', url('/admin/moderation/' . $contentId))
            ->line('Veuillez examiner ce contenu dans les plus brefs délais.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'moderation_log_id' => $this->moderationLog->id,
            'content_type' => class_basename($this->content),
            'content_id' => $this->content->id,
            'is_safe' => $this->moderationLog->is_safe,
            'score' => $this->moderationLog->score,
            'categories' => $this->moderationLog->categories,
            'reason' => $this->moderationLog->reason,
            'action' => $this->moderationLog->action,
            'message' => 'Un contenu a été signalé et nécessite votre attention.',
        ];
    }

    /**
     * Get the notification's database type.
     */
    public function databaseType(object $notifiable): string
    {
        return 'content_flagged';
    }
}
