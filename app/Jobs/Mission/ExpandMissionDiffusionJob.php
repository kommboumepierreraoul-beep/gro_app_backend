<?php

namespace App\Jobs\Mission;

use App\Models\Mission;
use App\Services\Mission\MissionDiffusionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpandMissionDiffusionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 300; // 5min pour les grandes plateformes

    public function __construct(private Mission $mission) {}

    public function handle(MissionDiffusionService $service): void
    {
        $this->mission->refresh();
        $service->expand($this->mission);
    }
}
