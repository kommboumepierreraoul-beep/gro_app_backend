<?php

namespace App\Mail;

use App\Models\Dispute;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DisputeEscalatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $dispute;

    public function __construct(Dispute $dispute)
    {
        $this->dispute = $dispute;
    }

    public function build()
    {
        return $this->subject('Litige escaladé - Intervention requise')
                    ->markdown('emails.disputes.escalated');
    }
}
