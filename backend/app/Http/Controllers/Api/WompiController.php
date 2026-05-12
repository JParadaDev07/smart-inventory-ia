<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Billing\SubscriptionService;
use App\Services\WompiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WompiController extends Controller
{
    public function __construct(
        private WompiService $wompi,
        private SubscriptionService $subscriptionService
    ) {}

    /**
     * POST /api/payments/wompi/checkout
     * Crea pago y retorna checkout_url.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);
        $amount = (int) $validated['amount'];

        $currency = config('services.wompi.currency', 'COP');
        $reference = (string) Str::uuid();
        $amountInCents = $amount * 100;

        if (empty(config('services.wompi.public_key')) || empty(config('services.wompi.integrity_secret'))) {
            Log::warning('Wompi checkout: public_key o integrity_secret no configurados');
            return response()->json(['message' => 'Payment gateway not configured.'], 503);
        }

        if (empty(config('services.wompi.redirect_url'))) {
            Log::warning('Wompi checkout: redirect_url no configurado');
            return response()->json(['message' => 'Redirect URL not configured.'], 503);
        }

        try {
            $payment = DB::transaction(function () use ($reference, $amount, $amountInCents, $currency) {
                return Payment::withoutGlobalScopes()->create([
                    'reference' => $reference,
                    'amount' => $amount,
                    'amount_in_cents' => $amountInCents,
                    'currency' => $currency,
                    'status' => 'pending',
                    'raw_response' => null,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Wompi checkout: error creando payment', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Error creating payment.'], 500);
        }

        try {
            $checkoutUrl = $this->wompi->buildCheckoutUrl($reference, $amountInCents, $currency);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Wompi checkout: invalid public_key', ['message' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json([
            'checkout_url' => $checkoutUrl,
        ]);
    }

    /**
     * POST /api/webhooks/wompi
     * Recibe evento transaction.updated y actualiza payment. Si APPROVED, verifica con GET antes de marcar paid.
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::channel('single')->info('Wompi webhook received', ['payload' => $payload]);

        if (!$this->wompi->verifyWebhookSignature($payload)) {
            Log::warning('Wompi webhook: firma inválida');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = $payload['event'] ?? null;
        if ($event !== 'transaction.updated') {
            return response()->json([], 200);
        }

        $transaction = $payload['data']['transaction'] ?? null;
        if (!$transaction) {
            return response()->json([], 200);
        }

        $reference = $transaction['reference'] ?? null;
        $transactionId = $transaction['id'] ?? null;
        $status = strtoupper((string) ($transaction['status'] ?? ''));

        if (empty($reference)) {
            return response()->json([], 200);
        }

        $payment = Payment::withoutGlobalScopes()->where('reference', $reference)->first();
        if (!$payment) {
            Log::warning('Wompi webhook: payment no encontrado', ['reference' => $reference]);
            return response()->json([], 200);
        }

        $internalStatus = $this->mapWompiStatusToInternal($status);

        if ($status === 'APPROVED') {
            $verified = $this->wompi->fetchTransaction((string) $transactionId);
            if ($verified && $this->wompi->verifyTransactionApproved(
                $verified,
                $payment->reference,
                (int) $payment->amount_in_cents
            )) {
                $internalStatus = 'paid';
            } else {
                Log::warning('Wompi webhook: verificación GET falló para APPROVED', ['reference' => $reference]);
                $internalStatus = 'pending';
            }
        }

        $wasAlreadyApproved = $payment->isApproved();

        try {
            $payment->update([
                'status' => $internalStatus,
                'wompi_transaction_id' => $transactionId,
                'wompi_status' => $status,
                'raw_response' => $transaction,
            ]);
        } catch (\Throwable $e) {
            Log::error('Wompi webhook: error actualizando payment', ['reference' => $reference, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'Error updating payment.'], 500);
        }

        // If this was a subscription payment (billing/create-payment), activate the plan
        if ($internalStatus === 'paid' && !$wasAlreadyApproved && $payment->business_id && $payment->plan) {
            try {
                $this->subscriptionService->activateFromPayment(
                    (int) $payment->business_id,
                    $payment->plan,
                    30
                );
                Log::info('Wompi webhook: subscription activated', ['reference' => $reference, 'plan' => $payment->plan]);
            } catch (\Throwable $e) {
                Log::error('Wompi webhook: error activating subscription', ['reference' => $reference, 'message' => $e->getMessage()]);
            }
        }

        return response()->json([], 200);
    }

    private function mapWompiStatusToInternal(string $wompiStatus): string
    {
        return match (strtoupper($wompiStatus)) {
            'APPROVED' => 'paid',
            'DECLINED' => 'declined',
            'ERROR' => 'error',
            'VOIDED' => 'voided',
            default => 'pending',
        };
    }
}
