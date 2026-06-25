<?php

namespace App\Services\Moderation;

use App\Models\Post;
use App\Models\ModerationPost;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class FastModerationLayer
{
    private array $blocklist = [];
    private array $spamDomains = [];

    public function __construct()
    {
        $this->blocklist = config('moderation.blocklist', []);
        $this->spamDomains = config('moderation.spam_domains', []);
    }

    /**
     * Vérification rapide
     * Retourne null si le contenu doit passer à l'IA
     */
    public function check(string $content, ?int $userId = null): ?string
    {
        // 1. Rate Limiter
        if ($userId && $this->isRateLimited($userId)) {
            return 'rejected';
        }

        // 2. Blocklist
        if ($this->matchesBlocklist($content)) {
            return 'rejected';
        }

        // 3. Spam Domains
        if ($this->containsSpamDomain($content)) {
            return 'rejected';
        }

        // 4. Duplicate Detector
        $duplicateStatus = $this->checkDuplicate($content);
        if ($duplicateStatus !== null) {
            return $duplicateStatus;
        }

        return null; // Cas ambigu -> passer à l'IA
    }

    /**
     * Vérifier le rate limit
     */
    private function isRateLimited(int $userId): bool
    {
        $key = 'moderation:rate_limit:' . $userId;
        $maxAttempts = $this->getRateLimitForUser($userId);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return true;
        }

        RateLimiter::hit($key, 3600);
        return false;
    }

    /**
     * Obtenir le rate limit pour un utilisateur
     */
    private function getRateLimitForUser(int $userId): int
    {
        $user = \App\Models\User::find($userId);
        if ($user && $user->isAdmin()) {
            return 100;
        }

        return config('moderation.rate_limit', 10);
    }

    /**
     * Vérifier si le contenu correspond à la blocklist
     */
    private function matchesBlocklist(string $content): bool
    {
        $contentLower = strtolower($content);

        foreach ($this->blocklist as $word) {
            if (str_contains($contentLower, strtolower($word))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifier si le contenu contient un domaine spam
     */
    private function containsSpamDomain(string $content): bool
    {
        foreach ($this->spamDomains as $domain) {
            if (stripos($content, $domain) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * ✅ Vérifier les doublons dans moderation_posts
     */
    private function checkDuplicate(string $content): ?string
    {
        $hash = hash('sha256', $content);
        $cacheKey = 'moderation:duplicate:' . $hash;

        // Vérifier en cache d'abord
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // ✅ Utiliser ModerationPost au lieu de Post
        $existing = ModerationPost::where('content_hash', $hash)
            ->whereIn('status', ['approved', 'rejected'])
            ->first();

        if ($existing) {
            $status = $existing->status;
            Cache::put($cacheKey, $status, 3600);
            return $status;
        }

        return null;
    }

    /**
     * Ajouter un mot à la blocklist
     */
    public function addToBlocklist(string $word): self
    {
        if (!in_array($word, $this->blocklist)) {
            $this->blocklist[] = $word;
        }
        return $this;
    }

    /**
     * Ajouter un domaine spam
     */
    public function addSpamDomain(string $domain): self
    {
        if (!in_array($domain, $this->spamDomains)) {
            $this->spamDomains[] = $domain;
        }
        return $this;
    }

    /**
     * Obtenir la blocklist
     */
    public function getBlocklist(): array
    {
        return $this->blocklist;
    }

    /**
     * Obtenir les domaines spam
     */
    public function getSpamDomains(): array
    {
        return $this->spamDomains;
    }
}
