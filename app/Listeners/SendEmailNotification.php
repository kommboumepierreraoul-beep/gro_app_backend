<?php

namespace App\Listeners;

use App\Events\ContentModerated;
use App\Mail\ModerationStatusMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendEmailNotification implements ShouldQueue
{
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Handle the event.
     */
    public function handle(ContentModerated $event): void
    {
        if (!$event->userId) {
            return;
        }

        // Ne pas envoyer d'email pour les statuts pending ou review
        if (in_array($event->status, ['pending', 'review'])) {
            return;
        }

        try {
            $user = User::find($event->userId);

            if (!$user) {
                return;
            }

            // Vérifier si l'utilisateur veut recevoir des emails
            if (!$this->shouldSendEmail($user)) {
                return;
            }

            Mail::to($user->email)->send(new ModerationStatusMail(
                $user,
                $event->contentType,
                $event->status,
                $event->reason,
                $event->scores
            ));

            Log::info('Email de modération envoyé', [
                'user_id' => $event->userId,
                'email' => $user->email,
                'status' => $event->status,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'envoi de l\'email', [
                'error' => $e->getMessage(),
                'user_id' => $event->userId,
            ]);
        }
    }

    /**
     * Vérifier si l'utilisateur veut recevoir des emails.
     */
    private function shouldSendEmail(User $user): bool
    {
        // Vérifier si l'utilisateur a une préférence de notification
        // Adaptez selon votre structure de données
        return true;
    }

    /**
     * Handle a job failure.
     */
    public function failed(ContentModerated $event, \Throwable $exception): void
    {
        Log::error('Échec de l\'envoi d\'email de modération', [
            'user_id' => $event->userId,
            'status' => $event->status,
            'error' => $exception->getMessage(),
        ]);
    }
}
