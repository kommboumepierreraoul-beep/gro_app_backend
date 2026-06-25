<?php

namespace App\Services\Translation\Providers;

use App\Services\Translation\TranslationException;
use App\Services\Translation\TranslationProviderInterface;
use Illuminate\Support\Facades\Http;

class LibreTranslateProvider implements TranslationProviderInterface
{
    public function __construct(protected array $config) {}

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        $response = Http::timeout(10)->retry(2, 200)->post($this->config['url'], array_filter([
            'q' => $text,
            'source' => $sourceLocale ?? 'auto',
            'target' => $targetLocale,
            'format' => 'text',
            'api_key' => $this->config['key'] ?? null,
        ]));

        if ($response->failed()) {
            throw new TranslationException('LibreTranslate a échoué : ' . $response->status());
        }

        $translated = $response->json('translatedText');

        if ($translated === null) {
            throw new TranslationException('Réponse LibreTranslate invalide.');
        }

        return $translated;
    }

    public function translateBatch(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        // Le support du batch dépend de la version de l'instance LibreTranslate.
        $response = Http::timeout(20)->retry(2, 200)->post($this->config['url'], array_filter([
            'q' => $texts,
            'source' => $sourceLocale ?? 'auto',
            'target' => $targetLocale,
            'format' => 'text',
            'api_key' => $this->config['key'] ?? null,
        ]));

        if ($response->failed()) {
            throw new TranslationException('LibreTranslate (batch) a échoué : ' . $response->status());
        }

        $translated = $response->json('translatedText');

        if (!is_array($translated)) {
            // Instance ne supportant pas le batch : on retombe sur des appels individuels.
            return array_map(fn($t) => $this->translate($t, $targetLocale, $sourceLocale), $texts);
        }

        return $translated;
    }
}
