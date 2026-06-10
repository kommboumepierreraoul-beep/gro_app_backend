<?php

namespace App\Services\AI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service principal d'interaction avec l'API DeepSeek.
 *
 * Responsabilités :
 *  - Appels chat (réponse complète ou streaming SSE)
 *  - Modération de contenu
 *  - Génération de tags
 *  - Résumé de discussions
 *  - Amélioration de posts
 */
class DeepSeekService
{
    private Client $client;

    /**
     * Durée de cache pour la modération (secondes).
     */
    private const MODERATION_CACHE_TTL = 3600;

    /**
     * Durée de cache pour les tags (secondes).
     */
    private const TAGS_CACHE_TTL = 1800;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => config('services.deepseek.base_url', 'https://api.deepseek.com/v1'),
            'timeout'  => 60,
            'connect_timeout' => 10,
            'headers'  => [
                'Authorization' => 'Bearer ' . config('services.deepseek.api_key'),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // CHAT
    // ──────────────────────────────────────────────────────────

    /**
     * Envoie une conversation et retourne la réponse complète.
     *
     * @param  array  $messages  Tableau de messages [['role'=>..., 'content'=>...]]
     * @param  array  $options   Options optionnelles (model, temperature, max_tokens…)
     * @return array{success: bool, content: string, usage: array, model: string, error?: string, code?: int}
     */
    public function chat(array $messages, array $options = []): array
    {
        $payload = $this->buildPayload($messages, $options, stream: false);

        try {
            $response = $this->client->post('/chat/completions', ['json' => $payload]);
            $data     = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'content' => $data['choices'][0]['message']['content'] ?? '',
                'usage'   => $data['usage'] ?? [],
                'model'   => $data['model'] ?? $payload['model'],
            ];
        } catch (\Throwable $e) {
            return $this->handleException($e, 'chat');
        }
    }

    /**
     * Streaming SSE — appelle $onChunk pour chaque token reçu.
     *
     * @param  array     $messages
     * @param  callable  $onChunk   Reçoit string $token
     * @param  array     $options
     */
    public function chatStream(array $messages, callable $onChunk, array $options = []): void
    {
        $payload = $this->buildPayload($messages, $options, stream: true);

        try {
            $response = $this->client->post('/chat/completions', [
                'json'   => $payload,
                'stream' => true,
            ]);

            $body = $response->getBody();

            while (! $body->eof()) {
                $line = $this->readStreamLine($body);

                if (! str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = substr($line, 6);

                if ($data === '[DONE]') {
                    break;
                }

                $chunk   = json_decode($data, true);
                $content = $chunk['choices'][0]['delta']['content'] ?? '';

                if ($content !== '') {
                    $onChunk($content);
                }
            }
        } catch (\Throwable $e) {
            Log::error('DeepSeek stream error', ['message' => $e->getMessage()]);
            $onChunk('[ERREUR: Le service IA est temporairement indisponible]');
        }
    }

    // ──────────────────────────────────────────────────────────
    // MODÉRATION
    // ──────────────────────────────────────────────────────────

    /**
     * Analyse un contenu textuel et retourne un rapport de modération.
     *
     * Résultat mis en cache 1h (même contenu = même résultat).
     *
     * @return array{is_safe: bool, score: float, categories: string[], reason: string}
     */
    public function moderateContent(string $content): array
    {
        $cacheKey = 'deepseek_moderation_' . md5($content);

        return Cache::remember($cacheKey, self::MODERATION_CACHE_TTL, function () use ($content) {
            $result = $this->chat(
                messages: [
                    [
                        'role'    => 'system',
                        'content' => <<<'PROMPT'
Tu es un système de modération pour une communauté en ligne.
Analyse le texte ci-dessous et réponds UNIQUEMENT avec un objet JSON valide, sans markdown ni backticks.
Format attendu :
{
  "is_safe": true|false,
  "score": 0.0 à 1.0 (probabilité de contenu problématique),
  "categories": [] (tableau des catégories détectées parmi : hate_speech, harassment, spam, misinformation, adult_content, violence),
  "reason": "explication courte en français"
}
PROMPT,
                    ],
                    [
                        'role'    => 'user',
                        'content' => "Contenu à analyser :\n\n" . mb_substr($content, 0, 3000),
                    ],
                ],
                options: [
                    'model'       => config('services.deepseek.default_model', 'deepseek-chat'),
                    'temperature' => 0.1,
                    'max_tokens'  => 300,
                ]
            );

            if (! $result['success']) {
                // En cas d'erreur API, on laisse passer (fail open) pour ne pas bloquer la publication
                Log::warning('DeepSeek moderation failed, defaulting to safe', ['error' => $result['error'] ?? '']);
                return $this->defaultSafeResult();
            }

            // Nettoyage des éventuels blocs markdown retournés par le modèle
            $json   = preg_replace('/```(?:json)?\n?|\n?```/', '', trim($result['content']));
            $parsed = json_decode($json, true);

            if (! is_array($parsed)) {
                Log::warning('DeepSeek moderation: invalid JSON response', ['raw' => $result['content']]);
                return $this->defaultSafeResult();
            }

            return [
                'is_safe'    => (bool)  ($parsed['is_safe']    ?? true),
                'score'      => (float) ($parsed['score']      ?? 0.0),
                'categories' => (array) ($parsed['categories'] ?? []),
                'reason'     => (string)($parsed['reason']     ?? ''),
            ];
        });
    }

    // ──────────────────────────────────────────────────────────
    // TAGS
    // ──────────────────────────────────────────────────────────

    /**
     * Génère des tags pertinents pour un post communautaire.
     *
     * @param  int  $maxTags  Nombre maximum de tags à générer (défaut : 5)
     * @return string[]
     */
    public function generateTags(string $postContent, int $maxTags = 5): array
    {
        $cacheKey = 'deepseek_tags_' . md5($postContent . $maxTags);

        return Cache::remember($cacheKey, self::TAGS_CACHE_TTL, function () use ($postContent, $maxTags) {
            $result = $this->chat(
                messages: [
                    [
                        'role'    => 'system',
                        'content' => 'Tu génères des tags concis et pertinents pour des posts communautaires. '
                            . 'Réponds UNIQUEMENT avec un tableau JSON de strings, sans markdown. '
                            . 'Exemple : ["tag1", "tag2", "tag3"]',
                    ],
                    [
                        'role'    => 'user',
                        'content' => "Génère {$maxTags} tags pour ce post :\n\n" . mb_substr($postContent, 0, 2000),
                    ],
                ],
                options: ['temperature' => 0.3, 'max_tokens' => 150]
            );

            if (! $result['success']) {
                return [];
            }

            $json = preg_replace('/```(?:json)?\n?|\n?```/', '', trim($result['content']));
            $tags = json_decode($json, true);

            if (! is_array($tags)) {
                return [];
            }

            // Nettoyage : on garde uniquement les strings non vides
            return array_values(array_filter(
                array_slice($tags, 0, $maxTags),
                fn($t) => is_string($t) && trim($t) !== ''
            ));
        });
    }

    // ──────────────────────────────────────────────────────────
    // RÉSUMÉ DE FIL DE DISCUSSION
    // ──────────────────────────────────────────────────────────

    /**
     * Résume un fil de discussion.
     *
     * @param  array  $messages  Tableau de ['author' => string, 'content' => string]
     */
    public function summarizeThread(array $messages): string
    {
        if (count($messages) < 2) {
            return 'Pas assez de messages pour générer un résumé.';
        }

        // Construction du fil de discussion formaté
        $thread = collect($messages)
            ->map(fn($m) => "[{$m['author']}] : {$m['content']}")
            ->implode("\n");

        // Limite de tokens : on tronque si nécessaire
        $thread = mb_substr($thread, 0, 4000);

        $result = $this->chat(
            messages: [
                [
                    'role'    => 'system',
                    'content' => 'Tu résumes des discussions communautaires en 2 à 3 phrases claires, neutres et informatives. '
                        . 'Tu mentionnes les points clés et le consensus éventuel.',
                ],
                [
                    'role'    => 'user',
                    'content' => "Résume cette discussion :\n\n{$thread}",
                ],
            ],
            options: ['max_tokens' => 512, 'temperature' => 0.4]
        );

        return $result['success'] ? $result['content'] : 'Résumé temporairement indisponible.';
    }

    // ──────────────────────────────────────────────────────────
    // AMÉLIORATION DE POST
    // ──────────────────────────────────────────────────────────

    /**
     * Améliore la clarté et la lisibilité d'un post.
     * Conserve le sens, le ton et la langue d'origine.
     */
    public function improvePost(string $content): string
    {
        $result = $this->chat(
            messages: [
                [
                    'role'    => 'system',
                    'content' => 'Tu es un assistant de rédaction pour une communauté en ligne. '
                        . 'Améliore la clarté, la lisibilité et la structure du texte sans en changer le sens, '
                        . 'le ton ni la langue. Retourne uniquement le texte amélioré, sans commentaire.',
                ],
                [
                    'role'    => 'user',
                    'content' => "Améliore ce post :\n\n" . mb_substr($content, 0, 3000),
                ],
            ],
            options: ['max_tokens' => 1024, 'temperature' => 0.5]
        );

        return $result['success'] ? $result['content'] : $content;
    }

    // ──────────────────────────────────────────────────────────
    // HELPERS PRIVÉS
    // ──────────────────────────────────────────────────────────

    /**
     * Construit le payload pour l'API DeepSeek.
     */
    private function buildPayload(array $messages, array $options, bool $stream): array
    {
        return array_merge([
            'model'       => config('services.deepseek.default_model', 'deepseek-chat'),
            'messages'    => $messages,
            'max_tokens'  => config('services.deepseek.max_tokens', 2048),
            'temperature' => config('services.deepseek.temperature', 0.7),
            'stream'      => $stream,
        ], $options);
    }

    /**
     * Lit une ligne depuis un stream Guzzle.
     */
    private function readStreamLine($body): string
    {
        $line = '';
        while (! $body->eof()) {
            $char = $body->read(1);
            if ($char === "\n") {
                break;
            }
            $line .= $char;
        }
        return rtrim($line, "\r");
    }

    /**
     * Résultat par défaut en cas d'erreur de modération (fail open).
     */
    private function defaultSafeResult(): array
    {
        return [
            'is_safe'    => true,
            'score'      => 0.0,
            'categories' => [],
            'reason'     => 'Modération indisponible — contenu publié sans vérification.',
        ];
    }

    /**
     * Normalise les exceptions Guzzle en tableau d'erreur.
     */
    private function handleException(\Throwable $e, string $context): array
    {
        $code    = $e->getCode();
        $message = $e->getMessage();

        if ($e instanceof ConnectException) {
            $userMessage = 'Impossible de joindre le service IA. Veuillez réessayer.';
        } elseif ($e instanceof ServerException) {
            $userMessage = 'Le service IA est temporairement indisponible (erreur serveur).';
        } elseif ($e instanceof RequestException && $code === 429) {
            $userMessage = 'Quota IA dépassé. Veuillez patienter avant de réessayer.';
        } elseif ($e instanceof RequestException && $code === 401) {
            $userMessage = 'Clé API invalide. Contactez l\'administrateur.';
        } else {
            $userMessage = 'Une erreur inattendue s\'est produite avec le service IA.';
        }

        Log::error("DeepSeek error [{$context}]", [
            'code'    => $code,
            'message' => $message,
            'class'   => get_class($e),
        ]);

        return [
            'success' => false,
            'error'   => $userMessage,
            'code'    => $code,
            'content' => '',
            'usage'   => [],
            'model'   => '',
        ];
    }
}
