<?php

return [
    'fastapi' => [
        'url' => env('FASTAPI_URL', 'http://localhost:8000'),
        'timeout' => (int) env('FASTAPI_TIMEOUT', 5),
    ],
    'ai' => [
        'enabled' => (bool) env('AI_ENABLED', true),
    ],
    'subscription' => [
        'plans' => [
            'basic' => 'Basic',
            'pro' => 'Pro',
            'enterprise' => 'Enterprise',
        ],
        'trial_days' => 14,
    ],
    'whatsapp' => [
        'phone_id' => env('WHATSAPP_PHONE_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'enabled' => (bool) env('WHATSAPP_ENABLED', false),
        'admin_default' => env('WHATSAPP_ADMIN_DEFAULT'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    ],
    'wompi' => config('wompi', []),
];
