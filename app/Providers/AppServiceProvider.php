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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use App\Models\Order;
use App\Policies\OrderPolicy;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Les politiques d'autorisation de l'application.
     */
    protected $policies = [
        Order::class => OrderPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ✅ Enregistrer le binding de l'interface IA
        $this->app->bind(AIModerationInterface::class, function ($app) {
            $provider = config('moderation.ai_provider', 'groq');

            return match ($provider) {
                'openai' => new OpenAIModerationProvider(),
                default => new GroqModerationProvider(),
            };
        });

        // ✅ Services de modération
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
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ✅ Enregistrer la règle de validation exists_with
        Validator::extend('exists_with', function ($attribute, $value, $parameters, $validator) {
            $contextType = $validator->getData()['context_type'] ?? null;

            if (empty($contextType)) {
                return true; // Pas de contexte = pas de validation
            }

            // ✅ Vérifier que le modèle existe avant de l'utiliser
            $modelMap = [
                'post' => \App\Models\Post::class,
                'comment' => \App\Models\Comment::class,
                // ✅ Ajouter d'autres types si nécessaire
                // 'message' => \App\Models\Message::class,
                // 'user' => \App\Models\User::class,
            ];

            if (app()->environment('production')) {
                URL::forceScheme('https');
            }

            $model = $modelMap[$contextType] ?? null;

            if (!$model || !class_exists($model)) {
                return true; // Type inconnu ou modèle inexistant = pas de validation
            }

            return $model::where('id', $value)->exists();
        });

        // ✅ Ajouter une règle de validation pour la modération
        Validator::extend('moderation_status', function ($attribute, $value, $parameters, $validator) {
            return in_array($value, ['pending', 'approved', 'review', 'rejected']);
        });

        // ✅ Ajouter une règle de validation pour les scores
        Validator::extend('moderation_score', function ($attribute, $value, $parameters, $validator) {
            return is_numeric($value) && $value >= 0 && $value <= 1;
        });
    }
}