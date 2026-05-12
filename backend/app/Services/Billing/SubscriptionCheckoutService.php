<?php

namespace App\Services\Billing;

use App\Models\Business;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionCheckoutService
{
    public function __construct(
        private WompiService $wompi
    ) {}

    /**
     * Validate Wompi config for the current environment.
     * In production, ensures prod keys and a valid redirect URL.
     */
    public function validateConfig(): void
    {
        Log::info('Subscription checkout validateConfig', [
            'wompi_environment' => config('wompi.environment'),
            'wompi_is_production' => config('wompi.is_production'),
        ]);

        $publicKey = config('wompi.public_key');
        $integritySecret = config('wompi.integrity_secret');
        if (empty($publicKey) || empty($integritySecret)) {
            throw ValidationException::withMessages([
                'gateway' => ['Payment gateway is not configured. Please contact support.'],
            ]);
        }

        if (! config('wompi.is_production')) {
            return;
        }

        if (! str_starts_with((string) $publicKey, 'pub_prod_')) {
            Log::warning('Subscription checkout: WOMPI_ENVIRONMENT=production but public key is not production', [
                'key_prefix' => substr($publicKey, 0, 12),
            ]);
            throw ValidationException::withMessages([
                'gateway' => ['Production payment keys are not configured correctly.'],
            ]);
        }

        $redirectUrl = $this->resolveRedirectUrl();
        if (empty($redirectUrl)) {
            Log::warning('Subscription checkout: production requires WOMPI_REDIRECT_URL or FRONTEND_URL');
            throw ValidationException::withMessages([
                'gateway' => ['Redirect URL is required for production. Set WOMPI_REDIRECT_URL or FRONTEND_URL.'],
            ]);
        }

        if (! $this->isAllowedRedirectUrl($redirectUrl)) {
            Log::warning('Subscription checkout: production redirect URL should be HTTPS', ['url' => $redirectUrl]);
            throw ValidationException::withMessages([
                'gateway' => ['Production redirect URL must use HTTPS (or a valid tunnel URL).'],
            ]);
        }
    }

    /**
     * Create subscription payment and return checkout URL.
     * Validates config (including production rules) before creating the payment.
     *
     * @return array{payment: Payment, checkout_url: string, reference: string}
     */
    public function createSubscriptionCheckout(Business $business, string $plan): array
    {
        $this->validateConfig();

        $price = (int) config("plans.{$plan}.price");
        if ($price <= 0) {
            throw ValidationException::withMessages([
                'plan' => ['Plan is not configured.'],
            ]);
        }

        $currency = config('plans.currency', 'COP');
        $reference = (string) Str::uuid();
        $amountInCents = $price * 100;
        $redirectUrl = $this->resolveRedirectUrl();

        $payment = Payment::create([
            'business_id' => $business->id,
            'plan' => $plan,
            'amount' => $price,
            'amount_in_cents' => $amountInCents,
            'currency' => $currency,
            'reference' => $reference,
            'wompi_status' => 'PENDING',
            'raw_response' => [],
        ]);

        $publicKey = config('wompi.public_key');
        $signatureIntegrity = $this->wompi->buildSignature($reference, $amountInCents, $currency);
        $checkoutUrl = $this->wompi->buildCheckoutUrl($reference, $price, $redirectUrl ?? '', $currency);

        if (config('wompi.is_production')) {
            Log::info('Subscription checkout (production)', [
                'reference' => $reference,
                'plan' => $plan,
                'environment' => config('wompi.environment'),
            ]);
        }

        $widgetRedirectUrl = $this->redirectUrlSafeForWidget($redirectUrl);
        $widgetParams = [
            'public_key' => $publicKey,
            'amount_in_cents' => $amountInCents,
            'reference' => $reference,
            'signature_integrity' => $signatureIntegrity,
            'currency' => $currency,
            'redirect_url' => $widgetRedirectUrl,
        ];

        return [
            'payment' => $payment,
            'checkout_url' => $checkoutUrl,
            'reference' => $reference,
            'widget_params' => $widgetParams,
        ];
    }

    /**
     * For the widget request to Wompi/CloudFront: omit redirect_url when it is localhost or
     * non-HTTPS in production, to avoid 403 (CloudFront blocks those).
     */
    private function redirectUrlSafeForWidget(?string $redirectUrl): ?string
    {
        if (empty($redirectUrl)) {
            return null;
        }
        $host = parse_url($redirectUrl, PHP_URL_HOST);
        $scheme = strtolower((string) parse_url($redirectUrl, PHP_URL_SCHEME));
        if ($host === 'localhost' || $host === '127.0.0.1' || str_ends_with((string) $host, '.localhost')) {
            return null;
        }
        if (config('wompi.is_production') && $scheme !== 'https') {
            return null;
        }
        return $redirectUrl;
    }

    /**
     * Redirect URL: WOMPI_REDIRECT_URL if set, otherwise FRONTEND_URL + /payment/return.
     */
    private function resolveRedirectUrl(): ?string
    {
        $url = config('wompi.redirect_url');
        if (! empty($url)) {
            return rtrim($url, '/');
        }
        $frontend = rtrim(config('app.frontend_url', config('app.url')), '/');
        if (empty($frontend)) {
            return null;
        }
        return $frontend . '/payment/return';
    }

    /**
     * For production: allow HTTPS, tunnel (ngrok), or localhost when APP_ENV is local/testing (development).
     * For test: allow any.
     */
    private function isAllowedRedirectUrl(string $url): bool
    {
        if (! config('wompi.is_production')) {
            return true;
        }
        $appEnv = config('app.env', 'production');
        if (in_array($appEnv, ['local', 'testing'], true)) {
            return true; // allow localhost redirect when developing with production keys
        }
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme === 'https') {
            return true;
        }
        if (in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with((string) $host, '.localhost')) {
            return false;
        }
        if (str_ends_with((string) $host, '.ngrok-free.app') || str_ends_with((string) $host, '.ngrok.io')) {
            return true;
        }
        return false;
    }
}
