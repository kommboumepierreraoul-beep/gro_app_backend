<?php

namespace App\Services\Translation\Providers;

use App\Services\AI\DeepSeekService;
use App\Services\Translation\TranslationException;
use App\Services\Translation\TranslationProviderInterface;

class DeepSeekTranslationProvider implements TranslationProviderInterface
{
    public function __construct(
        protected array $config,
        protected DeepSeekService $deepSeek,
    ) {}

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        $prompt = "Traduis le texte suivant en {$targetLocale}. Réponds uniquement avec la traduction, sans aucun commentaire ni guillemets :\n\n{$text}";

        try {
            return trim($this->deepSeek->complete($prompt));
        } catch (\Throwable $e) {
            throw new TranslationException('DeepSeek (traduction) a échoué : ' . $e->getMessage());
        }
    }

    public function translateBatch(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        return array_map(fn($t) => $this->translate($t, $targetLocale, $sourceLocale), $texts);
    }
}
