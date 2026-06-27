<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use App\Models\Dispute;
class DisputeResolvedNotification extends Notification
{
    public function __construct(public Dispute $dispute) {}
    public function via($notifiable): array { return ['database']; }
    public function toDatabase($notifiable): array {
        return [
            'title'      => 'Litige resolu',
            'message'    => "Votre litige sur la commande #{$this->dispute->order->order_number} a ete resolu.",
            'type'       => 'dispute_resolved',
            'dispute_id' => $this->dispute->id,
            'url'        => '/disputes/' . $this->dispute->id,
        ];
    }
}
