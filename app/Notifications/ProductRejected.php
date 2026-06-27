<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;

class ProductRejected extends Notification
{
    public function __construct(public string $productName, public string $reason = '') {}
    public function via($notifiable): array { return ['database']; }
    public function toDatabase($notifiable): array {
        return [
            'title'   => 'Produit rejeté',
            'message' => "Votre produit \"{$this->productName}\" a été rejeté." . ($this->reason ? " Raison : {$this->reason}" : ''),
            'type'    => 'product_rejected',
            'url'     => '/my-shop',
        ];
    }
}
