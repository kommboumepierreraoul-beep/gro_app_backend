<?php

namespace App\Notifications;

use App\Models\Shop;
use Illuminate\Notifications\Notification;

class ShopApprovedNotification extends Notification
{
    public function __construct(public Shop $shop) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Boutique approuvee',
            'message' => "Votre boutique {$this->shop->name} a ete approuvee. Vous pouvez maintenant vendre sur AgriPulse.",
            'type' => 'shop_approved',
            'shop_id' => $this->shop->id,
            'url' => '/my-shop',
        ];
    }
}
