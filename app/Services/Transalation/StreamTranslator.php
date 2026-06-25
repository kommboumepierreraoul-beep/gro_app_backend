<?php

namespace App\Services\Translation;

class StreamingTranslator
{
    protected string $buffer = '';

    public function __construct(
        protected TranslationService $translator,
        protected string $targetLocale,
        protected string $sourceLocale,
    ) {}

    /**
     * Reçoit un token du flux IA. Retourne le texte traduit prêt à
     * envoyer dès qu'une phrase complète est détectée, sinon null.
     */
    public function push(string $token): ?string
    {
        if ($this->targetLocale === $this->sourceLocale) {
            return $token;
        }

        $this->buffer .= $token;

        if (!preg_match('/^(.*[.!?\n])\s*(.*)$/s', $this->buffer, $matches)) {
            return null;
        }

        [, $completeSentence, $remainder] = $matches;
        $this->buffer = $remainder;

        return $this->translator->translateLive($completeSentence, $this->targetLocale, $this->sourceLocale);
    }

    /**
     * À appeler à la fin du flux pour traduire le reste du buffer.
     */
    public function flush(): ?string
    {
        if ($this->buffer === '' || $this->targetLocale === $this->sourceLocale) {
            return null;
        }

        $remaining = $this->buffer;
        $this->buffer = '';

        return $this->translator->translateLive($remaining, $this->targetLocale, $this->sourceLocale);
    }
}
