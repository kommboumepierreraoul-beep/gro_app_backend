<?php
<<<<<<< HEAD
namespace App\Mail;
use App\Models\Dispute;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
class DisputeResolvedMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public Dispute $dispute) {}
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Litige résolu - Commande #' . $this->dispute->order->order_number);
    }
    public function content(): Content
    {
        return new Content(view: 'emails.disputes.resolved');
=======

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DisputeResolvedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dispute Resolved Mail',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'view.name',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
    }
}
