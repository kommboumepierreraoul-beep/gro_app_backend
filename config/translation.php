<?php

return [
    'default_provider' => env('TRANSLATION_PROVIDER', 'deepl'),

    // Providers tentés dans l'ordre si le provider par défaut échoue.
    // Exemple .env : TRANSLATION_FALLBACK_CHAIN=google,libretranslate
    'fallback_chain' => array_filter(explode(',', env('TRANSLATION_FALLBACK_CHAIN', ''))),

    'providers' => [

        'deepl' => [
            'driver' => \App\Services\Translation\Providers\DeepLProvider::class,
            'key' => env('DEEPL_API_KEY'),
            'url' => env('DEEPL_API_URL', 'https://api-free.deepl.com/v2/translate'),
        ],

        'google' => [
            'driver' => \App\Services\Translation\Providers\GoogleTranslateProvider::class,
            'key' => env('GOOGLE_TRANSLATE_API_KEY'),
            'url' => 'https://translation.googleapis.com/language/translate/v2',
        ],

        'azure' => [
            'driver' => \App\Services\Translation\Providers\AzureTranslatorProvider::class,
            'key' => env('AZURE_TRANSLATOR_KEY'),
            'region' => env('AZURE_TRANSLATOR_REGION'),
            'url' => env('AZURE_TRANSLATOR_URL', 'https://api.cognitive.microsofttranslator.com/translate'),
        ],

        'amazon' => [
            'driver' => \App\Services\Translation\Providers\AmazonTranslateProvider::class,
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'eu-west-1'),
        ],

        'libretranslate' => [
            'driver' => \App\Services\Translation\Providers\LibreTranslateProvider::class,
            'url' => env('LIBRETRANSLATE_URL', 'http://localhost:5000/translate'),
            'key' => env('LIBRETRANSLATE_API_KEY'),
        ],

        // Réutilise votre DeepSeekService existant comme moteur de traduction.
        'deepseek' => [
            'driver' => \App\Services\Translation\Providers\DeepSeekTranslationProvider::class,
        ],

        // Aucun appel externe : utile en local/CI sans clé API.
        'null' => [
            'driver' => \App\Services\Translation\Providers\NullProvider::class,
        ],
    ],

    'source_locale' => env('TRANSLATION_SOURCE_LOCALE', 'fr'),
    'supported_locales' => ['fr', 'en', 'es', 'de'],
    'eager_locales' => ['en'],
    'cache_ttl_ui_days' => 365,
    'cache_ttl_content_days' => 30,
];
