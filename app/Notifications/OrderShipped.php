<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use App\Models\Order;

class OrderShipped extends Notification
{
    public function __construct(public Order $order) {}
    public function via($notifiable): array { return ['database']; }
    public function toDatabase($notifiable): array {
        return [
            'title'    => 'Commande expédiée',
            'message'  => "Votre commande #{$this->order->order_number} est en route !",
            'type'     => 'order_shipped',
            'order_id' => $this->order->id,
            'url'      => '/orders',
        ];
    }
}
