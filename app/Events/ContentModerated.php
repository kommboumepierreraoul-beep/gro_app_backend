<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentModerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $contentType;
    public int $contentId;
    public string $status;
    public ?string $reason;
    public ?int $userId;
    public array $scores;
    public string $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $contentType,
        int $contentId,
        string $status,
        ?string $reason = null,
        ?int $userId = null,
        array $scores = []
    ) {
        $this->contentType = $contentType;
        $this->contentId = $contentId;
        $this->status = $status;
        $this->reason = $reason;
        $this->userId = $userId;
        $this->scores = $scores;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Canal pour l'utilisateur spécifique
        if ($this->userId) {
            $channels[] = new PrivateChannel('user.' . $this->userId);
        }

        // Canal pour les modérateurs
        $channels[] = new PrivateChannel('moderation');

        // Canal pour le type de contenu spécifique
        $channels[] = new PrivateChannel('moderation.' . $this->contentType);

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'content.moderated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'content_type' => $this->contentType,
            'content_id' => $this->contentId,
            'status' => $this->status,
            'reason' => $this->reason,
            'user_id' => $this->userId,
            'scores' => $this->scores,
            'timestamp' => $this->timestamp,
            'message' => $this->getStatusMessage(),
        ];
    }

    /**
     * Get a human-readable message for the status.
     */
    private function getStatusMessage(): string
    {
        return match ($this->status) {
            'approved' => 'Votre contenu a été approuvé et est maintenant visible.',
            'rejected' => 'Votre contenu a été rejeté. Motif: ' . ($this->reason ?? 'Non conforme aux règles.'),
            'review' => 'Votre contenu est en cours de vérification par un modérateur.',
            'pending' => 'Votre contenu est en attente d\'analyse.',
            default => 'Le statut de votre contenu a changé.',
        };
    }
}
