<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class DepositCompleted extends Notification
{
    public function __construct(public float $amount) {}
    public function via($notifiable): array { return ['database']; }
    public function toArray($notifiable): array { return $this->toDatabase($notifiable); }
    public function toDatabase($notifiable): array {
        return [
            'title'   => 'Dépôt effectué',
            'message' => "Votre wallet a été crédité de {$this->amount} FCFA.",
            'type'    => 'deposit_completed',
            'url'     => '/wallet',
        ];
    }
}
