<?php

namespace App\Services\Moderation\Contracts;

interface AIModerationInterface
{
    /**
     * Analyser un texte
     */
    public function analyzeText(string $content): array;

    /**
     * Analyser une image
     */
    public function analyzeImage(string $base64, string $mediaType = 'image/jpeg'): array;

    /**
     * Analyser un texte avec une image
     */
    public function analyzeTextWithImage(string $text, string $base64, string $mediaType = 'image/jpeg'): array;

    /**
     * Obtenir le nom du provider
     */
    public function getProviderName(): string;

    /**
     * Obtenir le modèle utilisé
     */
    public function getModel(): string;

    /**
     * Vérifier si le provider est disponible
     */
    public function isAvailable(): bool;

    /**
     * Obtenir le coût estimé de l'analyse
     */
    public function getEstimatedCost(array $input): float;

    /**
     * Parser la réponse brute
     */
    public function parseResponse(string $raw): array;
}
