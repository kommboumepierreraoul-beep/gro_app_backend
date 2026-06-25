<?php

namespace App\Services\Moderation\Providers;

use App\Services\Moderation\Contracts\AIModerationInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqModerationProvider implements AIModerationInterface
{
    private ?string $apiKey = null;
    private string $model;
    private string $baseUrl = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        // ✅ Correction : utiliser la bonne configuration
        $this->apiKey = config('services.groq.api_key');
        $this->model = config('services.groq.model', 'llama-3.3-70b-versatile');
        
        // ✅ Fallback : essayer directement depuis .env si config('services.groq') est vide
        if (empty($this->apiKey)) {
            $this->apiKey = env('GROQ_API_KEY');
        }
    }

    public function analyzeText(string $content): array
    {
        // ✅ Vérifier que la clé API est disponible
        if (empty($this->apiKey)) {
            Log::error('Groq API Key manquante');
            return $this->getDefaultErrorResponse('Clé API Groq non configurée');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->baseUrl, [
                'model' => $this->model,
                'temperature' => 0.1,
                'max_tokens' => 300,
                'response_format' => [
                    'type' => 'json_object'
                ],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('Groq API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->getDefaultErrorResponse('Erreur API Groq: ' . $response->status());
            }

            $raw = $response->json('choices.0.message.content', '');

            if (empty($raw)) {
                Log::error('Groq API Empty Response');
                return $this->getDefaultErrorResponse('Réponse vide de l\'API Groq');
            }

            return $this->parseResponse($raw);

        } catch (\Exception $e) {
            Log::error('Groq API Exception', [
                'error' => $e->getMessage(),
            ]);
            return $this->getDefaultErrorResponse('Exception: ' . $e->getMessage());
        }
    }

    public function analyzeImage(string $base64, string $mediaType = 'image/jpeg'): array
    {
        // ❌ Groq ne supporte pas les images pour l'instant
        Log::info('Groq does not support image analysis');
        return $this->getDefaultErrorResponse('Groq ne supporte pas les images');
    }

    public function analyzeTextWithImage(
        string $text,
        string $base64,
        string $mediaType = 'image/jpeg'
    ): array {
        // ❌ Groq ne supporte pas les images pour l'instant
        Log::info('Groq does not support image analysis');
        return $this->getDefaultErrorResponse('Groq ne supporte pas les images');
    }

    public function getProviderName(): string
    {
        return 'groq';
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
        // Groq est gratuit
        return 0;
    }

    public function parseResponse(string $raw): array
    {
        // ✅ Nettoyer la réponse
        $clean = trim(preg_replace('/```json|```/', '', $raw));
        $result = json_decode($clean, true);

        if (!is_array($result) || !isset($result['action'])) {
            Log::warning('Groq Invalid JSON', [
                'raw' => substr($raw, 0, 500),
            ]);
            return $this->getDefaultErrorResponse('Erreur de parsing JSON');
        }

        // ✅ S'assurer que les scores sont bien des nombres
        return [
            'safe' => (bool)($result['safe'] ?? false),
            'action' => $result['action'] ?? 'review',
            'reason' => $result['reason'] ?? 'Contenu analysé',
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

Analyse le contenu fourni et retourne UNIQUEMENT un JSON valide, sans texte additionnel, sans balises Markdown.

Le JSON doit avoir cette structure exacte :
{
  "safe": true,
  "toxicity": 0.0,
  "spam": 0.0,
  "hate": 0.0,
  "violence": 0.0,
  "action": "approve",
  "reason": "Contenu acceptable"
}

Les scores doivent être entre 0 et 1.
L'action doit être "approve", "review" ou "reject".
PROMPT;
    }

    private function getDefaultErrorResponse(string $reason = 'Erreur API Groq'): array
    {
        return [
            'safe' => false,
            'action' => 'review',
            'reason' => $reason,
            'toxicity' => 0,
            'spam' => 0,
            'hate' => 0,
            'violence' => 0,
        ];
    }
}