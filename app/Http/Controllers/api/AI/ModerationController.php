<?php
// app/Http/Controllers/Api/AI/ModerationController.php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\DeepSeekService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint de modération manuelle (usage admin/modérateur).
 */
class ModerationController extends Controller
{
    public function __construct(
        private readonly DeepSeekService $deepSeek
    ) {}

    /**
     * POST /api/ai/moderate
     * Analyse un contenu et retourne le rapport de modération.
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:10000'],
        ]);

        $result = $this->deepSeek->moderateContent($request->input('content'));

        return response()->json($result);
    }
}
