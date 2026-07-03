<?php

namespace App\Providers;

use App\Events\ContentModerated;
use App\Events\ContentReported;
use App\Events\ModerationConfigUpdated;
use App\Listeners\LogModerationDecision;
use App\Listeners\NotifyUserModeration;
use App\Listeners\SendEmailNotification;
use App\Listeners\SendModerationWebhook;
use App\Listeners\UpdateModerationStats;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // Événements de modération
        ContentModerated::class => [
            NotifyUserModeration::class,      // Notification en base de données
            LogModerationDecision::class,     // Log dans les fichiers
            SendEmailNotification::class,     // Email à l'utilisateur
            SendModerationWebhook::class,     // Webhook externe
            UpdateModerationStats::class,     // Mise à jour des stats
        ],

        ContentReported::class => [
            // Ajoutez vos listeners ici
        ],

        ModerationConfigUpdated::class => [
            // Ajoutez vos listeners ici
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
