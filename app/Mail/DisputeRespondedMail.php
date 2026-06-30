<?php
namespace App\Mail;
use App\Models\Dispute;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
class DisputeRespondedMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public Dispute $dispute) {}
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Réponse à votre litige #' . $this->dispute->id);
    }
    public function content(): Content
    {
        return new Content(view: 'emails.disputes.responded');
    }
}
