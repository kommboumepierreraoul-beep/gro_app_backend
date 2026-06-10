<?php
// app/Http/Controllers/Api/AI/SuggestionController.php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\DeepSeekService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints IA de suggestion / amélioration de contenu.
 */
class SuggestionController extends Controller
{
    public function __construct(
        private readonly DeepSeekService $deepSeek
    ) {}

    /**
     * POST /api/ai/tags
     * Génère des tags pour un post.
     */
    public function generateTags(Request $request): JsonResponse
    {
        $request->validate([
            'content'   => ['required', 'string', 'min:20', 'max:5000'],
            'max_tags'  => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        $tags = $this->deepSeek->generateTags(
            postContent: $request->input('content'),
            maxTags: $request->integer('max_tags', 5),
        );

        return response()->json(['tags' => $tags]);
    }

    /**
     * POST /api/ai/summarize
     * Résume un fil de discussion.
     */
    public function summarizeThread(Request $request): JsonResponse
    {
        $request->validate([
            'messages'           => ['required', 'array', 'min:2', 'max:100'],
            'messages.*.author'  => ['required', 'string', 'max:100'],
            'messages.*.content' => ['required', 'string', 'max:2000'],
        ]);

        $summary = $this->deepSeek->summarizeThread($request->input('messages'));

        return response()->json(['summary' => $summary]);
    }

    /**
     * POST /api/ai/improve-post
     * Améliore la rédaction d'un post.
     */
    public function improvePost(Request $request): JsonResponse
    {
        $request->validate([
            'content' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $improved = $this->deepSeek->improvePost($request->input('content'));

        return response()->json([
            'original' => $request->input('content'),
            'improved' => $improved,
        ]);
    }
}
