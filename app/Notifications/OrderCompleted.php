<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use App\Models\Order;

class OrderCompleted extends Notification
{
    public function __construct(public Order $order) {}
    public function via($notifiable): array { return ['database']; }
    public function toDatabase($notifiable): array {
        return [
            'title'    => 'Commande complétée',
            'message'  => "La commande #{$this->order->order_number} est terminée. Fonds libérés.",
            'type'     => 'order_completed',
            'order_id' => $this->order->id,
            'url'      => '/orders',
        ];
    }
}
