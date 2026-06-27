<?php

namespace App\Jobs\Mission;

use App\Models\Mission;
use App\Services\Mission\MissionDiffusionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DiffuseMissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(private Mission $mission) {}

    public function handle(MissionDiffusionService $service): void
    {
        // Ne diffuser que si toujours publiée
        $this->mission->refresh();

        if ($this->mission->status !== Mission::STATUS_PUBLISHED) {
            return;
        }

        $service->diffuse($this->mission);

        // Planifier l'expansion si non pourvue après 48h
        ExpandMissionDiffusionJob::dispatch($this->mission)
            ->delay(now()->addHours(48))
            ->onQueue('notifications');
    }
}
