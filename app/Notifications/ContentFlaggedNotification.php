<?php
// app/Notifications/ContentFlaggedNotification.php

namespace App\Notifications;

use App\Models\ModerationLog;
use App\Services\BrevoMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ContentFlaggedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected ModerationLog $moderationLog;
    protected mixed $content;

    public function __construct(ModerationLog $moderationLog, mixed $content)
    {
        $this->moderationLog = $moderationLog;
        $this->content = $content;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function sendWithBrevo(object $notifiable): void
    {
        $contentType = class_basename($this->content);
        $contentId = $this->content->id;
        $name = $notifiable->name ?? $notifiable->firstname ?? 'Administrateur';

        $categories = is_array($this->moderationLog->categories)
            ? implode(', ', $this->moderationLog->categories)
            : ($this->moderationLog->categories ?? 'Non spécifiées');

        $url = url('/admin/moderation/' . $contentId);

        $html = "
            <div style='font-family: Arial, sans-serif; max-width: 650px; margin: auto;'>
                <h2 style='color: #dc2626;'>Contenu signalé - Action requise</h2>

                <p>Bonjour <strong>{$name}</strong>,</p>

                <p>
                    Un contenu a été signalé par le système de modération automatique.
                </p>

                <div style='background: #f9fafb; padding: 16px; border-radius: 8px;'>
                    <p><strong>Type de contenu :</strong> {$contentType}</p>
                    <p><strong>ID du contenu :</strong> {$contentId}</p>
                    <p><strong>Score de risque :</strong> {$this->moderationLog->score}</p>
                    <p><strong>Raison :</strong> " . ($this->moderationLog->reason ?? 'Non spécifiée') . "</p>
                    <p><strong>Catégories :</strong> {$categories}</p>
                    <p><strong>Action :</strong> {$this->moderationLog->action}</p>
                </div>

                <p style='margin-top: 20px;'>
                    Veuillez examiner ce contenu dans les plus brefs délais.
                </p>

                <p>
                    <a href='{$url}' style='
                        background: #16a34a;
                        color: white;
                        padding: 12px 18px;
                        text-decoration: none;
                        border-radius: 6px;
                        display: inline-block;
                    '>
                        Voir le contenu
                    </a>
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
            $name,
            'Contenu signalé - Action requise',
            $html
        );
    }

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

    public function databaseType(object $notifiable): string
    {
        return 'content_flagged';
    }
}
