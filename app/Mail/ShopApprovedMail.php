<?php

namespace App\Mail;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ShopApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public Shop $shop) {}

    public function build()
    {
        return $this->subject('Votre boutique AgriPulse est approuvee')
            ->view('emails.shop-approved');
    }
}
