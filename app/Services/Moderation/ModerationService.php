<?php

namespace App\Services\Moderation;

use App\Services\Moderation\Contracts\AIModerationInterface;
use App\Services\Moderation\Providers\GroqModerationProvider;
use Illuminate\Support\Facades\Log;

class ModerationService
{
    private AIModerationInterface $provider;
    private DecisionEngine $decisionEngine;
    private FastModerationLayer $fastLayer;

    private array $providers = [
        'groq' => GroqModerationProvider::class,
    ];

    public function __construct(
        ?AIModerationInterface $provider = null,
        ?DecisionEngine $decisionEngine = null,
        ?FastModerationLayer $fastLayer = null
    ) {
        $this->decisionEngine = $decisionEngine ?? new DecisionEngine();
        $this->fastLayer = $fastLayer ?? new FastModerationLayer();
        $this->provider = $provider ?? $this->getDefaultProvider();
    }

    /**
     * Obtenir le provider par défaut depuis le .env
     */
    private function getDefaultProvider(): AIModerationInterface
    {
        $default = config('moderation.ai_provider', 'claude');
        return $this->getProvider($default);
    }

    /**
     * Obtenir un provider spécifique
     */
    public function getProvider(string $name): AIModerationInterface
    {
        if (!isset($this->providers[$name])) {
            Log::warning("Provider {$name} non trouvé, utilisation du provider par défaut");
            return new GroqModerationProvider();
        }

        $class = $this->providers[$name];
        return new $class();
    }

    /**
     * Changer le provider
     */
    public function setProvider(string $name): self
    {
        $this->provider = $this->getProvider($name);
        return $this;
    }

    /**
     * Changer le provider avec une instance
     */
    public function setProviderInstance(AIModerationInterface $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * Analyser un contenu (texte + optionnel image)
     */
    public function analyze(string $content, ?string $imageBase64 = null, string $mediaType = 'image/jpeg'): array
    {
        // Vérification rapide (Fast Layer)
        $fastResult = $this->fastLayer->check($content);

        if ($fastResult !== null) {
            return [
                'safe' => $fastResult !== 'rejected',
                'action' => $fastResult,
                'reason' => 'Décision rapide (filtre)',
                'toxicity' => 0,
                'spam' => 0,
                'hate' => 0,
                'violence' => 0,
                'source' => 'fast_layer',
            ];
        }

        // Analyse IA
        try {
            if ($imageBase64) {
                $result = $this->provider->analyzeTextWithImage($content, $imageBase64, $mediaType);
            } else {
                $result = $this->provider->analyzeText($content);
            }
        } catch (\Exception $e) {
            Log::error('Moderation analysis failed', [
                'error' => $e->getMessage(),
                'provider' => $this->provider->getProviderName(),
            ]);
            return $this->getFallbackResponse($e->getMessage());
        }

        // Décision finale
        $action = $this->decisionEngine->decide($result);
        $result['action'] = $action;
        $result['source'] = $this->provider->getProviderName();

        return $result;
    }

    /**
     * Analyser un post
     */
    public function analyzePost(\App\Models\Post $post): array
    {
        $imageBase64 = null;
        $mediaType = 'image/jpeg';

        if ($post->cover_image) {
            $imageBase64 = $this->getImageBase64($post->cover_image);
        }

        return $this->analyze($post->content, $imageBase64, $mediaType);
    }

    /**
     * Analyser un commentaire
     */
    public function analyzeComment(\App\Models\Comment $comment): array
    {
        return $this->analyze($comment->content);
    }

    /**
     * Analyser un message
     */
    public function analyzeMessage(\App\Models\Message $message): array
    {
        $imageBase64 = null;
        $mediaType = 'image/jpeg';

        if ($message->media_url) {
            $imageBase64 = $this->getImageBase64($message->media_url);
        }

        return $this->analyze($message->content, $imageBase64, $mediaType);
    }

    /**
     * Récupérer une image en base64
     */
    private function getImageBase64(string $path): ?string
    {
        try {
            $fullPath = storage_path('app/public/' . $path);
            if (file_exists($fullPath)) {
                return base64_encode(file_get_contents($fullPath));
            }
        } catch (\Exception $e) {
            Log::error('Erreur lecture image', ['path' => $path, 'error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Réponse de fallback en cas d'erreur
     */
    private function getFallbackResponse(string $error): array
    {
        return [
            'safe' => false,
            'action' => 'review',
            'reason' => 'Erreur technique: ' . $error,
            'toxicity' => 0,
            'spam' => 0,
            'hate' => 0,
            'violence' => 0,
            'source' => 'fallback',
        ];
    }

    /**
     * Obtenir le provider actuel
     */
    public function getCurrentProvider(): AIModerationInterface
    {
        return $this->provider;
    }

    /**
     * Obtenir les providers disponibles
     */
    public function getAvailableProviders(): array
    {
        $available = [];
        foreach ($this->providers as $name => $class) {
            $provider = new $class();
            if ($provider->isAvailable()) {
                $available[$name] = [
                    'name' => $name,
                    'model' => $provider->getModel(),
                    'available' => true,
                ];
            }
        }
        return $available;
    }

    /**
     * Vérifier si un provider est disponible
     */
    public function isProviderAvailable(string $name): bool
    {
        try {
            $provider = $this->getProvider($name);
            return $provider->isAvailable();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtenir le coût estimé
     */
    public function getEstimatedCost(string $content, ?string $imageBase64 = null): float
    {
        $input = ['text' => $content];
        if ($imageBase64) {
            $input['image'] = true;
        }
        return $this->provider->getEstimatedCost($input);
    }
}
