<?php

namespace App\Http\Controllers\Api\Mission;

use App\Http\Controllers\Controller;
use App\Models\MissionApplication;
use App\Models\MissionReminder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MissionApplicationController extends Controller
{
    // ... Ta méthode store() existante ...

    /**
     * Planifie les rappels automatiques pour une candidature de mission.
     */
    protected function scheduleReminders(MissionApplication $application): void
    {
        $mission = $application->mission;
        $user = $application->applicant; // Ou $application->applicant_id selon tes besoins

        // Sécurité au cas où start_date n'est pas un objet Carbon casté
        $startDate = Carbon::parse($mission->start_date);
        $now = Carbon::now();

        // 1. Liste des types de rappels avec leurs calculs temporels associés
        $remindersConfiguration = [
            MissionReminder::TYPE_48H          => $startDate->copy()->subHours(48),
            MissionReminder::TYPE_24H          => $startDate->copy()->subHours(24),
            MissionReminder::TYPE_2H           => $startDate->copy()->subHours(2),
            MissionReminder::TYPE_STARTED      => $startDate->copy(),
            // Exemple : Demander un avis 24h après la fin de la mission (si fin définie) ou après le début
            MissionReminder::TYPE_REVIEW_PROMPT => $startDate->copy()->addDays(2),
        ];

        foreach ($remindersConfiguration as $type => $remindAt) {
            // Uniquement si la date du rappel est encore dans le futur
            if ($remindAt->isAfter($now)) {

                // updateOrCreate évite les doublons si l'utilisateur annule et re-postule
                MissionReminder::updateOrCreate(
                    [
                        'mission_id' => $mission->id,
                        'user_id'    => $user->id,
                        'type'       => $type,
                    ],
                    [
                        'remind_at'  => $remindAt,
                        'sent'       => false,
                        'sent_at'    => null,
                    ]
                );
            }
        }
    }
}
