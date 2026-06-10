<?php

namespace App\Jobs\Mission;

use App\Models\MissionView;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class RecordMissionViewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public function __construct(
        private int  $missionId,
        private ?int $userId,
        private ?string $ipHash
    ) {}

    public function handle(): void
    {
        // Pour utilisateurs connectés : éviter le double comptage
        if ($this->userId) {
            MissionView::firstOrCreate(
                ['mission_id' => $this->missionId, 'user_id' => $this->userId],
                ['viewed_at'  => now()]
            );
        } else {
            // Pour visiteurs anonymes : compter par IP (moins strict)
            MissionView::firstOrCreate(
                ['mission_id' => $this->missionId, 'ip_hash' => $this->ipHash],
                ['viewed_at'  => now()]
            );
        }
        // Le trigger SQL s'occupe d'incrémenter views_count
    }
}
