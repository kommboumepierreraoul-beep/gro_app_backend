<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'api_key' => env('AI_API_KEY'),
    'base_url' => env('AI_BASE_URL', 'https://api.groq.com/openai/v1'),
    'model' => env('AI_MODEL', 'llama-3.3-70b-versatile'),
    'timeout' => env('AI_TIMEOUT', 60),
    'provider' => env('AI_PROVIDER', 'groq'),

    /*
    |--------------------------------------------------------------------------
    | Context Types
    |--------------------------------------------------------------------------
    */
    'context_types' => [
        'general' => 'General',
        'post' => 'Post',
        'mission' => 'Mission',
        'comment' => 'Comment',
    ],

    /*
    |--------------------------------------------------------------------------
    | System Prompts
    |--------------------------------------------------------------------------
    */
    'system_prompts' => [
        'chat' => env('AI_SYSTEM_PROMPT_CHAT', "Tu es AgriPulse AI, l'assistant intelligent..."),
        'improve' => env('AI_SYSTEM_PROMPT_IMPROVE', "Améliore le texte suivant..."),
        'summarize' => env('AI_SYSTEM_PROMPT_SUMMARIZE', "Résume cette discussion..."),
        'moderate' => env('AI_SYSTEM_PROMPT_MODERATE', "Analyse ce contenu..."),
        'context' => env('AI_SYSTEM_PROMPT_CONTEXT', "Tu es AgriPulse AI, assistant spécialisé..."),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'max_attempts' => env('AI_RATE_LIMIT_ATTEMPTS', 60),
        'decay_minutes' => env('AI_RATE_LIMIT_DECAY', 1),
    ],
];
