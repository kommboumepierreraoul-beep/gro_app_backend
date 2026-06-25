<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ModerationStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $contentType;
    public string $status;
    public ?string $reason;
    public array $scores;

    /**
     * Create a new message instance.
     */
    public function __construct(
        User $user,
        string $contentType,
        string $status,
        ?string $reason = null,
        array $scores = []
    ) {
        $this->user = $user;
        $this->contentType = $contentType;
        $this->status = $status;
        $this->reason = $reason;
        $this->scores = $scores;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📢 Statut de votre contenu sur AgriPulse',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.moderation-status',
            with: [
                'user' => $this->user,
                'contentType' => $this->contentType,
                'status' => $this->status,
                'reason' => $this->reason,
                'scores' => $this->scores,
                'statusLabel' => $this->getStatusLabel(),
                'statusColor' => $this->getStatusColor(),
                'contentTypeLabel' => $this->getContentTypeLabel(),
            ],
        );
    }

    /**
     * Get the status label.
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'approved' => '✅ Approuvé',
            'rejected' => '❌ Rejeté',
            'review' => '🔍 En vérification',
            'pending' => '⏳ En attente',
            default => '📢 Mis à jour',
        };
    }

    /**
     * Get the status color.
     */
    private function getStatusColor(): string
    {
        return match ($this->status) {
            'approved' => 'green',
            'rejected' => 'red',
            'review' => 'orange',
            'pending' => 'blue',
            default => 'gray',
        };
    }

    /**
     * Get the content type label.
     */
    private function getContentTypeLabel(): string
    {
        return match ($this->contentType) {
            'post' => 'publication',
            'comment' => 'commentaire',
            'message' => 'message',
            default => 'contenu',
        };
    }
}
