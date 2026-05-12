<?php

return [
    'currency' => 'COP',
    'basic' => [
        'price' => (int) env('PLAN_BASIC_PRICE', 19900),
        'name' => 'Basic',
    ],
    'pro' => [
        'price' => (int) env('PLAN_PRO_PRICE', 39900),
        'name' => 'Pro',
    ],
    'enterprise' => [
        'price' => (int) env('PLAN_ENTERPRISE_PRICE', 79999),
        'name' => 'Enterprise',
    ],
];
