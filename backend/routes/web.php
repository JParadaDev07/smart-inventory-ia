<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name', 'Smart Inventory API'),
        'environment' => config('app.env'),
        'version' => '1.0',
        'message' => 'Bienvenido a la API de Smart Inventory AI.',
        'base_url' => url('/'),
        'api_base' => url('/api'),
        'endpoints' => [
            'auth' => [
                'POST /api/auth/register',
                'POST /api/auth/login',
                'POST /api/auth/forgot-password',
                'POST /api/auth/reset-password',
            ],
            'billing' => [
                'GET /api/plans',
                'POST /api/billing/webhook',
                'POST /api/billing/create-payment',
            ],
            'products' => [
                'GET /api/products',
                'POST /api/products',
                'GET /api/products/{id}',
                'PUT /api/products/{id}',
                'DELETE /api/products/{id}',
                'POST /api/products/{product}/stock-adjustment',
                'GET /api/products/{id}/intelligence',
                'GET /api/products/{id}/ai-prediction',
            ],
            'sales' => [
                'GET /api/sales',
                'POST /api/sales',
                'GET /api/sales/{id}',
                'DELETE /api/sales/{id}',
            ],
            'alerts' => [
                'GET /api/alerts',
            ],
            'subscription' => [
                'GET /api/subscription',
                'PUT /api/subscription',
            ],
            'tickets' => [
                'GET /api/tickets',
                'POST /api/tickets',
                'GET /api/tickets/{ticket}',
                'POST /api/tickets/{ticket}/messages',
                'GET /api/admin/tickets',
                'GET /api/admin/tickets/{ticket}',
                'POST /api/admin/tickets/{ticket}/messages',
                'PATCH /api/admin/tickets/{ticket}',
            ],
            'webhooks' => [
                'POST /api/webhooks/wompi',
            ],
        ],
        'docs' => null,
    ]);
});

