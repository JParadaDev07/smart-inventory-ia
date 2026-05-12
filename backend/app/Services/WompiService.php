<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WompiService
{
    private function config(string $key, mixed $default = null): mixed
    {
        return config("services.wompi.{$key}", $default);
    }

    /**
     * Firma de integridad: reference + amountInCents + currency + WOMPI_INTEGRITY_SECRET
     * SHA256.
     */
    public function buildIntegritySignature(string $reference, int $amountInCents, string $currency): string
    {
        $secret = $this->config('integrity_secret');
        $string = $reference . $amountInCents . $currency . $secret;
        return hash('sha256', $string);
    }

    /**
     * URL de checkout con parámetros exactos. redirect-url desde config.
     * @throws \InvalidArgumentException if public_key is missing or invalid (avoids Wompi "merchants/undefined")
     */
    public function buildCheckoutUrl(string $reference, int $amountInCents, string $currency): string
    {
        $publicKey = $this->config('public_key');
        if (empty($publicKey) || ! preg_match('/^pub_(test|prod)_/', (string) $publicKey)) {
            throw new \InvalidArgumentException('Wompi public_key is missing or invalid.');
        }
        $signature = $this->buildIntegritySignature($reference, $amountInCents, $currency);
        $params = [
            'public-key' => $publicKey,
            'currency' => $currency,
            'amount-in-cents' => $amountInCents,
            'reference' => $reference,
            'redirect-url' => $this->config('redirect_url'),
            'signature:integrity' => $signature,
        ];
        $base = rtrim($this->config('checkout_url'), '/');
        return $base . '?' . http_build_query($params);
    }

    /**
     * Valida firma del webhook según documentación Wompi.
     * secret + timestamp + valores de signature.properties en orden.
     */
    public function verifyWebhookSignature(array $payload): bool
    {
        $secret = $this->config('events_secret');
        if (empty($secret)) {
            Log::warning('Wompi events_secret no configurado');
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

    /**
     * GET transaction desde API Wompi para verificación extra (APPROVED).
     * Bearer WOMPI_PRIVATE_KEY.
     */
    public function fetchTransaction(string $transactionId): ?array
    {
        $baseUrl = $this->config('api_url');
        $token = $this->config('private_key');
        if (empty($baseUrl) || empty($token)) {
            Log::warning('Wompi api_url o private_key no configurados');
            return null;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->get("{$baseUrl}/transactions/{$transactionId}");

            if (!$response->successful()) {
                Log::warning('Wompi GET transaction falló', ['id' => $transactionId, 'status' => $response->status()]);
                return null;
            }

            return $response->json('data');
        } catch (\Throwable $e) {
            Log::error('Wompi fetchTransaction exception', ['id' => $transactionId, 'message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Verifica que la transacción en API coincida: status APPROVED, amount_in_cents, reference.
     */
    public function verifyTransactionApproved(array $transaction, string $expectedReference, int $expectedAmountInCents): bool
    {
        $status = strtoupper((string) ($transaction['status'] ?? ''));
        $reference = $transaction['reference'] ?? '';
        $amountInCents = (int) ($transaction['amount_in_cents'] ?? 0);

        return $status === 'APPROVED'
            && $reference === $expectedReference
            && $amountInCents === $expectedAmountInCents;
    }
}
