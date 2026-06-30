<?php

return [
    'sandbox' => env('NOTCHPAY_SANDBOX', true),
    'public_key' => env('NOTCHPAY_PUBLIC_KEY'),
    'secret_key' => env('NOTCHPAY_SECRET_KEY'),
    'endpoint' => env('NOTCHPAY_ENDPOINT', 'https://api.notchpay.co'),
    'webhook_hash' => env('NOTCHPAY_WEBHOOK_HASH'),
    'callback_url' => env('NOTCHPAY_CALLBACK_URL'),
    'return_url' => env('NOTCHPAY_RETURN_URL'),
];
