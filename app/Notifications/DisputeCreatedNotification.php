<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use App\Models\Dispute;
class DisputeCreatedNotification extends Notification
{
    public function __construct(public Dispute $dispute) {}
    public function via($notifiable): array { return ['database']; }
    public function toDatabase($notifiable): array {
        return [
            'title'      => 'Nouveau litige reçu',
            'message'    => "Un client a ouvert un litige sur la commande #{$this->dispute->order->order_number}.",
            'type'       => 'dispute_created',
            'dispute_id' => $this->dispute->id,
            'url'        => '/seller/disputes/' . $this->dispute->id,
        ];
    }
}
