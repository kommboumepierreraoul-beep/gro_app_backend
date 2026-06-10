<?php
// app/Http/Controllers/Api/AI/ChatController.php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\ChatRequest;
use App\Services\AI\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gère les endpoints de conversation IA.
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService
    ) {}

    /**
     * POST /api/ai/chat
     * Réponse complète (non-streaming).
     */
    public function sendMessage(ChatRequest $request): JsonResponse
    {
        $result = $this->conversationService->sendMessage(
            userId:      $request->user()->id,
            userMessage: $request->validated('message'),
            sessionId:   $request->validated('session_id'),
        );

        if (! $result['success']) {
            return response()->json(
                ['error' => $result['error']],
                503
            );
        }

        return response()->json([
            'content'    => $result['content'],
            'session_id' => $result['session_id'],
            'usage'      => $result['usage'] ?? [],
        ]);
    }

    /**
     * GET /api/ai/chat/stream
     * Réponse streaming SSE.
     */
    public function stream(ChatRequest $request): StreamedResponse
    {
        $userId    = $request->user()->id;
        $message   = $request->validated('message');
        $sessionId = $request->validated('session_id');

        return new StreamedResponse(function () use ($userId, $message, $sessionId) {
            $this->conversationService->sendMessageStream(
                userId:      $userId,
                userMessage: $message,
                sessionId:   $sessionId,
                onChunk:     function (string $chunk) {
                    echo 'data: ' . json_encode(['content' => $chunk]) . "\n\n";
                    ob_flush();
                    flush();
                },
                onDone:      function (string $fullResponse) {
                    echo 'data: ' . json_encode(['done' => true]) . "\n\n";
                    ob_flush();
                    flush();
                }
            );
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }

    /**
     * GET /api/ai/conversations
     * Liste les conversations de l'utilisateur courant.
     */
    public function listConversations(Request $request): JsonResponse
    {
        $conversations = $this->conversationService->getUserConversations(
            userId:  $request->user()->id,
            perPage: (int) $request->query('per_page', 10),
        );

        return response()->json($conversations);
    }

    /**
     * POST /api/ai/conversations
     * Démarre une nouvelle conversation.
     */
    public function startConversation(Request $request): JsonResponse
    {
        $conversation = $this->conversationService->startNewConversation(
            $request->user()->id
        );

        return response()->json([
            'session_id' => $conversation->session_id,
            'id'         => $conversation->id,
        ], 201);
    }
}