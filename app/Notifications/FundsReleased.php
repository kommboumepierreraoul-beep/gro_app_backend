<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use App\Models\Order;

class FundsReleased extends Notification
{
    public function __construct(public Order $order) {}
    public function via($notifiable): array { return ['database']; }
    public function toDatabase($notifiable): array {
        return [
            'title'    => 'Fonds reçus',
            'message'  => "Vous avez reçu {$this->order->total_amount} FCFA pour la commande #{$this->order->order_number}.",
            'type'     => 'funds_released',
            'order_id' => $this->order->id,
            'url'      => '/wallet',
        ];
    }
}
