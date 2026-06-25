<?php

namespace App\Services\Translation\Providers;

use App\Services\Translation\TranslationException;
use App\Services\Translation\TranslationProviderInterface;
use Illuminate\Support\Facades\Http;

class AzureTranslatorProvider implements TranslationProviderInterface
{
    public function __construct(protected array $config) {}

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        return $this->translateBatch([$text], $targetLocale, $sourceLocale)[0];
    }

    public function translateBatch(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        $query = ['api-version' => '3.0', 'to' => $targetLocale];

        if ($sourceLocale) {
            $query['from'] = $sourceLocale;
        }

        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->config['key'],
            'Ocp-Apim-Subscription-Region' => $this->config['region'],
            'Content-Type' => 'application/json',
        ])
            ->timeout(15)
            ->retry(2, 200)
            ->post($this->config['url'] . '?' . http_build_query($query), array_map(fn($t) => ['Text' => $t], $texts));

        if ($response->failed()) {
            throw new TranslationException('Azure Translator a échoué : ' . $response->status());
        }

        $body = $response->json();

        if (!is_array($body)) {
            throw new TranslationException('Réponse Azure Translator invalide.');
        }

        return array_map(fn($item) => $item['translations'][0]['text'], $body);
    }
}
