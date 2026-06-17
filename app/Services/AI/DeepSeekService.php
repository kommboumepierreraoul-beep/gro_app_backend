<?php

namespace App\Services\AI;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeepSeekService
{
    private PendingRequest $client;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->model = config('ai.model', 'llama-3.3-70b-versatile');
        $this->baseUrl = rtrim(config('ai.base_url', 'https://api.groq.com/openai/v1'), '/');

        Log::info('AI Service initialized', [
            'provider' => config('ai.provider', 'groq'),
            'base_url' => $this->baseUrl,
            'model' => $this->model,
            'api_key_present' => !empty(config('ai.api_key')),
        ]);

        $this->client = Http::acceptJson()
            ->withToken(config('ai.api_key'))
            ->baseUrl($this->baseUrl)
            ->timeout(config('ai.timeout', 60))
            ->retry(2, 500);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. CHAT DIRECT
    // ──────────────────────────────────────────────────────────────────────────

    public function chat(array $messages, array $options = []): array
    {
        try {
            Log::info('AI Chat request', [
                'model' => $this->model,
                'messages_count' => count($messages),
            ]);

            $response = $this->client->post('/chat/completions', array_merge([
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1024,
            ], $options));

            if ($response->failed()) {
                Log::error('AI Request Failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => $response->json('error.message') ?? $response->body(),
                    'code' => $response->status(),
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'content' => $data['choices'][0]['message']['content'] ?? '',
                'tokens' => $data['usage']['total_tokens'] ?? 0,
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'stop',
                'model' => $data['model'] ?? $this->model,
            ];
        } catch (\Throwable $e) {
            Log::error('AI Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. CHAT AVEC SYSTEM PROMPT
    // ──────────────────────────────────────────────────────────────────────────

    public function chatWithSystem(array $messages, ?string $systemPrompt = null, array $options = []): array
    {
        $fullMessages = [];

        if ($systemPrompt) {
            $fullMessages[] = ['role' => 'system', 'content' => $systemPrompt];
        } elseif (config('ai.system_prompts.chat')) {
            $fullMessages[] = ['role' => 'system', 'content' => config('ai.system_prompts.chat')];
        } else {
            $fullMessages[] = ['role' => 'system', 'content' => $this->getDefaultSystemPrompt()];
        }

        $fullMessages = array_merge($fullMessages, $messages);

        return $this->chat($fullMessages, $options);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. STREAMING SSE (CORRIGÉ - Version qui fonctionne)
    // ──────────────────────────────────────────────────────────────────────────

    public function streamChatOutput(array $messages, callable $callback): void
    {
        try {
            Log::info('AI Stream request', [
                'model' => $this->model,
                'messages_count' => count($messages),
            ]);

            $response = $this->client
                ->withOptions(['stream' => true])
                ->post('/chat/completions', [
                    'model' => $this->model,
                    'messages' => $messages,
                    'max_tokens' => 1024,
                    'temperature' => 0.7,
                    'stream' => true,
                ]);

            if ($response->failed()) {
                $errorBody = $response->body();
                Log::error('AI Stream Request Failed', [
                    'status' => $response->status(),
                    'body' => $errorBody,
                ]);

                echo "event: error\ndata: " . json_encode([
                    'error' => $response->json('error.message') ?? $errorBody,
                    'status' => $response->status(),
                ]) . "\n\n";
                flush();
                return;
            }

            $body = $response->toPsrResponse()->getBody();
            $tokenCount = 0;

            while (!$body->eof()) {
                if (connection_aborted()) {
                    Log::warning('Stream aborted by client');
                    break;
                }

                $chunk = $body->read(1024);

                if (empty($chunk)) {
                    continue;
                }

                $lines = explode("\n", $chunk);

                foreach ($lines as $line) {
                    $line = trim($line);

                    if (empty($line)) {
                        continue;
                    }

                    if (!str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $payload = trim(substr($line, 5));

                    if ($payload === '[DONE]') {
                        Log::info('Stream DONE received', ['tokens_sent' => $tokenCount]);
                        echo "event: done\ndata: {}\n\n";
                        flush();
                        return;
                    }

                    $decoded = json_decode($payload, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::warning('Invalid JSON in stream', [
                            'payload' => substr($payload, 0, 200),
                            'error' => json_last_error_msg(),
                        ]);
                        continue;
                    }

                    $token = $decoded['choices'][0]['delta']['content'] ?? null;

                    if ($token !== null && $token !== '') {
                        $tokenCount++;
                        echo 'data: ' . json_encode(['token' => $token]) . "\n\n";
                        flush();
                        $callback($token);
                    }
                }
            }

            Log::info('Stream ended', ['total_tokens' => $tokenCount]);
        } catch (\Throwable $e) {
            Log::error('AI Stream Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            echo "event: error\ndata: " . json_encode([
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]) . "\n\n";
            flush();
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. STREAMING AVEC SYSTEM PROMPT
    // ──────────────────────────────────────────────────────────────────────────

    public function streamChatWithSystem(array $messages, ?string $systemPrompt = null, callable $callback): void
    {
        $fullMessages = [];

        if ($systemPrompt) {
            $fullMessages[] = ['role' => 'system', 'content' => $systemPrompt];
        } elseif (config('ai.system_prompts.chat')) {
            $fullMessages[] = ['role' => 'system', 'content' => config('ai.system_prompts.chat')];
        } else {
            $fullMessages[] = ['role' => 'system', 'content' => $this->getDefaultSystemPrompt()];
        }

        $fullMessages = array_merge($fullMessages, $messages);

        $this->streamChatOutput($fullMessages, $callback);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. MODÉRATION
    // ──────────────────────────────────────────────────────────────────────────

    public function moderate(string $content): array
    {
        $sanitizedContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        $prompt = <<<PROMPT
Analyse ce contenu pour détecter: spam, discours haineux, violence, contenu adulte, désinformation.

<content_to_analyze>
{$sanitizedContent}
</content_to_analyze>

Réponds UNIQUEMENT en JSON valide avec le format:
{"flagged": bool, "confidence": float, "reasons": ["raison1", ...]}
PROMPT;

        $result = $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ], ['temperature' => 0.1, 'max_tokens' => 256]);

        if (!($result['success'] ?? false)) {
            Log::error('Moderation API failed', ['error' => $result['error'] ?? 'Unknown']);
            return [
                'flagged' => false,
                'confidence' => 0.0,
                'reasons' => ['API_ERROR'],
                'requires_review' => true,
                'raw' => $result['error'] ?? null,
                'model' => $this->model,
            ];
        }

        $json = json_decode($result['content'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Moderation JSON parse failed', [
                'content_preview' => substr($result['content'], 0, 200),
            ]);

            return [
                'flagged' => false,
                'confidence' => 0.0,
                'reasons' => ['PARSE_ERROR'],
                'requires_review' => true,
                'raw' => $result['content'],
                'model' => $result['model'] ?? $this->model,
            ];
        }

        return [
            'flagged' => (bool) ($json['flagged'] ?? false),
            'confidence' => (float) ($json['confidence'] ?? 0.0),
            'reasons' => (array) ($json['reasons'] ?? []),
            'requires_review' => ($json['confidence'] ?? 0) > 0.5,
            'raw' => $result['content'],
            'model' => $result['model'] ?? $this->model,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. TAGS
    // ──────────────────────────────────────────────────────────────────────────

    public function generateTags(string $content, int $max = 5): array
    {
        $sanitizedContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        $prompt = <<<PROMPT
Génère exactement {$max} tags pertinents pour ce contenu.

<content_to_analyze>
{$sanitizedContent}
</content_to_analyze>

Réponds UNIQUEMENT en JSON: {"tags": ["tag1", "tag2", ...]}
PROMPT;

        $result = $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ], ['temperature' => 0.3, 'max_tokens' => 128]);

        if (!($result['success'] ?? false)) {
            Log::error('Tag generation failed', ['error' => $result['error'] ?? 'Unknown']);
            return [];
        }

        $json = json_decode($result['content'], true);
        return $json['tags'] ?? [];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. RÉSUMÉ
    // ──────────────────────────────────────────────────────────────────────────

    public function summarize(string $content, string $language = 'fr'): string
    {
        $sanitizedContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        $systemPrompt = config(
            'ai.system_prompts.summarize',
            "Tu es un assistant qui résume des discussions. Réponds en {$language}. Sois concis (3-5 phrases max)."
        );

        $result = $this->chatWithSystem(
            [
                ['role' => 'user', 'content' => "Résume cette discussion:\n\n{$sanitizedContent}"],
            ],
            $systemPrompt,
            ['temperature' => 0.4, 'max_tokens' => 256]
        );

        return $result['content'] ?? '';
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. AMÉLIORATION DE TEXTE
    // ──────────────────────────────────────────────────────────────────────────

    public function improvePost(string $content, string $language = 'fr'): string
    {
        $systemPrompt = config(
            'ai.system_prompts.improve',
            "Améliore le texte suivant en corrigeant les fautes et en le rendant plus clair. Réponds en {$language}."
        );

        $sanitizedContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        $result = $this->chatWithSystem(
            [
                ['role' => 'user', 'content' => $sanitizedContent],
            ],
            $systemPrompt,
            ['temperature' => 0.5, 'max_tokens' => 512]
        );

        return $result['content'] ?? '';
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9. SYSTEM PROMPT PAR DÉFAUT
    // ──────────────────────────────────────────────────────────────────────────

    private function getDefaultSystemPrompt(): string
    {
        return config(
            'ai.system_prompts.chat',
            "Tu es AgriPulse AI, l'assistant intelligent de la plateforme communautaire agricole AgriPulse.

Ta mission est d'aider les agriculteurs, éleveurs, techniciens agricoles et membres de la communauté.

Règles :
1. Utilise les informations du contexte pour répondre
2. Sois courtois, professionnel et empathique
3. Si tu ne sais pas, dis-le honnêtement
4. Ne donne pas de conseils médicaux vétérinaires sans mentionner la consultation d'un professionnel
5. Structure ta réponse de manière claire et organisée"
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10. HEALTH CHECK
    // ──────────────────────────────────────────────────────────────────────────

    public function healthCheck(): array
    {
        try {
            Log::info('AI Health Check', [
                'base_url' => $this->baseUrl,
            ]);

            $response = $this->client->get('/models');

            return [
                'status' => 'ok',
                'api_connected' => $response->successful(),
                'model' => $this->model,
                'base_url' => $this->baseUrl,
                'response_code' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'model' => $this->model,
                'base_url' => $this->baseUrl,
            ];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 11. ESTIMATION DE TOKENS
    // ──────────────────────────────────────────────────────────────────────────

    public function estimateTokens(string $text): int
    {
        $wordCount = str_word_count($text, 0, 'àâäéèêëîïôöûüÿçÀÂÄÉÈÊËÎÏÔÖÛÜŸÇ');
        $charCount = mb_strlen($text);
        return max(1, (int) ceil(max($charCount / 4, $wordCount * 0.75)));
    }
}
