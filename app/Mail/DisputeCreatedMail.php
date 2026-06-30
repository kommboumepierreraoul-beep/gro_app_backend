<?php
namespace App\Mail;
use App\Models\Dispute;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
class DisputeCreatedMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public Dispute $dispute) {}
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nouveau litige - Commande #' . $this->dispute->order->order_number);
    }
    public function content(): Content
    {
        return new Content(view: 'emails.disputes.created');
    }
}
