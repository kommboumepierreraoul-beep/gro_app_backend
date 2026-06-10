<?php

namespace App\Jobs\Mission;

use App\Models\Mission;
use App\Models\MissionApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class NotifyApplicantsOfMissionUpdateJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public function __construct(private Mission $mission) {}

    public function handle(): void
    {
        $applicants = $this->mission->applications()
            ->whereIn('status', [MissionApplication::STATUS_PENDING, MissionApplication::STATUS_ACCEPTED])
            ->with('applicant:id,name')
            ->get()
            ->pluck('applicant');

        if ($applicants->isEmpty()) return;

        Notification::send($applicants, new \App\Notifications\Mission\MissionUpdatedNotification($this->mission));
    }
}
