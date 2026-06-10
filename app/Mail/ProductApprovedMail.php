<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProductApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $product;

    public function __construct($user, $product)
    {
        $this->user = $user;
        $this->product = $product;
    }

    public function build()
    {
        return $this->subject('Votre produit a été approuvé')
                    ->view('emails.product-approved');
    }
}