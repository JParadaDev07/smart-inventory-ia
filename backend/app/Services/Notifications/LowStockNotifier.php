<?php

namespace App\Services\Notifications;

use App\Models\Business;
use App\Models\Product;
use App\Models\WhatsappNotification;
use App\Services\Inventory\InventoryAnalysisService;

class LowStockNotifier
{
    public function __construct(
        private WhatsAppService $whatsApp,
        private InventoryAnalysisService $inventoryAnalysis
    ) {
    }

    /**
     * Notify business owner about low stock for the given product.
     * Idempotent per product per day.
     */
    public function notify(Business $business, Product $product): void
    {
        if (!$business->isOnProPlan() || !$business->whatsapp_enabled) {
            return;
        }

        if (!$this->whatsApp->isEnabled()) {
            return;
        }

        $to = $business->whatsapp_number ?: config('services.whatsapp.admin_default');
        if (!$to) {
            return;
        }

        // Avoid spamming: only one low-stock notification per product per day
        $today = now()->toDateString();
        $already = WhatsappNotification::query()
            ->where('business_id', $business->id)
            ->where('product_id', $product->id)
            ->where('type', 'low_stock')
            ->whereDate('last_sent_at', $today)
            ->exists();

        if ($already) {
            return;
        }

        $intel = $this->inventoryAnalysis->getIntelligence($product, 30, $business->id);

        $body = sprintf(
            "Hola, tu ferretería %s tiene stock bajo de %s.\n".
            "Stock actual: %d / Mínimo: %d.\n".
            "Recomendación local: comprar %d uds. Stock estimado hasta: %s.\n\n".
            "¿Qué deseas hacer?",
            $business->name,
            $product->name,
            $product->current_stock,
            $product->minimum_stock,
            $intel['recommended_purchase_quantity'] ?? 0,
            $intel['estimated_stock_out_date'] ?? 'N/D'
        );

        $this->whatsApp->sendLowStockMenu($to, $body);

        WhatsappNotification::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'type' => 'low_stock',
            'status' => 'sent',
            'recipient' => $to,
            'meta' => [
                'recommended_purchase_quantity' => $intel['recommended_purchase_quantity'] ?? null,
            ],
            'last_sent_at' => now(),
        ]);
    }
}

