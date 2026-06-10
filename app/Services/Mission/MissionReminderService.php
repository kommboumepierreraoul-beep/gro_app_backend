<?php

namespace App\Services\Mission;

use App\Models\MissionReminder;
use App\Notifications\Mission\MissionReminderNotification;
use Illuminate\Support\Facades\Log;

class MissionReminderService
{
    /**
     * Envoyer tous les rappels dont la date est dépassée.
     * Appelé par le job SendMissionRemindersJob (toutes les 5 min).
     */
    public function sendDue(): void
    {
        $sent = 0;
        $errors = 0;

        MissionReminder::query()
            ->where('sent', false)
            ->where('remind_at', '<=', now())
            ->with(['mission:id,ulid,title,start_date,location_label', 'user:id,name,email'])
            ->chunkById(100, function ($reminders) use (&$sent, &$errors) {
                foreach ($reminders as $reminder) {
                    try {
                        $reminder->user->notify(
                            new MissionReminderNotification($reminder)
                        );

                        $reminder->update([
                            'sent'    => true,
                            'sent_at' => now(),
                        ]);

                        $sent++;
                    } catch (\Throwable $e) {
                        Log::error("Erreur rappel #{$reminder->id} : {$e->getMessage()}");
                        $errors++;
                    }
                }
            });

        Log::info("Rappels missions : {$sent} envoyés, {$errors} erreurs.");
    }
}
