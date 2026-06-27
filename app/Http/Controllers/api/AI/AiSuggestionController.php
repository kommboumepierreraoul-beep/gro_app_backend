<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\DeepSeekService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AiSuggestionController extends Controller
{
    public function __construct(private readonly DeepSeekService $ai) {}

    // ── Tags ───────────────────────────────────────────────────────────────────

    public function tags(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string|min:10|max:5000',
                'max' => 'sometimes|integer|min:1|max:10',
            ]);

            $max = $validated['max'] ?? 5;
            $cacheKey = 'ai_tags_' . md5($validated['content'] . '_max_' . $max);

            $tags = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($validated, $max) {
                return $this->ai->generateTags(
                    $validated['content'],
                    $max
                );
            });

            return response()->json(['tags' => $tags]);
        } catch (\Exception $e) {
            Log::error('Tags generation error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Erreur lors de la génération des tags',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ── Résumé ─────────────────────────────────────────────────────────────────

    public function summarize(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string|min:50|max:20000',
                'language' => 'sometimes|string|in:fr,en,es,de',
            ]);

            $language = $validated['language'] ?? 'fr';
            $cacheKey = 'ai_summary_' . md5($validated['content'] . '_lang_' . $language);

            $summary = Cache::remember($cacheKey, now()->addMinutes(60), function () use ($validated, $language) {
                return $this->ai->summarize(
                    $validated['content'],
                    $language
                );
            });

            return response()->json(['summary' => $summary]);
        } catch (\Exception $e) {
            Log::error('Summarize error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Erreur lors du résumé',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ── Amélioration ───────────────────────────────────────────────────────────

    public function improve(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string|min:10|max:10000',
                'language' => 'sometimes|string|in:fr,en,es,de',
            ]);

            $language = $validated['language'] ?? 'fr';

            $improved = $this->ai->improvePost(
                $validated['content'],
                $language
            );

            return response()->json(['improved' => $improved]);
        } catch (\Exception $e) {
            Log::error('Improve error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Erreur lors de l\'amélioration du texte',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
