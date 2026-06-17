<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\AiChatRequest;
use App\Models\AiConversation;
use App\Services\AI\ConversationService;
use App\Services\AI\DeepSeekService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatController extends Controller
{
    public function __construct(
        private readonly DeepSeekService $ai,
        private readonly ConversationService $conversations,
    ) {}

    public function __invoke(AiChatRequest $request): StreamedResponse|JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['error' => 'Non authentifié'], 401);
            }

            // Rate limiting
            $rateLimitKey = 'ai_chat_' . ($user->id ?? $request->ip());
            $maxAttempts = config('ai.rate_limit.max_attempts', 60);

            if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);
                return response()->json([
                    'error' => 'Trop de requêtes. Veuillez réessayer dans ' . $seconds . ' secondes.',
                    'retry_after' => $seconds,
                ], 429);
            }

            RateLimiter::hit($rateLimitKey, 60);

            // 1. Trouver ou créer la conversation
            $conversation = $this->conversations->findOrCreate(
                userId: $user->id,
                conversationId: $request->conversation_id,
                contextType: $request->input('context_type', 'general'),
                contextId: $request->input('context_id'),
            );

            // 2. Ajouter le contexte
            $contextData = $request->input('context_data');
            if (!$contextData && $request->input('context_type') && $request->input('context_id')) {
                $contextData = $conversation->getContextData();
            }

            // 3. Persister le message utilisateur
            $this->conversations->addMessage(
                conversation: $conversation,
                role: 'user',
                content: $request->message,
            );

            // 4. Construire le payload
            $systemPrompt = $request->input('system_prompt');
            if (!$systemPrompt) {
                $systemPrompt = $this->getSystemPromptForContext($request->input('context_type'));
            }

            $apiMessages = $this->conversations->buildApiMessages(
                $conversation,
                $systemPrompt,
                $contextData
            );

            // 5. Stream ou direct
            if ($request->boolean('stream')) {
                return $this->handleStream($conversation, $apiMessages);
            }

            return $this->handleDirect($conversation, $apiMessages);
        } catch (\Exception $e) {
            Log::error('AiChatController Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Une erreur est survenue lors du traitement de votre demande.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function handleStream(
        AiConversation $conversation,
        array $apiMessages
    ): StreamedResponse {
        return new StreamedResponse(function () use ($conversation, $apiMessages) {
            try {
                $fullResponse = '';

                // Envoyer l'ID de conversation
                echo "event: meta\ndata: " . json_encode([
                    'conversation_id' => $conversation->id
                ]) . "\n\n";
                flush();

                // Streamer la réponse
                $this->ai->streamChatOutput($apiMessages, function ($token) use (&$fullResponse) {
                    $fullResponse .= $token;
                });

                // Persister la réponse
                if (!empty($fullResponse)) {
                    $this->conversations->addMessage(
                        conversation: $conversation,
                        role: 'assistant',
                        content: $fullResponse,
                        tokens: $this->estimateTokens($fullResponse),
                        meta: ['stream' => true],
                    );
                }

                echo "event: done\ndata: {}\n\n";
                flush();
            } catch (\Exception $e) {
                Log::error('Stream error', [
                    'message' => $e->getMessage(),
                    'conversation_id' => $conversation->id,
                ]);

                echo "event: error\ndata: " . json_encode([
                    'error' => $e->getMessage()
                ]) . "\n\n";
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function handleDirect(
        AiConversation $conversation,
        array $apiMessages
    ): JsonResponse {
        try {
            $result = $this->ai->chat($apiMessages);

            if (!($result['success'] ?? false)) {
                return response()->json([
                    'error' => $result['error'] ?? 'Erreur inconnue',
                ], $result['code'] ?? 500);
            }

            // Persister la réponse assistant
            $message = $this->conversations->addMessage(
                conversation: $conversation,
                role: 'assistant',
                content: $result['content'],
                tokens: $result['tokens'] ?? 0,
                meta: [
                    'finish_reason' => $result['finish_reason'] ?? 'stop',
                    'model' => $result['model'] ?? config('ai.model'),
                ],
            );

            return response()->json([
                'message' => [
                    'id' => $message->id,
                    'role' => 'assistant',
                    'content' => $result['content'],
                    'tokens' => $result['tokens'] ?? 0,
                ],
                'conversation_id' => $conversation->id,
                'total_tokens_used' => $conversation->fresh()->total_tokens ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Direct chat error', [
                'message' => $e->getMessage(),
                'conversation_id' => $conversation->id,
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function estimateTokens(string $text): int
    {
        $wordCount = str_word_count($text, 0, 'àâäéèêëîïôöûüÿçÀÂÄÉÈÊËÎÏÔÖÛÜŸÇ');
        $charCount = mb_strlen($text);
        return max(1, (int) ceil(max($charCount / 4, $wordCount * 0.75)));
    }

    private function getSystemPromptForContext(?string $contextType): string
    {
        $prompts = [
            'post' => 'ai.system_prompts.context',
            'mission' => 'ai.system_prompts.context',
            'comment' => 'ai.system_prompts.context',
            'general' => 'ai.system_prompts.chat',
        ];

        $key = $prompts[$contextType] ?? 'ai.system_prompts.chat';
        return config($key, config('ai.system_prompts.chat'));
    }
}
