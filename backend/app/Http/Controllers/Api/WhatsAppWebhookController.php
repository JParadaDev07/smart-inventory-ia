<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use App\Models\WhatsappNotification;
use App\Repositories\ProductRepository;
use App\Services\Notifications\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private WhatsAppService $whatsApp,
        private ProductRepository $products,
    ) {
    }

    // Meta verification (GET)
    public function verify(Request $request): JsonResponse
    {
        $verifyToken = config('services.whatsapp.verify_token');

        if (
            $request->get('hub_mode') === 'subscribe' &&
            $request->get('hub_verify_token') === $verifyToken
        ) {
            return response()->json((int) $request->get('hub_challenge', 0));
        }

        return response()->json(['message' => 'Invalid verify token.'], 403);
    }

    // Incoming messages (POST)
    public function handle(Request $request): JsonResponse
    {
        $data = $request->all();

        try {
            $entry = $data['entry'][0]['changes'][0]['value'] ?? null;
            if (!$entry || empty($entry['messages'][0])) {
                return response()->json(['status' => 'ignored']);
            }

            $message = $entry['messages'][0];
            $from = $message['from'];

            $business = $this->findBusinessByWhatsapp($from);
            if (!$business) {
                return response()->json(['status' => 'ignored']);
            }

            // Handle interactive reply (buttons)
            if (($message['type'] ?? null) === 'interactive') {
                $btnId = $message['interactive']['button_reply']['id'] ?? null;
                if ($btnId === 'REMIND_LATER') {
                    $this->handleRemindLater($business, $from);
                } elseif ($btnId === 'ADJUST_STOCK') {
                    $this->whatsApp->sendText($from, 'Responde con la cantidad a reponer para este producto (solo número).');
                }
            } elseif (($message['type'] ?? null) === 'text') {
                $this->handleTextQuantity($business, $from, $message['text']['body'] ?? '');
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp webhook error', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'ok']);
    }

    private function findBusinessByWhatsapp(string $from): ?Business
    {
        // from comes as number with country code, e.g. "573001112233"
        return Business::query()
            ->where('whatsapp_number', $from)
            ->first();
    }

    private function handleRemindLater(Business $business, string $from): void
    {
        $notification = WhatsappNotification::query()
            ->where('business_id', $business->id)
            ->where('type', 'low_stock')
            ->latest('last_sent_at')
            ->first();

        if (!$notification) {
            $this->whatsApp->sendText($from, 'No encontré una alerta de stock reciente para recordar.');
            return;
        }

        $notification->update([
            'next_run_at' => now()->addHours(2),
            'status' => 'scheduled',
        ]);

        $this->whatsApp->sendText($from, 'Perfecto, te lo recordaré de nuevo en aproximadamente 2 horas.');
    }

    private function handleTextQuantity(Business $business, string $from, string $body): void
    {
        $qty = (int) trim($body);
        if ($qty <= 0) {
            $this->whatsApp->sendText($from, 'Por favor envía solo la cantidad positiva que quieres reponer (por ejemplo: 50).');
            return;
        }

        $notification = WhatsappNotification::query()
            ->where('business_id', $business->id)
            ->where('type', 'low_stock')
            ->latest('last_sent_at')
            ->first();

        if (!$notification || !$notification->product_id) {
            $this->whatsApp->sendText($from, 'No encontré una alerta de stock para ajustar en este momento.');
            return;
        }

        $product = $this->products->findForBusiness($notification->product_id, $business->id);
        if (!$product) {
            $this->whatsApp->sendText($from, 'El producto ya no está disponible.');
            return;
        }

        // Reponer qty unidades (ajuste positivo)
        $product->current_stock += $qty;
        $product->save();

        $notification->update([
            'status' => 'completed',
        ]);

        $this->whatsApp->sendText(
            $from,
            sprintf('Listo, ajusté el stock de %s. Nuevo stock: %d.', $product->name, $product->current_stock)
        );
    }
}

