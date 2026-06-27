<?php

namespace App\Services\Moderation\Providers;

use App\Services\Moderation\Contracts\AIModerationInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIModerationProvider implements AIModerationInterface
{
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.moderation_model', 'gpt-4o-mini');
    }

    public function analyzeText(string $content): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/chat/completions', [
            'model' => $this->model,
            'max_tokens' => 300,
            'temperature' => 0.1,
            'messages' => [
                ['role' => 'system', 'content' => $this->getSystemPrompt()],
                ['role' => 'user', 'content' => $content],
            ],
            'response_format' => ['type' => 'json_object'],
        ]);

        if (!$response->successful()) {
            Log::error('OpenAI API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return $this->getDefaultErrorResponse();
        }

        $data = $response->json();
        $raw = $data['choices'][0]['message']['content'] ?? '';
        return $this->parseResponse($raw);
    }

    public function analyzeImage(string $base64, string $mediaType = 'image/jpeg'): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/chat/completions', [
            'model' => $this->model,
            'max_tokens' => 300,
            'temperature' => 0.1,
            'messages' => [
                ['role' => 'system', 'content' => $this->getSystemPrompt()],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => 'data:' . $mediaType . ';base64,' . $base64,
                            ],
                        ],
                        ['type' => 'text', 'text' => 'Analyse cette image pour la modération.'],
                    ],
                ],
            ],
            'response_format' => ['type' => 'json_object'],
        ]);

        if (!$response->successful()) {
            Log::error('OpenAI API Image Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return $this->getDefaultErrorResponse();
        }

        $data = $response->json();
        $raw = $data['choices'][0]['message']['content'] ?? '';
        return $this->parseResponse($raw);
    }

    public function analyzeTextWithImage(string $text, string $base64, string $mediaType = 'image/jpeg'): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/chat/completions', [
            'model' => $this->model,
            'max_tokens' => 300,
            'temperature' => 0.1,
            'messages' => [
                ['role' => 'system', 'content' => $this->getSystemPrompt()],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => 'data:' . $mediaType . ';base64,' . $base64,
                            ],
                        ],
                        ['type' => 'text', 'text' => "Analyse ce contenu pour la modération:\n\n{$text}"],
                    ],
                ],
            ],
            'response_format' => ['type' => 'json_object'],
        ]);

        if (!$response->successful()) {
            Log::error('OpenAI API Text+Image Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return $this->getDefaultErrorResponse();
        }

        $data = $response->json();
        $raw = $data['choices'][0]['message']['content'] ?? '';
        return $this->parseResponse($raw);
    }

    public function getProviderName(): string
    {
        return 'openai';
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function getEstimatedCost(array $input): float
    {
        $textLength = strlen($input['text'] ?? '');
        $hasImage = isset($input['image']);

        $costPerToken = 0.000002;
        $estimatedTokens = ceil($textLength / 4);
        $cost = $estimatedTokens * $costPerToken;

        if ($hasImage) {
            $cost += 0.001;
        }

        return round($cost, 6);
    }

    public function parseResponse(string $raw): array
    {
        $clean = trim(preg_replace('/```json|```/', '', $raw));
        $result = json_decode($clean, true);

        if (!is_array($result) || !isset($result['action'])) {
            return $this->getDefaultErrorResponse();
        }

        return [
            'safe' => $result['safe'] ?? false,
            'action' => $result['action'] ?? 'review',
            'reason' => $result['reason'] ?? 'Erreur de parsing',
            'toxicity' => (float)($result['toxicity'] ?? 0),
            'spam' => (float)($result['spam'] ?? 0),
            'hate' => (float)($result['hate'] ?? 0),
            'violence' => (float)($result['violence'] ?? 0),
        ];
    }

    private function getSystemPrompt(): string
    {
        return <<<PROMPT
Tu es un système de modération pour une plateforme communautaire agricole.
Analyse le contenu fourni.
Retourne uniquement du JSON sans texte additionnel ni balises Markdown :

{
  "safe": true|false,
  "toxicity": 0.0-1.0,
  "spam": 0.0-1.0,
  "hate": 0.0-1.0,
  "violence": 0.0-1.0,
  "action": "approve|review|reject",
  "reason": "explication courte en français"
}
PROMPT;
    }

    private function getDefaultErrorResponse(): array
    {
        return [
            'safe' => false,
            'action' => 'review',
            'reason' => 'Erreur API OpenAI',
            'toxicity' => 0,
            'spam' => 0,
            'hate' => 0,
            'violence' => 0,
        ];
    }
}
