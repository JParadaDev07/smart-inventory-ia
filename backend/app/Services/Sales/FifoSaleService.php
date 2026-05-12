<?php

namespace App\Services\Sales;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\InventoryLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductBranchStock;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SaleItem;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class FifoSaleService
{
    public function createSale(
        int $businessId,
        array $items,
        ?string $date = null,
        ?int $branchId = null
    ): Sale {
        return DB::transaction(function () use ($businessId, $items, $date, $branchId) {
        $branchId = $this->resolveBranchId($businessId, $branchId);
            $createdAt = $date ? Carbon::parse($date) : now();
            // Use the sale date for expiration policy evaluation (past/future sales included).
            $today = $createdAt->copy()->startOfDay();

            $sale = Sale::create([
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'total_amount' => 0,
                'has_expired_items' => false,
                'expired_quantity_total' => 0,
            ]);

            // `Sale` has $fillable without timestamps fields; explicitly set timestamps to the requested sale date.
            $sale->timestamps = false;
            $sale->created_at = $createdAt;
            $sale->updated_at = $createdAt;
            $sale->saveQuietly();
            $sale->timestamps = true;

            $total = 0.0;
            $expiredQtyTotal = 0;

            // Compatibility: sale.items is aggregated by product_id (UI depends on it).
            $saleItemsAgg = []; // [productId => ['quantity' => int, 'unit_price' => float]]

            foreach ($items as $item) {
                $product = Product::withoutGlobalScope('business')
                    ->where('business_id', $businessId)
                    ->where('id', (int) $item['product_id'])
                    ->firstOrFail();

                $qtyToSell = (int) $item['quantity'];
                $unitPrice = (float) ($item['unit_price'] ?? $product->sale_price);

                if ($qtyToSell <= 0) {
                    throw new DomainException("Invalid quantity for product {$product->id}");
                }

                if ($product->perecedero) {
                    [$lineTotal, $lineExpiredQty] = $this->sellPerishableUsingFifoFefo(
                        $sale,
                        $product,
                        $branchId,
                        $qtyToSell,
                        $unitPrice,
                        $today
                    );
                    $total += $lineTotal;
                    $expiredQtyTotal += $lineExpiredQty;

                    $saleItemsAgg[$product->id] = $saleItemsAgg[$product->id] ?? [
                        'quantity' => 0,
                        'unit_price' => $unitPrice,
                    ];
                    $saleItemsAgg[$product->id]['quantity'] += $qtyToSell;
                } else {
                    $lineTotal = $this->sellNonPerishableSharedStock(
                        $sale,
                        $product,
                        $branchId,
                        $qtyToSell,
                        $unitPrice
                    );

                    $total += $lineTotal;
                    $saleItemsAgg[$product->id] = $saleItemsAgg[$product->id] ?? [
                        'quantity' => 0,
                        'unit_price' => $unitPrice,
                    ];
                    $saleItemsAgg[$product->id]['quantity'] += $qtyToSell;
                }
            }

            // Persist aggregated sale_items for UI/back-compat.
            foreach ($saleItemsAgg as $productId => $agg) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => (int) $productId,
                    'quantity' => (int) $agg['quantity'],
                    'unit_price' => (float) $agg['unit_price'],
                ]);
            }

            $sale->update([
                'total_amount' => round($total, 2),
                'has_expired_items' => $expiredQtyTotal > 0,
                'expired_quantity_total' => (int) $expiredQtyTotal,
            ]);

            return $sale->load('items.product');
        });
    }

    private function sellPerishableUsingFifoFefo(
        Sale $sale,
        Product $product,
        int $branchId,
        int $qtyToSell,
        float $unitPrice,
        Carbon $today
    ): array {
        $businessId = (int) $sale->business_id;
        $expiredQtyTotal = 0;
        $lineTotal = 0.0;

        $expiredMode = $product->expired_mode ?: 'bloquear';

        $qtyRemaining = $qtyToSell;
        while ($qtyRemaining > 0) {
            $batch = $this->lockNextUsableBatchForSale(
                businessId: $businessId,
                branchId: $branchId,
                product: $product,
                today: $today,
                expiredMode: $expiredMode
            );

            if (!$batch) {
                throw new DomainException("Insufficient usable batch stock for product: {$product->name}");
            }

            $allocQty = min($qtyRemaining, (int) $batch->quantity_available);

            // Policy:
            // - Blocked: expiration_date < today
            // - Warn+Allowed: expiration_date == today
            // - Expired sold flag: expiration_date <= today
            $batchDate = $batch->expiration_date ? Carbon::parse($batch->expiration_date) : null;
            $isBlocked = $batchDate ? $batchDate->lt($today) : false;
            $isWarnToday = $batchDate ? $batchDate->eq($today) : false;
            $isExpiredSold = $batchDate ? $batchDate->lte($today) : false;

            // In bloquear mode, blocked batches should not be selected. Keep guard anyway.
            if ($expiredMode === 'bloquear' && $isBlocked) {
                throw new DomainException("Expired stock blocked for product {$product->name}");
            }

            // Update batch remaining quantity.
            $batch->quantity_available -= $allocQty;
            $batch->save();

            // Update summary stock for product and branch.
            $this->decrementSummaryStock($product, $branchId, $allocQty);

            // Detail per batch (partial allowed).
            SaleDetail::create([
                'business_id' => $businessId,
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'quantity' => (int) $allocQty,
                'unit_price' => (float) $unitPrice,
                'unit_cost' => (float) $batch->cost_unit,
                'expiration_date' => $batch->expiration_date,
                // requirement: register if sold expired. With our rule, equality today is the warning but is considered expired sold.
                'is_expired' => (bool) $isExpiredSold,
            ]);

            InventoryMovement::create([
                'business_id' => $businessId,
                'product_id' => $product->id,
                'branch_id' => $branchId,
                'batch_id' => $batch->id,
                'type' => 'salida',
                'quantity' => (int) $allocQty,
                'reason' => null,
                'is_expired' => (bool) $isExpiredSold,
                'reference_type' => Sale::class,
                'reference_id' => $sale->id,
                'occurred_at' => $sale->created_at,
            ]);

            InventoryLog::create([
                'business_id' => $businessId,
                'product_id' => $product->id,
                'type' => 'sale',
                'quantity_change' => -$allocQty,
                'quantity_after' => (int) $product->fresh()->current_stock,
                'reference_type' => Sale::class,
                'reference_id' => $sale->id,
            ]);

            if ($isExpiredSold) {
                $expiredQtyTotal += (int) $allocQty;
            }

            $lineTotal += $allocQty * $unitPrice;
            $qtyRemaining -= $allocQty;
        }

        return [$lineTotal, $expiredQtyTotal];
    }

    private function lockNextUsableBatchForSale(
        int $businessId,
        int $branchId,
        Product $product,
        Carbon $today,
        string $expiredMode
    ): ?Batch {
        $supportsLock = in_array(DB::getDriverName(), ['mysql', 'pgsql'], true);
        // For perishables: require expiration_date so we can apply the policy.
        $query = Batch::query()
            ->forBusiness($businessId)
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->where('quantity_available', '>', 0)
            // FEFO: non-null expiration first, then by expiration_date asc, then received_at asc.
            // If expiration_date is NULL (legacy data), it goes last and is treated as "not expired" for warning purposes.
            ->orderByRaw('expiration_date IS NULL asc, expiration_date asc, received_at asc')
            // SQLite may not support FOR UPDATE; only lock when DB supports it.
            ->when($supportsLock, fn ($q) => $q->lockForUpdate());

        if ($expiredMode === 'bloquear') {
            // Filter blocked batches (< today). Allow today (warn) and future.
            // Also allow NULL expiration_date (legacy) without marking it as expired.
            $query->where(function ($q) use ($today) {
                $q->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', $today->toDateString());
            });
        }

        return $query->first();
    }

    private function sellNonPerishableSharedStock(
        Sale $sale,
        Product $product,
        int $branchId,
        int $qty,
        float $unitPrice
    ): float {
        $businessId = (int) $sale->business_id;
        $product->refresh();

        $productStock = $this->getOrCreateBranchStock($product, $branchId);
        $available = (int) $productStock->current_stock;

        if ($available < $qty) {
            throw new DomainException("Insufficient branch stock for product: {$product->name}. Available: {$available}");
        }

        $productStock->current_stock -= $qty;
        $productStock->save();

        $product->current_stock -= $qty;
        $product->save();

        InventoryLog::create([
            'business_id' => $businessId,
            'product_id' => $product->id,
            'type' => 'sale',
            'quantity_change' => -$qty,
            'quantity_after' => (int) $product->fresh()->current_stock,
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
        ]);

        return (float) ($qty * $unitPrice);
    }

    private function decrementSummaryStock(Product $product, int $branchId, int $qty): void
    {
        $product->current_stock -= $qty;
        $product->save();

        $productStock = $this->getOrCreateBranchStock($product, $branchId);
        $productStock->current_stock -= $qty;
        $productStock->save();
    }

    private function getOrCreateBranchStock(Product $product, int $branchId): ProductBranchStock
    {
        $stock = ProductBranchStock::query()
            ->where('business_id', $product->business_id)
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->first();

        if ($stock) {
            return $stock;
        }

        return ProductBranchStock::create([
            'business_id' => $product->business_id,
            'product_id' => $product->id,
            'branch_id' => $branchId,
            'current_stock' => 0,
            'minimum_stock' => (int) $product->minimum_stock,
        ]);
    }

    private function resolveBranchId(int $businessId, ?int $branchId): int
    {
        if ($branchId !== null) {
            return $branchId;
        }

        $resolved = Branch::query()->where('business_id', $businessId)->orderBy('id')->value('id');
        if (!$resolved) {
            throw new DomainException('No branch configured for this business.');
        }

        return (int) $resolved;
    }

    public function deleteSale(int $businessId, int $saleId): void
    {
        DB::transaction(function () use ($businessId, $saleId) {
            /** @var Sale $sale */
            $sale = Sale::forBusiness($businessId)
                ->with(['items.product', 'details.batch'])
                ->findOrFail($saleId);

            $branchId = (int) ($sale->branch_id ?? 0);
            if ($branchId === 0) {
                $branchId = $this->resolveBranchId($businessId, null);
            }

            $detailsProductIds = $sale->details->map(fn ($d) => $d->product_id)->unique()->values()->all();

            // Restore perishable consumption from sale_details/batches.
            foreach ($sale->details as $detail) {
                $batch = $detail->batch;
                if (!$batch) continue;

                $batch->quantity_available += (int) $detail->quantity;
                $batch->save();

                $product = $batch->product;
                if (!$product) {
                    continue;
                }

                $product->current_stock += (int) $detail->quantity;
                $product->save();

                $productStock = $this->getOrCreateBranchStock($product, (int) $batch->branch_id);
                $productStock->current_stock += (int) $detail->quantity;
                $productStock->save();

                InventoryMovement::create([
                    'business_id' => (int) $businessId,
                    'product_id' => (int) $detail->product_id,
                    'branch_id' => (int) $batch->branch_id,
                    'batch_id' => (int) $batch->id,
                    'type' => 'entrada',
                    'quantity' => (int) $detail->quantity,
                    'reason' => 'sale_cancel',
                    'is_expired' => (bool) $detail->is_expired,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'occurred_at' => now(),
                ]);

                InventoryLog::create([
                    'business_id' => (int) $businessId,
                    'product_id' => (int) $detail->product_id,
                    'type' => 'adjustment',
                    'quantity_change' => (int) $detail->quantity,
                    'quantity_after' => (int) $product->fresh()->current_stock,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                ]);
            }

            // Restore non-perishables (or any product that doesn't have details rows).
            foreach ($sale->items as $item) {
                if (in_array($item->product_id, $detailsProductIds, true)) {
                    continue;
                }

                $product = $item->product;
                if (!$product) continue;

                $qty = (int) $item->quantity;

                $product->current_stock += $qty;
                $product->save();

                $productStock = $this->getOrCreateBranchStock($product, (int) $branchId);
                $productStock->current_stock += $qty;
                $productStock->save();

                InventoryLog::create([
                    'business_id' => (int) $businessId,
                    'product_id' => (int) $item->product_id,
                    'type' => 'adjustment',
                    'quantity_change' => (int) $qty,
                    'quantity_after' => (int) $product->fresh()->current_stock,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                ]);
            }

            $sale->delete();
        });
    }
}

