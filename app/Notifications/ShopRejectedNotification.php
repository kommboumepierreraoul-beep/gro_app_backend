<?php

namespace App\Notifications;

use App\Models\Shop;
use Illuminate\Notifications\Notification;

class ShopRejectedNotification extends Notification
{
    public function __construct(public Shop $shop, public string $reason) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Boutique a corriger',
            'message' => "Votre demande de boutique {$this->shop->name} n'a pas ete approuvee : {$this->reason}",
            'type' => 'shop_rejected',
            'shop_id' => $this->shop->id,
            'reason' => $this->reason,
            'url' => '/create-shop',
        ];
    }
}
