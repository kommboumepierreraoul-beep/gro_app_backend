<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use App\Models\Order;

class NewOrderReceived extends Notification
{
    public function __construct(public Order $order) {}
    public function via($notifiable): array { return ['database']; }
    public function toDatabase($notifiable): array {
        return [
            'title'    => 'Nouvelle commande',
            'message'  => "Vous avez reçu une nouvelle commande #{$this->order->order_number}.",
            'type'     => 'new_order',
            'order_id' => $this->order->id,
            'url'      => '/orders',
        ];
    }
}
