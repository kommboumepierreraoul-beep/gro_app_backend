<?php

namespace App\Services\Translation;

interface TranslationProviderInterface
{
    /**
     * Traduit un texte unique. Lève TranslationException en cas d'échec.
     */
    public function translate(string $text, string $targetLocale, ?string $sourceLocale = null): string;

    /**
     * Traduit plusieurs textes en un seul appel API.
     * Retourne un tableau indexé dans le même ordre que $texts.
     */
    public function translateBatch(array $texts, string $targetLocale, ?string $sourceLocale = null): array;
}
