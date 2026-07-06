<?php

namespace App\Services\AI;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
                'temperature' => (float) config('ai.temperature', 0.35),
                'max_tokens' => 1024,
            ], $options));

            if ($response->failed()) {
                $errorMessage = $response->json('error.message') ?? $response->json('error') ?? $response->body();

                if ($response->status() === 429) {
                    $errorMessage = 'Quota ou limite de requêtes IA atteint.';
                } elseif ($response->status() === 401) {
                    $errorMessage = 'Clé API IA invalide ou non autorisée.';
                }

                Log::error('AI Request Failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'code' => $response->status(),
                ];
            }

            $data = $response->json();

            $content = $this->normalizeGeneratedContent($data['choices'][0]['message']['content'] ?? '');

            return [
                'success' => true,
                'content' => $content,
                'tokens' => $data['usage']['total_tokens'] ?? 0,
                'usage' => $data['usage'] ?? ['total_tokens' => 0],
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

    public function chatWithSystem(array $messages, ?string $systemPrompt = null, array $options = []): array
    {
        $fullMessages = [[
            'role' => 'system',
            'content' => $this->withQualityPrompt(
                $systemPrompt ?: config('ai.system_prompts.chat') ?: $this->getDefaultSystemPrompt()
            ),
        ]];

        return $this->chat(array_merge($fullMessages, $messages), $options);
    }

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
                    'temperature' => (float) config('ai.temperature', 0.35),
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
            $buffer = '';
            $tokenCount = 0;

            while (!$body->eof()) {
                if (connection_aborted()) {
                    Log::warning('Stream aborted by client');
                    break;
                }

                $chunk = $body->read(1024);

                if ($chunk === '') {
                    continue;
                }

                $buffer .= $chunk;
                $lines = explode("\n", $buffer);
                $buffer = array_pop($lines) ?? '';

                foreach ($lines as $line) {
                    $line = trim($line);

                    if ($line === '' || !str_starts_with($line, 'data:')) {
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

    public function streamChatWithSystem(array $messages, ?string $systemPrompt = null, callable $callback): void
    {
        $fullMessages = [[
            'role' => 'system',
            'content' => $this->withQualityPrompt(
                $systemPrompt ?: config('ai.system_prompts.chat') ?: $this->getDefaultSystemPrompt()
            ),
        ]];

        $this->streamChatOutput(array_merge($fullMessages, $messages), $callback);
    }

    public function moderate(string $content): array
    {
        $sanitizedContent = $this->plainTextForPrompt($content);

        $prompt = <<<PROMPT
Analyse ce contenu pour détecter : spam, discours haineux, violence, contenu adulte, arnaque ou désinformation.

<content_to_analyze>
{$sanitizedContent}
</content_to_analyze>

Réponds UNIQUEMENT en JSON valide avec le format :
{"flagged": bool, "confidence": float, "reasons": ["raison1", ...]}
PROMPT;

        $result = $this->chatWithSystem([
            ['role' => 'user', 'content' => $prompt],
        ], config('ai.system_prompts.moderate'), ['temperature' => 0.1, 'max_tokens' => 256]);

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

    public function moderateContent(string $content): array
    {
        $result = $this->moderate($content);

        return [
            'is_safe' => !($result['flagged'] ?? false),
            'score' => (float) ($result['confidence'] ?? 0.0),
            'categories' => (array) ($result['reasons'] ?? []),
            'reason' => implode(', ', (array) ($result['reasons'] ?? [])),
            'raw' => $result,
        ];
    }

    public function generateTags(string $content, int $max = 5): array
    {
        $sanitizedContent = $this->plainTextForPrompt($content);

        $prompt = <<<PROMPT
Génère exactement {$max} tags pertinents pour ce contenu.

<content_to_analyze>
{$sanitizedContent}
</content_to_analyze>

Réponds UNIQUEMENT en JSON valide : {"tags": ["tag1", "tag2", ...]}
PROMPT;

        $result = $this->chatWithSystem([
            ['role' => 'user', 'content' => $prompt],
        ], null, ['temperature' => 0.2, 'max_tokens' => 128]);

        if (!($result['success'] ?? false)) {
            Log::error('Tag generation failed', ['error' => $result['error'] ?? 'Unknown']);
            return [];
        }

        $json = json_decode($result['content'], true);
        return is_array($json['tags'] ?? null) ? $json['tags'] : [];
    }

    public function summarize(string $content, string $language = 'fr'): string
    {
        $sanitizedContent = $this->plainTextForPrompt($content);
        $systemPrompt = config(
            'ai.system_prompts.summarize',
            "Tu es un assistant qui résume des discussions. Réponds en {$language}. Sois concis (3-5 phrases maximum)."
        );

        $result = $this->chatWithSystem(
            [
                ['role' => 'user', 'content' => "Résume cette discussion :\n\n{$sanitizedContent}"],
            ],
            "{$systemPrompt}\nRéponds en {$language}.",
            ['temperature' => 0.2, 'max_tokens' => 256]
        );

        return $result['content'] ?? '';
    }

    public function summarizeThread(array $messages, string $language = 'fr'): string
    {
        if (count($messages) < 2) {
            return 'Pas assez de messages pour générer un résumé.';
        }

        $content = collect($messages)
            ->map(fn (array $message) => ($message['author'] ?? 'Utilisateur') . ': ' . ($message['content'] ?? ''))
            ->implode("\n");

        return $this->summarize($content, $language);
    }

    public function improvePost(string $content, string $language = 'fr'): string
    {
        $systemPrompt = config(
            'ai.system_prompts.improve',
            "Améliore le texte suivant en corrigeant les fautes et en le rendant plus clair. Réponds en {$language}."
        );

        $sanitizedContent = $this->plainTextForPrompt($content);

        $result = $this->chatWithSystem(
            [
                ['role' => 'user', 'content' => $sanitizedContent],
            ],
            "{$systemPrompt}\nRéponds en {$language}.",
            ['temperature' => 0.2, 'max_tokens' => 512]
        );

        return $result['content'] ?? '';
    }

    private function getDefaultSystemPrompt(): string
    {
        return config('ai.system_prompts.chat', "Tu es AgriPulse IA, l'assistant intelligent de la plateforme agricole AgriPulse.");
    }

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

    public function estimateTokens(string $text): int
    {
        $wordCount = str_word_count($text, 0, 'àâäéèêëîïôöûüÿçÀÂÄÉÈÊËÎÏÔÖÛÜŸÇ');
        $charCount = mb_strlen($text);
        return max(1, (int) ceil(max($charCount / 4, $wordCount * 0.75)));
    }

    private function withQualityPrompt(string $systemPrompt): string
    {
        $qualityPrompt = (string) config('ai.system_prompts.quality', '');

        if ($qualityPrompt === '' || str_contains($systemPrompt, $qualityPrompt)) {
            return $systemPrompt;
        }

        return trim($systemPrompt) . "\n\n" . trim($qualityPrompt);
    }

    private function plainTextForPrompt(string $content): string
    {
        return trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function normalizeGeneratedContent(string $content): string
    {
        $replacements = [
            'Ã©' => 'é',
            'Ã¨' => 'è',
            'Ãª' => 'ê',
            'Ã«' => 'ë',
            'Ã ' => 'à',
            'Ã¢' => 'â',
            'Ã¤' => 'ä',
            'Ã®' => 'î',
            'Ã¯' => 'ï',
            'Ã´' => 'ô',
            'Ã¶' => 'ö',
            'Ã¹' => 'ù',
            'Ã»' => 'û',
            'Ã¼' => 'ü',
            'Ã§' => 'ç',
            'Ã‰' => 'É',
            'Ã€' => 'À',
            'Ã‡' => 'Ç',
            'â€™' => "'",
            'â€œ' => '"',
            'â€' => '"',
            'â€“' => '-',
            'â€”' => '-',
            'â€¦' => '...',
            'Â ' => ' ',
            'Â·' => '-',
        ];

        return trim(strtr($content, $replacements));
    }
}
