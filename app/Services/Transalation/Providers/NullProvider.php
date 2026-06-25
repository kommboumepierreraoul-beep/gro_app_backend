<?php

namespace App\Services\Translation\Providers;

use App\Services\Translation\TranslationProviderInterface;

class NullProvider implements TranslationProviderInterface
{
    public function __construct(protected array $config = []) {}

    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string
    {
        return "[{$targetLocale}] {$text}";
    }

    public function translateBatch(array $texts, string $targetLocale, ?string $sourceLocale = null): array
    {
        return array_map(fn($t) => $this->translate($t, $targetLocale, $sourceLocale), $texts);
    }
}
