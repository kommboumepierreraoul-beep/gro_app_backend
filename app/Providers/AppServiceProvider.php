<?php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use App\Models\Post;
use App\Observers\PostObserver;
use App\Services\AI\ConversationService;
use App\Services\AI\DeepSeekService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Enregistrement des services IA en singleton
        // (le client Guzzle est instancié une seule fois par requête)
        $this->app->singleton(DeepSeekService::class);
        $this->app->singleton(ConversationService::class);
    }

    public function boot(): void
    {
        // ── Observers ─────────────────────────────────────────
        Post::observe(PostObserver::class);

        // ── Rate Limiters ──────────────────────────────────────
        RateLimiter::for('ai', function (Request $request) {
            $userId = $request->user()?->id ?? $request->ip();

            return [
                // 20 requêtes par minute par utilisateur
                Limit::perMinute(20)->by("ai_min_{$userId}"),
                // 500 requêtes par jour par utilisateur
                Limit::perDay(500)->by("ai_day_{$userId}"),
            ];
        });
    }
}