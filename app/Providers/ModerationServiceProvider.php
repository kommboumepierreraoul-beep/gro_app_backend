<?php

namespace App\Providers;

use App\Services\Moderation\Contracts\AIModerationInterface;
use App\Services\Moderation\Providers\GroqModerationProvider;
use App\Services\Moderation\Providers\ClaudeModerationProvider;
use App\Services\Moderation\Providers\OpenAIModerationProvider;
use App\Services\Moderation\Providers\GeminiModerationProvider;
use App\Services\Moderation\SyncModerationService;
use App\Services\Moderation\DecisionEngine;
use App\Services\Moderation\FastModerationLayer;
use App\Services\Moderation\ModerationService;
use Illuminate\Support\ServiceProvider;

class ModerationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 1. Binding de l'interface
        $this->app->bind(AIModerationInterface::class, function ($app) {
            $provider = config('moderation.ai_provider', 'groq');

            return match ($provider) {
                'openai' => new OpenAIModerationProvider(),
                default => new GroqModerationProvider(),
            };
        });

        // 2. Services
        $this->app->singleton(DecisionEngine::class, function ($app) {
            return new DecisionEngine();
        });

        $this->app->singleton(FastModerationLayer::class, function ($app) {
            return new FastModerationLayer();
        });

        $this->app->singleton(ModerationService::class, function ($app) {
            return new ModerationService(
                $app->make(AIModerationInterface::class),
                $app->make(DecisionEngine::class),
                $app->make(FastModerationLayer::class)
            );
        });

        $this->app->singleton(SyncModerationService::class, function ($app) {
            return new SyncModerationService(
                $app->make(AIModerationInterface::class),
                $app->make(DecisionEngine::class),
                $app->make(FastModerationLayer::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publier la configuration
        $this->publishes([
            __DIR__ . '/../../config/moderation.php' => config_path('moderation.php'),
        ], 'moderation-config');
    }
}
