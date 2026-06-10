<?php

namespace App\Jobs\Mission;

use App\Services\Mission\MissionReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendMissionRemindersJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries   = 1;
    public int $timeout = 60;

    public function handle(MissionReminderService $service): void
    {
        $service->sendDue();
    }
}
