<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use App\Models\Dispute;
class DisputeEscalatedNotification extends Notification
{
    public function __construct(public Dispute $dispute) {}
    public function via($notifiable): array { return ['database']; }
    public function toDatabase($notifiable): array {
        return [
            'title'      => 'Litige escalade',
            'message'    => "Le litige sur la commande #{$this->dispute->order->order_number} a ete transmis a l'administrateur.",
            'type'       => 'dispute_escalated',
            'dispute_id' => $this->dispute->id,
            'url'        => '/disputes/' . $this->dispute->id,
        ];
    }
}
