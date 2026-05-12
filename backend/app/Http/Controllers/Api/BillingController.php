<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Billing\SubscriptionCheckoutService;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\WompiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class BillingController extends Controller
{
    public function __construct(
        private SubscriptionCheckoutService $subscriptionCheckout,
        private WompiService $wompi,
        private SubscriptionService $subscriptionService
    ) {}

    /**
     * Public plan info (prices in COP) for display.
     */
    public function plans(Request $request): JsonResponse
    {
        return response()->json([
            'currency' => config('plans.currency', 'COP'),
            'basic' => [
                'name' => config('plans.basic.name', 'Basic'),
                'price' => (int) config('plans.basic.price', 39900),
            ],
            'pro' => [
                'name' => config('plans.pro.name', 'Pro'),
                'price' => (int) config('plans.pro.price', 79900),
            ],
            'enterprise' => [
                'name' => config('plans.enterprise.name', 'Enterprise'),
                'price' => (int) config('plans.enterprise.price', 79999),
            ],
        ]);
    }

    /**
     * Create a subscription payment and return Wompi Web Checkout URL.
     * Validates Wompi config and production rules (HTTPS redirect, prod keys) when WOMPI_ENVIRONMENT=production.
     */
    public function createPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::in(['basic', 'pro', 'enterprise'])],
        ]);
        $plan = $validated['plan'];

        $user = $request->user();
        $business = $user->business;
        if (! $business) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        Log::info('Billing createPayment started', [
            'user_id' => $user->id,
            'business_id' => $business->id,
            'plan' => $plan,
            'wompi_environment' => config('wompi.environment'),
            'wompi_is_production' => config('wompi.is_production'),
        ]);

        try {
            $result = $this->subscriptionCheckout->createSubscriptionCheckout($business, $plan);
        } catch (ValidationException $e) {
            $message = $e->errors()['gateway'][0] ?? 'Payment gateway is not configured. Please contact support.';
            Log::warning('Billing createPayment validation error', [
                'plan' => $plan,
                'message' => $message,
            ]);
            return response()->json(['message' => $message], 503);
        } catch (InvalidArgumentException $e) {
            Log::error('Billing createPayment invalid Wompi configuration', [
                'plan' => $plan,
                'message' => $e->getMessage(),
            ]);
            return response()->json(['message' => $e->getMessage()], 503);
        }

        Log::info('Billing createPayment success', [
            'plan' => $plan,
            'reference' => $result['reference'] ?? null,
        ]);

        return response()->json([
            'checkout_url' => $result['checkout_url'],
            'reference' => $result['reference'],
            'widget_params' => $result['widget_params'] ?? null,
        ]);
    }

    /**
     * Wompi webhook: validate signature, find payment by reference, update payment and subscription on APPROVED.
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::channel('single')->info('Wompi webhook received', ['payload' => $payload]);

        if (! $this->wompi->verifyEventSignature($payload)) {
            Log::warning('Wompi webhook invalid signature');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = $payload['event'] ?? null;
        if ($event !== 'transaction.updated') {
            return response()->json([], 200);
        }

        $transaction = $payload['data']['transaction'] ?? null;
        if (! $transaction) {
            return response()->json([], 200);
        }

        $reference = $transaction['reference'] ?? null;
        $status = strtoupper((string) ($transaction['status'] ?? ''));
        $transactionId = $transaction['id'] ?? null;

        if (empty($reference)) {
            return response()->json([], 200);
        }

        $payment = Payment::where('reference', $reference)->first();
        if (! $payment) {
            Log::warning('Wompi webhook: payment not found', ['reference' => $reference]);
            return response()->json([], 200);
        }

        $wasAlreadyApproved = $payment->isApproved();

        DB::transaction(function () use ($payment, $transactionId, $status, $transaction, $wasAlreadyApproved) {
            $payment->update([
                'wompi_transaction_id' => $transactionId,
                'wompi_status' => $status,
                'raw_response' => $transaction,
            ]);

            if ($status === 'APPROVED' && ! $wasAlreadyApproved) {
                $this->subscriptionService->activateFromPayment(
                    $payment->business_id,
                    $payment->plan,
                    30
                );
            }
        });

        return response()->json([], 200);
    }
}
