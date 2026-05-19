<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset Your Password')
            ->greeting('Hello ' . $notifiable->firstname . '!')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->line('Use the code below to reset your password. It is valid for **15 minutes**.')
            ->line('')
            ->line('## ' . $this->code)
            ->line('')
            ->line('If you did not request a password reset, no further action is required.')
            ->salutation('Regards, ' . config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return ['code' => $this->code];
    }
}
