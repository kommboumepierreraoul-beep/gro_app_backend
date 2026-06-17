<?php

namespace App\Mail;

use App\Models\Dispute;
use App\Models\DisputeMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewDisputeMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public $dispute;
    public $message;

    public function __construct(Dispute $dispute, DisputeMessage $message)
    {
        $this->dispute = $dispute;
        $this->message = $message;
    }

    public function build()
    {
        return $this->subject('Nouveau message dans votre litige')
                    ->markdown('emails.disputes.new-message');
    }
}
