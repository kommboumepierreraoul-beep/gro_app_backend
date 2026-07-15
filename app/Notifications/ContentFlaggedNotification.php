<?php

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
            : ($this->moderationLog->categories ?? 'Non specifiees');
        $reason = $this->moderationLog->reason ?? 'Non specifiee';
        $score = $this->moderationLog->score;
        $action = $this->moderationLog->action;
        $url = rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/')
            . '/admin/moderation/' . $contentId;

        $html = view('emails.content-flagged', compact(
            'contentType',
            'contentId',
            'name',
            'categories',
            'reason',
            'score',
            'action',
            'url'
        ))->render();

        app(BrevoMailService::class)->send(
            $notifiable->email,
            $name,
            'Contenu signale - Action requise',
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
            'message' => 'Un contenu a ete signale et necessite votre attention.',
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return 'content_flagged';
    }
}
