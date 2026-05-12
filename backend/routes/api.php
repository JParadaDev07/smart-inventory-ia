<?php

use App\Http\Controllers\Api\AlertsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductIntelligenceController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\WompiController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketAdminController;
use Illuminate\Support\Facades\Route;

Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
Route::get('auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->name('verification.verify');

Route::post('billing/webhook', [BillingController::class, 'webhook']);
Route::get('plans', [BillingController::class, 'plans']);

Route::post('payments/wompi/checkout', [WompiController::class, 'checkout']);
Route::post('webhooks/wompi', [WompiController::class, 'webhook']);
Route::get('webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/user', [AuthController::class, 'user']);
    Route::put('auth/user', [AuthController::class, 'update']);

    // Tickets de soporte (usuario actual)
    Route::get('tickets', [TicketController::class, 'index']);
    Route::post('tickets', [TicketController::class, 'store']);
    Route::get('tickets/{ticket}', [TicketController::class, 'show']);
    Route::post('tickets/{ticket}/messages', [TicketController::class, 'addMessage']);

    // Tickets de soporte (admin global)
    Route::get('admin/tickets', [TicketAdminController::class, 'index']);
    Route::get('admin/tickets/{ticket}', [TicketAdminController::class, 'show']);
    Route::post('admin/tickets/{ticket}/messages', [TicketAdminController::class, 'addMessage']);
    Route::patch('admin/tickets/{ticket}', [TicketAdminController::class, 'update']);
    Route::post('auth/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->name('verification.send');

    Route::middleware('verified')->group(function () {
        Route::get('dashboard', DashboardController::class);

    Route::middleware('subscription')->group(function () {
        Route::get('branches', [BranchController::class, 'index']);
        Route::post('branches', [BranchController::class, 'store']);
        Route::get('alerts', AlertsController::class);
        Route::apiResource('products', ProductController::class);
        Route::post('products/{product}/stock-adjustment', [ProductController::class, 'adjustStock']);
        Route::get('products/{id}/intelligence', [ProductIntelligenceController::class, 'show'])
            ->name('products.intelligence');
        Route::middleware('subscription:pro')->group(function () {
            Route::get('products/{id}/ai-prediction', [ProductIntelligenceController::class, 'aiPrediction']);
        });
        Route::get('sales', [SaleController::class, 'index']);
        Route::get('sales/{id}', [SaleController::class, 'show']);
        Route::post('sales', [SaleController::class, 'store']);
        Route::delete('sales/{id}', [SaleController::class, 'destroy']);
    });

        Route::get('subscription', [SubscriptionController::class, 'show']);
        Route::put('subscription', [SubscriptionController::class, 'update']);
        Route::post('billing/create-payment', [BillingController::class, 'createPayment']);
    });
});
