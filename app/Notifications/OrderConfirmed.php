<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use App\Models\Order;

class OrderConfirmed extends Notification
{
    public function __construct(public Order $order) {}
    public function via($notifiable): array { return ['database']; }
    public function toDatabase($notifiable): array {
        return [
            'title'    => 'Commande confirmée',
            'message'  => "Votre commande #{$this->order->order_number} a été confirmée.",
            'type'     => 'order_confirmed',
            'order_id' => $this->order->id,
            'url'      => '/orders',
        ];
    }
}
