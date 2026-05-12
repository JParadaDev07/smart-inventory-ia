<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.whatsapp.enabled', false)
            && config('services.whatsapp.phone_id')
            && config('services.whatsapp.access_token');
    }

    public function sendText(string $to, string $message): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->send([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $message,
            ],
        ]);
    }

    /**
    * Send an interactive low-stock menu with two options:
    * - REMIND_LATER
    * - ADJUST_STOCK
    */
    public function sendLowStockMenu(string $to, string $body): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => $body,
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'REMIND_LATER',
                                'title' => 'Recordar después',
                            ],
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'ADJUST_STOCK',
                                'title' => 'Reajustar stock',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->send($payload);
    }

    private function send(array $payload): void
    {
        $phoneId = config('services.whatsapp.phone_id');
        $token = config('services.whatsapp.access_token');
        $endpoint = "https://graph.facebook.com/v20.0/{$phoneId}/messages";

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->post($endpoint, $payload);

            if (!$response->successful()) {
                Log::warning('WhatsApp API request failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp API exception', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}

