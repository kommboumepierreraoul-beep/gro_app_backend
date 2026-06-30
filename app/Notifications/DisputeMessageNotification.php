<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use App\Models\Dispute;
class DisputeMessageNotification extends Notification
{
    public function __construct(public Dispute $dispute, public string $senderName) {}
    public function via($notifiable): array { return ['database']; }
    public function toDatabase($notifiable): array {
        return [
            'title'      => 'Nouveau message',
            'message'    => "{$this->senderName} vous a envoye un message sur le litige #{$this->dispute->id}.",
            'type'       => 'dispute_message',
            'dispute_id' => $this->dispute->id,
            'url'        => '/disputes/' . $this->dispute->id,
        ];
    }
}
