<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProductRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $product;
    public $reason;

    public function __construct($user, $product, $reason)
    {
        $this->user = $user;
        $this->product = $product;
        $this->reason = $reason;
    }

    public function build()
    {
        return $this->subject('Votre produit a été rejeté')
                    ->view('emails.product-rejected');
    }
}