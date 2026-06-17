<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;

class ProductApproved extends Notification
{
    public function __construct(public string $productName) {}
    public function via($notifiable): array { return ['database']; }
    public function toDatabase($notifiable): array {
        return [
            'title'   => 'Produit approuvé',
            'message' => "Votre produit \"{$this->productName}\" est maintenant visible.",
            'type'    => 'product_approved',
            'url'     => '/my-shop',
        ];
    }
}
