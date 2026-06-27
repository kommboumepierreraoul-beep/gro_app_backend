<?php

namespace App\Services\Translation\Providers;

use App\Services\Translation\TranslationException;
use App\Services\Translation\TranslationProviderInterface;
use Aws\Exception\AwsException;
use Aws\Translate\TranslateClient;

class AmazonTranslateProvider implements TranslationProviderInterface
{
    protected TranslateClient $client;

    public function __construct(protected array $config)
    {
        $this->client = new TranslateClient([
            'version' => 'latest',
            'region' => $this->config['region'],
            'credentials' => [
                'key' => $this->config['key'],
                'secret' => $this->config['secret'],
            ],
        ]);
    }

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        try {
            $result = $this->client->translateText([
                'Text' => $text,
                'SourceLanguageCode' => $sourceLocale ?? 'auto',
                'TargetLanguageCode' => $targetLocale,
            ]);
        } catch (AwsException $e) {
            throw new TranslationException('Amazon Translate a échoué : ' . $e->getMessage());
        }

        return $result['TranslatedText'];
    }

    public function translateBatch(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        // Amazon Translate n'offre pas de traduction de texte synchrone par lot :
        // on traduit séquentiellement, ce qui reste transparent pour le reste du système.
        return array_map(fn($text) => $this->translate($text, $targetLocale, $sourceLocale), $texts);
    }
}
