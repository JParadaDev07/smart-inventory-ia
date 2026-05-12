<?php

namespace App\Services\Inventory;

use App\Models\Batch;
use App\Models\Product;
use App\Models\SaleItem;
use Carbon\Carbon;

class InventoryAnalysisService
{
    public function averageDailyDemand(int $productId, int $days = 30, ?int $businessId = null): float
    {
        $total = SaleItem::query()
            ->where('product_id', $productId)
            ->whereHas('sale', function ($q) use ($days, $businessId) {
                $q->where('created_at', '>=', now()->subDays($days));
                if ($businessId) {
                    $q->where('business_id', $businessId);
                }
            })
            ->sum('quantity');

        return $days > 0 ? round($total / $days, 2) : 0.0;
    }

    public function safetyStock(Product $product, int $days = 30, ?int $businessId = null): float
    {
        $d = $this->averageDailyDemand($product->id, $days, $businessId);
        return round($d * 3, 2);
    }

    public function reorderPoint(Product $product, int $days = 30, ?int $businessId = null): float
    {
        $d = $this->averageDailyDemand($product->id, $days, $businessId);
        $leadTime = $product->supplier_lead_time_days ?: 0;
        $dlt = $d * $leadTime;
        $ss = $this->safetyStock($product, $days, $businessId);
        return round($dlt + $ss, 2);
    }

    public function recommendedPurchase(Product $product, int $days = 30, ?int $businessId = null): int
    {
        $rop = $this->reorderPoint($product, $days, $businessId);
        $today = now()->startOfDay();
        $currentStockForReorder = $product->current_stock;

        if ((bool) $product->perecedero && $businessId) {
            $expiredMode = (string) ($product->expired_mode ?? 'permitir');
            $currentStockForReorder = $this->getCurrentSellableStockForPerishable($product, $businessId, $today, $expiredMode);
        }

        $recommended = (int) ceil($rop - $currentStockForReorder);
        return max(0, $recommended);
    }

    public function getIntelligence(Product $product, int $days = 30, ?int $businessId = null): array
    {
        $avgDaily = $this->averageDailyDemand($product->id, $days, $businessId);
        $safetyStock = $this->safetyStock($product, $days, $businessId);
        $reorderPoint = $this->reorderPoint($product, $days, $businessId);

        $today = now()->startOfDay();
        $expiredMode = (string) ($product->expired_mode ?? 'permitir');

        $estimatedStockOutDate = null;

        // For perishable products, estimate stock-out using batch-level availability
        // and the configured expired_mode. This prevents overestimating stock when early batches expire.
        $currentStockForReorder = $product->current_stock;
        if ((bool) $product->perecedero && $businessId) {
            $currentStockForReorder = $this->getCurrentSellableStockForPerishable($product, $businessId, $today, $expiredMode);
            $recommended = (int) max(0, ceil($reorderPoint - $currentStockForReorder));

            if ($avgDaily > 0 && $currentStockForReorder > 0) {
                $estimatedStockOutDate = $this->estimatePerishableStockOutDate($product, $businessId, $today, $avgDaily, $expiredMode);
            }
        } else {
            $recommended = (int) max(0, ceil($reorderPoint - $product->current_stock));
            if ($avgDaily > 0 && $product->current_stock > 0) {
                $daysUntilStockOut = (int) floor($product->current_stock / $avgDaily);
                $estimatedStockOutDate = now()->addDays($daysUntilStockOut)->toDateString();
            }
        }

        return [
            'average_daily_demand' => $avgDaily,
            'safety_stock' => $safetyStock,
            'reorder_point' => $reorderPoint,
            'recommended_purchase_quantity' => $recommended,
            'estimated_stock_out_date' => $estimatedStockOutDate,
        ];
    }

    private function getCurrentSellableStockForPerishable(Product $product, int $businessId, Carbon $today, string $expiredMode): int
    {
        $query = Batch::query()
            ->forBusiness($businessId)
            ->where('product_id', $product->id)
            ->where('quantity_available', '>', 0);

        if ($expiredMode === 'bloquear') {
            $query->where(function ($q) use ($today) {
                $q->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', $today->toDateString()); // allow expiration_date == today
            });
        }

        return (int) $query->sum('quantity_available');
    }

    private function estimatePerishableStockOutDate(
        Product $product,
        int $businessId,
        Carbon $today,
        float $avgDailyDemand,
        string $expiredMode
    ): ?string {
        $batches = Batch::query()
            ->forBusiness($businessId)
            ->where('product_id', $product->id)
            ->where('quantity_available', '>', 0)
            ->orderByRaw('expiration_date IS NULL asc, expiration_date asc, received_at asc')
            ->get(['quantity_available', 'expiration_date']);

        if ($batches->isEmpty()) {
            return null;
        }

        $supportsExpiryBlocking = $expiredMode === 'bloquear';

        // Copy to an in-memory queue that we can decrement.
        $queue = $batches->map(function ($b) {
            return [
                'qty' => (float) $b->quantity_available,
                'expiration' => $b->expiration_date ? Carbon::parse($b->expiration_date)->startOfDay() : null,
            ];
        })->values()->all();

        $pointer = 0;
        $eps = 1e-9;

        // Hard cap for safety; most products will stock-out far sooner.
        $maxDays = 3650;

        for ($dayOffset = 0; $dayOffset < $maxDays; $dayOffset++) {
            $currentDate = $today->copy()->addDays($dayOffset);

            // In bloquear mode, any remaining qty of a batch that expires before today becomes non-sellable.
            if ($supportsExpiryBlocking) {
                while ($pointer < count($queue)) {
                    $exp = $queue[$pointer]['expiration'];
                    if ($exp !== null && $exp->lt($currentDate)) {
                        $queue[$pointer]['qty'] = 0.0;
                        $pointer++;
                        continue;
                    }
                    break;
                }
            }

            $remainingDemand = $avgDailyDemand;

            // Consume FEFO: always consume from the earliest expiration batch available.
            while ($remainingDemand > $eps) {
                while ($pointer < count($queue) && $queue[$pointer]['qty'] <= $eps) {
                    $pointer++;
                }

                if ($pointer >= count($queue)) {
                    return $currentDate->toDateString(); // unable to satisfy demand for this day
                }

                $available = $queue[$pointer]['qty'];
                $consume = min($available, $remainingDemand);

                $queue[$pointer]['qty'] -= $consume;
                $remainingDemand -= $consume;
            }
        }

        return null;
    }
}
