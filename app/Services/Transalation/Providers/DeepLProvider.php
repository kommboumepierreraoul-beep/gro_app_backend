<?php

namespace App\Services\Translation\Providers;

use App\Services\Translation\TranslationException;
use App\Services\Translation\TranslationProviderInterface;
use Illuminate\Support\Facades\Http;

class DeepLProvider implements TranslationProviderInterface
{
    public function __construct(protected array $config) {}

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        $response = Http::asForm()
            ->withHeaders(['Authorization' => 'DeepL-Auth-Key ' . $this->config['key']])
            ->timeout(8)
            ->retry(2, 200)
            ->post($this->config['url'], array_filter([
                'text' => $text,
                'target_lang' => strtoupper($targetLocale),
                'source_lang' => $sourceLocale ? strtoupper($sourceLocale) : null,
            ]));

        if ($response->failed()) {
            throw new TranslationException('DeepL a échoué : ' . $response->status());
        }

        $translated = $response->json('translations.0.text');

        if ($translated === null) {
            throw new TranslationException('Réponse DeepL invalide.');
        }

        return $translated;
    }

    public function translateBatch(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        $params = collect($texts)->map(fn($t) => 'text=' . rawurlencode($t))->implode('&');
        $params .= '&target_lang=' . rawurlencode(strtoupper($targetLocale));

        if ($sourceLocale) {
            $params .= '&source_lang=' . rawurlencode(strtoupper($sourceLocale));
        }

        $response = Http::withHeaders([
            'Authorization' => 'DeepL-Auth-Key ' . $this->config['key'],
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])
            ->timeout(15)
            ->retry(2, 200)
            ->withBody($params, 'application/x-www-form-urlencoded')
            ->post($this->config['url']);

        if ($response->failed()) {
            throw new TranslationException('DeepL (batch) a échoué : ' . $response->status());
        }

        $translations = $response->json('translations');

        if ($translations === null) {
            throw new TranslationException('Réponse DeepL (batch) invalide.');
        }

        return array_map(fn($t) => $t['text'], $translations);
    }
}
