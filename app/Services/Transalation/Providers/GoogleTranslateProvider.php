<?php

namespace App\Services\Translation\Providers;

use App\Services\Translation\TranslationException;
use App\Services\Translation\TranslationProviderInterface;
use Illuminate\Support\Facades\Http;

class GoogleTranslateProvider implements TranslationProviderInterface
{
    public function __construct(protected array $config) {}

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        $response = Http::timeout(8)->retry(2, 200)->post($this->endpoint(), array_filter([
            'q' => $text,
            'target' => $targetLocale,
            'source' => $sourceLocale,
            'format' => 'text',
        ]));

        if ($response->failed()) {
            throw new TranslationException('Google Translate a échoué : ' . $response->status());
        }

        $translated = $response->json('data.translations.0.translatedText');

        if ($translated === null) {
            throw new TranslationException('Réponse Google Translate invalide.');
        }

        return $translated;
    }

    public function translateBatch(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        $response = Http::timeout(15)->retry(2, 200)->post($this->endpoint(), array_filter([
            'q' => $texts,
            'target' => $targetLocale,
            'source' => $sourceLocale,
            'format' => 'text',
        ]));

        if ($response->failed()) {
            throw new TranslationException('Google Translate (batch) a échoué : ' . $response->status());
        }

        $translations = $response->json('data.translations');

        if ($translations === null) {
            throw new TranslationException('Réponse Google Translate (batch) invalide.');
        }

        return array_map(fn($t) => $t['translatedText'], $translations);
    }

    protected function endpoint(): string
    {
        return $this->config['url'] . '?key=' . $this->config['key'];
    }
}
