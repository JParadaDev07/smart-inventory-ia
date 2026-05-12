<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Business;
use Carbon\Carbon;
use App\Repositories\ProductRepository;
use App\Services\Inventory\InventoryAnalysisService;
use App\Services\Notifications\LowStockNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertsController extends Controller
{
    public function __construct(
        private ProductRepository $productRepository,
        private InventoryAnalysisService $analysisService,
        private LowStockNotifier $lowStockNotifier
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $businessId = $request->user()->business_id;
        /** @var Business $business */
        $business = $request->user()->business;

        $lowStock = $this->productRepository->lowStockForBusiness($businessId);
        $allProducts = $this->productRepository->allForBusiness($businessId);

        $recommended = [];
        foreach ($allProducts as $product) {
            $qty = $this->analysisService->recommendedPurchase($product, 30, $businessId);
            if ($qty > 0) {
                $recommended[] = [
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'current_stock' => $product->current_stock,
                        'minimum_stock' => $product->minimum_stock,
                    ],
                    'recommended_quantity' => $qty,
                ];
            }
        }

        // Trigger WhatsApp notifications (Pro only, idempotent per product/day)
        foreach ($lowStock as $product) {
            $this->lowStockNotifier->notify($business, $product);
        }

        // Bonus: batches expiring soon (based on product.expiration_alert_days).
        // We fetch a limited window (next 30 days) and then filter per product threshold in PHP
        // to keep logic DB-agnostic for SQLite/tests.
        $today = Carbon::today();
        $windowEnd = $today->copy()->addDays(30);
        $expiringSoon = [];

        $candidateBatches = Batch::query()
            ->with(['product:id,name,sku,perecedero,expiration_alert_days', 'branch:id,name'])
            ->where('business_id', $businessId)
            ->whereNotNull('expiration_date')
            ->whereBetween('expiration_date', [$today->toDateString(), $windowEnd->toDateString()])
            ->orderBy('expiration_date')
            ->get();

        foreach ($candidateBatches as $batch) {
            $p = $batch->product;
            if (!$p || !$p->perecedero) continue;

            $thresholdDays = (int) ($p->expiration_alert_days ?? 7);
            $batchDate = Carbon::parse($batch->expiration_date)->startOfDay();

            // days_until >= 0 because we limited to [today, today+30]
            $daysUntil = (int) $today->diffInDays($batchDate, false);
            if ($daysUntil <= $thresholdDays) {
                $expiringSoon[] = [
                    'batch_id' => $batch->id,
                    'product' => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'sku' => $p->sku,
                    ],
                    'branch' => [
                        'id' => (int) $batch->branch_id,
                        'name' => $batch->branch?->name ?? null,
                    ],
                    'quantity_available' => (int) $batch->quantity_available,
                    'expiration_date' => $batchDate->toDateString(),
                    'days_until' => $daysUntil,
                ];
            }
        }

        return response()->json([
            'low_stock' => $lowStock->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'current_stock' => $p->current_stock,
                'minimum_stock' => $p->minimum_stock,
            ])->values()->all(),
            'recommended_purchase' => $recommended,
            'expiring_soon' => $expiringSoon,
        ]);
    }
}
