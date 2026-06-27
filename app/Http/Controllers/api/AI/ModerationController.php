<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\ModerationRequest;
use App\Http\Requests\AI\RemoderationRequest;
use App\Models\ModerationLog;
use App\Services\AI\DeepSeekService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ModerationController extends Controller
{
    public function __construct(private DeepSeekService $ai) {}

    public function check(ModerationRequest $request): JsonResponse
    {
        try {
            $result = $this->ai->moderate($request->input('content'));

            return response()->json([
                'flagged' => $result['flagged'],
                'confidence' => $result['confidence'],
                'reasons' => $result['reasons'],
                'requires_review' => $result['requires_review'] ?? ($result['confidence'] > 0.5),
            ]);
        } catch (\Exception $e) {
            Log::error('Moderation check error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Erreur lors de la modération',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function logs(Request $request): JsonResponse
    {
        try {
            $logs = ModerationLog::with('moderatable')
                ->when($request->get('flagged'), fn($q) => $q->where('flagged', true))
                ->when($request->get('status'), fn($q) => $q->where('status', $request->status))
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json($logs);
        } catch (\Exception $e) {
            Log::error('Moderation logs error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Erreur lors de la récupération des logs',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(ModerationLog $moderationLog): JsonResponse
    {
        try {
            return response()->json($moderationLog->load('moderatable'));
        } catch (\Exception $e) {
            Log::error('Moderation show error', [
                'message' => $e->getMessage(),
                'log_id' => $moderationLog->id ?? null,
            ]);

            return response()->json([
                'error' => 'Erreur lors de la récupération du log',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function recheck(RemoderationRequest $request): JsonResponse
    {
        try {
            $oldLog = ModerationLog::findOrFail($request->moderation_log_id);
            $moderatable = $oldLog->moderatable;

            if (!$moderatable) {
                return response()->json(['error' => 'Contenu non trouvé'], 404);
            }

            $content = $moderatable->content ?? $moderatable->body ?? $moderatable->message;

            if (!$content) {
                return response()->json(['error' => 'Aucun contenu à modérer'], 400);
            }

            $result = $this->ai->moderate($content);

            $newLog = ModerationLog::create([
                'moderatable_type' => get_class($moderatable),
                'moderatable_id' => $moderatable->id,
                'content_hash' => hash('sha256', $content),
                'flagged' => $result['flagged'],
                'confidence_score' => $result['confidence'],
                'reasons' => $result['reasons'],
                'raw_response' => $result['raw'] ?? null,
                'status' => 'completed',
                'model_used' => $result['model'] ?? config('ai.model'),
                'processing_time_ms' => 0,
            ]);

            // Si flagged, cacher automatiquement
            if ($result['flagged'] && $result['confidence'] > 0.7) {
                if (method_exists($moderatable, 'update')) {
                    $moderatable->update(['is_hidden' => true]);
                }
            }

            return response()->json([
                'old_moderation' => $oldLog,
                'new_moderation' => $newLog,
                'changed' => $oldLog->flagged !== $newLog->flagged,
            ]);
        } catch (\Exception $e) {
            Log::error('Recheck error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Erreur lors de la re-modération',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function batch(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'contents' => 'required|array|max:100',
                'contents.*.id' => 'required|string',
                'contents.*.content' => 'required|string|min:10',
                'contents.*.type' => 'required|string',
            ]);

            $results = [];

            foreach ($request->contents as $item) {
                $result = $this->ai->moderate($item['content']);
                $results[] = [
                    'id' => $item['id'],
                    'type' => $item['type'],
                    'flagged' => $result['flagged'],
                    'confidence' => $result['confidence'],
                    'reasons' => $result['reasons'],
                    'requires_review' => $result['requires_review'] ?? false,
                ];
            }

            return response()->json([
                'total' => count($results),
                'flagged_count' => collect($results)->where('flagged', true)->count(),
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Batch moderation error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Erreur lors de la modération par lots',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
