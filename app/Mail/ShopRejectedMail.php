<?php

namespace App\Mail;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ShopRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Shop $shop,
        public string $reason
    ) {}

    public function build()
    {
        return $this->subject('Votre demande de boutique AgriPulse necessite des corrections')
            ->view('emails.shop-rejected');
    }
}
