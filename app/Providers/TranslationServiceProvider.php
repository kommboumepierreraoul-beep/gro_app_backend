<?php

namespace App\Providers;

use App\Services\Translation\TranslationProviderFactory;
use Illuminate\Support\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TranslationProviderFactory::class);
    }
}
