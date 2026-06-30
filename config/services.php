<?php
return [
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'resend' => [
        'key' => env('RESEND_KEY'),
    ],
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_NOTIFICATION_CHANNEL'),
        ],
    ],
    'notchpay' => [
        'api_key' => env('NOTCHPAY_API_KEY'),
        'api_secret' => env('NOTCHPAY_API_SECRET'),
    ],
    'monetbil' => [
        'service_key' => env('MONETBIL_SERVICE_KEY'),
        'service_secret' => env('MONETBIL_SERVICE_SECRET'),
    ],
    'ai' => [
        'api_key'       => env('AI_API_KEY'),
        'base_url'      => env('AI_BASE_URL'),
        'default_model' => env('AI_DEFAULT_MODEL'),
        'max_tokens'    => env('AI_MAX_TOKENS', 2048),
        'temperature'   => env('AI_TEMPERATURE', 0.7),
    ],
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
    ],
];
