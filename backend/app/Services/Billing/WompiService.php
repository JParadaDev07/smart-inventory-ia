<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\Log;

class WompiService
{
    /**
     * Build integrity signature for Widget/Web Checkout.
     * Concatenation: reference + amount_in_cents + currency + integrity_secret
     */
    public function buildSignature(string $reference, int $amountInCents, string $currency = 'COP'): string
    {
        $secret = config('wompi.integrity_secret');
        $string = $reference . $amountInCents . $currency . $secret;
        return hash('sha256', $string);
    }

    /**
     * Build checkout URL for redirect (Web Checkout).
     * Amount in COP pesos; converted to cents for Wompi (1 COP = 100 cents).
     * Omits redirect-url when it is localhost (CloudFront often blocks it and returns 403).
     * @throws \InvalidArgumentException if public_key is missing or invalid (would cause Wompi "merchants/undefined" error)
     */
    public function buildCheckoutUrl(string $reference, int $amountCop, string $redirectUrl, string $currency = 'COP'): string
    {
        $publicKey = config('wompi.public_key');
        if (empty($publicKey) || ! preg_match('/^pub_(test|prod)_/', (string) $publicKey)) {
            throw new \InvalidArgumentException('Wompi public_key is missing or invalid. Set WOMPI_PUBLIC_KEY or WOMPI_PUBLIC_KEY_PROD in .env');
        }
        $amountInCents = $amountCop * 100;
        $signature = $this->buildSignature($reference, $amountInCents, $currency);
        $params = [
            'public-key' => $publicKey,
            'currency' => $currency,
            'amount-in-cents' => $amountInCents,
            'reference' => $reference,
            'signature:integrity' => $signature,
        ];
        $base = rtrim(config('wompi.checkout_url'), '/');
        if ($redirectUrl !== '' && !$this->isLocalhostRedirect($redirectUrl)) {
            $params['redirect-url'] = $redirectUrl;
        }
        return $base . '?' . http_build_query($params);
    }

    private function isLocalhostRedirect(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        return $host === 'localhost' || $host === '127.0.0.1' || str_ends_with((string) $host, '.localhost');
    }

    /**
     * Verify Wompi event checksum (X-Event-Checksum or signature.checksum).
     * Concatenation: secret + timestamp + values of signature.properties in order.
     */
    public function verifyEventSignature(array $payload): bool
    {
        $secret = config('wompi.events_secret');
        if (empty($secret)) {
            Log::warning('Wompi events secret not configured');
            return false;
        }

        $signature = $payload['signature'] ?? null;
        if (!$signature || empty($signature['checksum']) || empty($signature['timestamp']) || empty($signature['properties'])) {
            return false;
        }

        $data = $payload['data'] ?? [];
        $parts = [];
        foreach ($signature['properties'] as $path) {
            $value = data_get($data, str_replace('.', '.', $path));
            $parts[] = $value ?? '';
        }
        $concatenated = $secret . $signature['timestamp'] . implode('', $parts);
        $expected = strtoupper(hash('sha256', $concatenated));
        $received = strtoupper((string) $signature['checksum']);

        return hash_equals($expected, $received);
    }
}
