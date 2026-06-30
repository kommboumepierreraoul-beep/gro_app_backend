<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use App\Models\Dispute;
class DisputeRespondedNotification extends Notification
{
    public function __construct(public Dispute $dispute) {}
    public function via($notifiable): array { return ['database']; }
    public function toDatabase($notifiable): array {
        return [
            'title'      => 'Réponse à votre litige',
            'message'    => "Le vendeur a répondu à votre litige sur la commande #{$this->dispute->order->order_number}.",
            'type'       => 'dispute_responded',
            'dispute_id' => $this->dispute->id,
            'url'        => '/disputes/' . $this->dispute->id,
        ];
    }
}
