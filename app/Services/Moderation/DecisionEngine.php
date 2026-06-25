<?php

namespace App\Services\Moderation;

class DecisionEngine
{
    private array $thresholds = [
        'toxicity' => ['review' => 0.45, 'reject' => 0.80],
        'spam' => ['review' => 0.40, 'reject' => 0.75],
        'hate' => ['review' => 0.30, 'reject' => 0.65],
        'violence' => ['review' => 0.30, 'reject' => 0.65],
    ];

    public function decide(array $scores): string
    {
        foreach ($this->thresholds as $category => $bounds) {
            $value = $scores[$category] ?? 0;

            // ✅ Rejet immédiat pour les cas graves
            if ($value >= $bounds['reject']) {
                return 'reject';
            }

            // ✅ Review pour les cas ambigus
            if ($value >= $bounds['review']) {
                return 'review';
            }
        }

        // ✅ Par défaut, approuver
        return 'approve';
    }

    public function getThresholds(): array
    {
        return $this->thresholds;
    }

    public function setThresholds(array $thresholds): self
    {
        $this->thresholds = array_merge($this->thresholds, $thresholds);
        return $this;
    }
}