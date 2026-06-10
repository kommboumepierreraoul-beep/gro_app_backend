<?php

namespace App\Jobs\Mission;

use App\Models\Mission;
use App\Models\MissionApplication;
use App\Models\MissionReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScheduleReviewPromptsJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public function __construct(private Mission $mission) {}

    public function handle(): void
    {
        // Récupérer les candidats acceptés
        $acceptedApplications = MissionApplication::where('mission_id', $this->mission->id)
            ->where('status', MissionApplication::STATUS_ACCEPTED)
            ->get();

        $reviewAt = now()->addDay()->startOfDay()->addHours(10); // J+1 à 10h

        // Créer rappel d'évaluation pour chaque candidat accepté
        foreach ($acceptedApplications as $app) {
            MissionReminder::updateOrCreate(
                [
                    'mission_id' => $this->mission->id,
                    'user_id'    => $app->applicant_id,
                    'type'       => MissionReminder::TYPE_REVIEW_PROMPT,
                ],
                ['remind_at' => $reviewAt, 'sent' => false]
            );
        }

        // Créer rappel d'évaluation pour l'auteur aussi
        MissionReminder::updateOrCreate(
            [
                'mission_id' => $this->mission->id,
                'user_id'    => $this->mission->author_id,
                'type'       => MissionReminder::TYPE_REVIEW_PROMPT,
            ],
            ['remind_at' => $reviewAt->addHour(), 'sent' => false]
        );
    }
}
