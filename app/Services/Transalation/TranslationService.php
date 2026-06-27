<?php

namespace App\Services\Translation;

use App\Models\Translation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TranslationService
{
    /** @var string[] Liste ordonnée des providers à essayer */
    protected array $providerChain;

    public function __construct(protected TranslationProviderFactory $factory)
    {
        $this->providerChain = array_values(array_unique(array_merge(
            [config('translation.default_provider')],
            config('translation.fallback_chain', [])
        )));
    }

    public function translateCached(string $text, string $targetLocale, string $context, int $ttlDays): string
    {
        $sourceLocale = config('translation.source_locale');

        if ($targetLocale === $sourceLocale) {
            return $text;
        }

        $hash = hash('sha256', $text);
        $cacheKey = "translation:{$context}:{$targetLocale}:{$hash}";

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $existing = Translation::where('context', $context)
            ->where('locale', $targetLocale)
            ->where('source_hash', $hash)
            ->first();

        if ($existing) {
            Cache::put($cacheKey, $existing->value, now()->addDays($ttlDays));
            return $existing->value;
        }

        [$translated, $succeeded] = $this->translateWithChain(
            fn(TranslationProviderInterface $provider) => $provider->translate($text, $targetLocale, $sourceLocale),
            $text
        );

        if (!$succeeded) {
            // Échec total de la chaîne : on ne persiste rien, pour retenter
            // dès la prochaine requête plutôt que de figer un texte non
            // traduit pendant toute la durée du TTL.
            return $translated;
        }

        Translation::updateOrCreate(
            ['context' => $context, 'locale' => $targetLocale],
            ['source_hash' => $hash, 'value' => $translated, 'expires_at' => now()->addDays($ttlDays)]
        );

        Cache::put($cacheKey, $translated, now()->addDays($ttlDays));

        return $translated;
    }

    /**
     * @param array<string, string> $texts
     * @return array<string, string>
     */
    public function translateBatchCached(array $texts, string $targetLocale, string $contextPrefix, int $ttlDays): array
    {
        $sourceLocale = config('translation.source_locale');

        if ($targetLocale === $sourceLocale) {
            return $texts;
        }

        $results = [];
        $missingKeys = [];
        $missingTexts = [];

        foreach ($texts as $key => $text) {
            $hash = hash('sha256', $text);
            $context = "{$contextPrefix}.{$key}";
            $cacheKey = "translation:{$context}:{$targetLocale}:{$hash}";

            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                $results[$key] = $cached;
                continue;
            }

            $existing = Translation::where('context', $context)
                ->where('locale', $targetLocale)
                ->where('source_hash', $hash)
                ->first();

            if ($existing) {
                Cache::put($cacheKey, $existing->value, now()->addDays($ttlDays));
                $results[$key] = $existing->value;
                continue;
            }

            $missingKeys[] = $key;
            $missingTexts[] = $text;
        }

        if (empty($missingTexts)) {
            return $results;
        }

        [$translatedTexts, $succeeded] = $this->translateWithChain(
            fn(TranslationProviderInterface $provider) => $provider->translateBatch($missingTexts, $targetLocale, $sourceLocale),
            $missingTexts
        );

        foreach ($missingKeys as $i => $key) {
            $translated = $translatedTexts[$i];
            $results[$key] = $translated;

            if (!$succeeded) {
                continue; // échec : on ne met rien en cache, pour retenter plus tard
            }

            $text = $missingTexts[$i];
            $hash = hash('sha256', $text);
            $context = "{$contextPrefix}.{$key}";
            $cacheKey = "translation:{$context}:{$targetLocale}:{$hash}";

            Translation::updateOrCreate(
                ['context' => $context, 'locale' => $targetLocale],
                ['source_hash' => $hash, 'value' => $translated, 'expires_at' => now()->addDays($ttlDays)]
            );

            Cache::put($cacheKey, $translated, now()->addDays($ttlDays));
        }

        return $results;
    }

    /**
     * Traduction directe sans cache, pour le streaming IA (contenu non déterministe).
     */
    public function translateLive(string $text, string $targetLocale, string $sourceLocale): string
    {
        [$translated] = $this->translateWithChain(
            fn(TranslationProviderInterface $provider) => $provider->translate($text, $targetLocale, $sourceLocale),
            $text
        );

        return $translated;
    }

    /**
     * Essaie chaque provider de la chaîne dans l'ordre jusqu'au succès.
     * Si tous échouent, retourne [$fallbackValue, false] (fail-open).
     *
     * @return array{0: mixed, 1: bool}
     */
    protected function translateWithChain(callable $callback, mixed $fallbackValue): array
    {
        foreach ($this->providerChain as $providerName) {
            try {
                $provider = $this->factory->make($providerName);

                return [$callback($provider), true];
            } catch (TranslationException $e) {
                Log::warning("Provider de traduction « {$providerName} » a échoué.", [
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        Log::error('Tous les providers de traduction configurés ont échoué, fallback texte source.');

        return [$fallbackValue, false];
    }
}
