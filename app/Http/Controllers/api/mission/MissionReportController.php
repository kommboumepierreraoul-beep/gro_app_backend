<?php

namespace App\Http\Controllers\Api\Mission;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\MissionReport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MissionReportController extends Controller
{
    /**
     * POST /api/missions/{ulid}/report
     * Body: { reason: string, details?: string }
     */
    public function store(Request $request, string $ulid): JsonResponse
    {
        $data = $request->validate([
            'reason'  => 'required|in:spam,inappropriate,scam,duplicate,misleading,other',
            'details' => 'nullable|string|max:1000',
        ]);

        $mission = Mission::where('ulid', $ulid)->firstOrFail();

        abort_if(
            $mission->isOwnedBy($request->user()),
            422,
            'Vous ne pouvez pas signaler votre propre mission.'
        );

        $existing = MissionReport::where('mission_id', $mission->id)
            ->where('reporter_id', $request->user()->id)
            ->first();

        abort_if($existing, 422, 'Vous avez déjà signalé cette mission.');

        $report = MissionReport::create([
            'mission_id'  => $mission->id,
            'reporter_id' => $request->user()->id,
            'reason'      => $data['reason'],
            'details'     => $data['details'] ?? null,
            'status'      => MissionReport::STATUS_PENDING,
        ]);

        // Auto-suspension si seuil de signalements atteint (modération légère)
        $reportsCount = MissionReport::where('mission_id', $mission->id)
            ->where('status', MissionReport::STATUS_PENDING)
            ->count();

        if ($reportsCount >= 5 && $mission->status === Mission::STATUS_PUBLISHED) {
            $mission->update(['status' => Mission::STATUS_SUSPENDED]);
        }

        return response()->json([
            'message' => 'Signalement envoyé. L\'équipe AgriPulse va examiner cette mission.',
            'data'    => $report,
        ], 201);
    }

    /**
     * GET /api/missions/{ulid}/report — vérifier si l'utilisateur a déjà signalé
     */
    public function check(Request $request, string $ulid): JsonResponse
    {
        $mission = Mission::where('ulid', $ulid)->firstOrFail();

        $report = MissionReport::where('mission_id', $mission->id)
            ->where('reporter_id', $request->user()->id)
            ->first();

        return response()->json(['reported' => (bool) $report, 'data' => $report]);
    }
}
