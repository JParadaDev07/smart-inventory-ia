<?php

$environment = env('WOMPI_ENVIRONMENT', 'test');
$isProduction = $environment === 'production';

return [
    'environment' => $environment,
    'is_production' => $isProduction,

    'url' => env('WOMPI_URL', 'https://production.wompi.co'),
    'checkout_url' => rtrim(env('WOMPI_CHECKOUT_URL', 'https://checkout.wompi.co/p/'), '/'),
    'api_url' => $isProduction ? 'https://production.wompi.co/v1' : 'https://sandbox.wompi.co/v1',
    'currency' => env('PSE_CURRENCY', 'COP'),
    'redirect_url' => env('WOMPI_REDIRECT_URL'),

    'public_key' => $isProduction ? env('WOMPI_PUBLIC_KEY_PROD') : env('WOMPI_PUBLIC_KEY'),
    'private_key' => $isProduction ? env('WOMPI_PRIVATE_KEY_PROD') : env('WOMPI_PRIVATE_KEY'),
    'integrity_secret' => $isProduction ? env('WOMPI_INTEGRITY_SECRET_PROD') : env('WOMPI_INTEGRITY_SECRET'),
    'events_secret' => $isProduction ? env('WOMPI_EVENTS_SECRET_PROD') : env('WOMPI_EVENTS_SECRET'),
];
