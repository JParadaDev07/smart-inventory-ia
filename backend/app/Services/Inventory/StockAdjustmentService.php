<?php

namespace App\Services\Inventory;

use App\Models\Batch;
use App\Models\InventoryLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductBranchStock;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    /**
     * @param array{
     *   quantity_change:int,
     *   branch_id?:int|null,
     *   type?:string|null,
     *   expiration_date?:string|null,
     *   cost_unit?:string|float|null,
     *   batch_id?:int|null,
     *   reason?:string|null
     * } $data
     */
    public function adjust(
        int $businessId,
        Product $product,
        array $data,
        int $branchId
    ): Product {
        return DB::transaction(function () use ($businessId, $product, $data, $branchId) {
            $change = (int) $data['quantity_change'];
            $type = $data['type'] ?? 'adjustment';

            if ($change === 0) {
                return $product;
            }

            if ($product->perecedero) {
                return $this->adjustPerishable($businessId, $product, $data, $branchId, $change, $type);
            }

            return $this->adjustNonPerishable($businessId, $product, $branchId, $change, $type);
        });
    }

    private function adjustNonPerishable(
        int $businessId,
        Product $product,
        int $branchId,
        int $change,
        string $type
    ): Product {
        $product = $product->fresh();

        $branchStock = ProductBranchStock::query()
            ->where('business_id', $businessId)
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->first();

        if (!$branchStock) {
            $branchStock = ProductBranchStock::create([
                'business_id' => $businessId,
                'product_id' => $product->id,
                'branch_id' => $branchId,
                'current_stock' => 0,
                'minimum_stock' => (int) $product->minimum_stock,
            ]);
        }

        $newBranchStock = (int) $branchStock->current_stock + $change;
        if ($newBranchStock < 0) {
            throw new DomainException('Resulting branch stock cannot be negative.');
        }

        $newTotalStock = (int) $product->current_stock + $change;
        if ($newTotalStock < 0) {
            throw new DomainException('Resulting stock cannot be negative.');
        }

        $branchStock->update(['current_stock' => $newBranchStock]);
        $product->update(['current_stock' => $newTotalStock]);

        InventoryLog::create([
            'business_id' => $businessId,
            'product_id' => $product->id,
            'type' => $type,
            'quantity_change' => $change,
            'quantity_after' => $newTotalStock,
            'reference_type' => null,
            'reference_id' => null,
        ]);

        return $product->fresh();
    }

    private function adjustPerishable(
        int $businessId,
        Product $product,
        array $data,
        int $branchId,
        int $change,
        string $type
    ): Product {
        $product = $product->fresh();
        $costUnit = isset($data['cost_unit']) && $data['cost_unit'] !== null ? (float) $data['cost_unit'] : (float) $product->cost_price;
        $reason = $data['reason'] ?? null;

        // Restock => create a new batch.
        if ($change > 0) {
            $expirationDate = $data['expiration_date'] ?? null;
            if (!$expirationDate) {
                throw new DomainException('expiration_date is required for perishable products restock.');
            }

            $batch = Batch::create([
                'business_id' => $businessId,
                'product_id' => $product->id,
                'branch_id' => $branchId,
                'quantity_received' => (int) $change,
                'quantity_available' => (int) $change,
                'expiration_date' => Carbon::parse($expirationDate)->toDateString(),
                'cost_unit' => $costUnit,
                'received_at' => now(),
            ]);

            $this->incrementSummaryStock($businessId, $product, $branchId, $change);

            InventoryMovement::create([
                'business_id' => $businessId,
                'product_id' => $product->id,
                'branch_id' => $branchId,
                'batch_id' => $batch->id,
                'type' => 'entrada',
                'quantity' => (int) $change,
                'reason' => $reason,
                'is_expired' => false,
                'reference_type' => null,
                'reference_id' => null,
                'occurred_at' => now(),
            ]);

            return $product->fresh();
        }

        // Merma/adjustment => consume from batches.
        $qtyToConsume = abs($change);
        $batchId = $data['batch_id'] ?? null;

        $movementType = 'merma';

        if ($batchId) {
            /** @var Batch|null $batch */
            $supportsLock = in_array(DB::getDriverName(), ['mysql', 'pgsql'], true);
            $batch = Batch::query()
                ->where('business_id', $businessId)
                ->where('id', (int) $batchId)
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->when($supportsLock, fn ($q) => $q->lockForUpdate())
                ->first();

            if (!$batch) {
                throw new DomainException('Specified batch not found for this product/branch.');
            }

            if ((int) $batch->quantity_available < $qtyToConsume) {
                throw new DomainException('Insufficient batch quantity to consume.');
            }

            $batch->quantity_available -= $qtyToConsume;
            $batch->save();

            $this->decrementSummaryStock($product, $branchId, $qtyToConsume);

            InventoryMovement::create([
                'business_id' => $businessId,
                'product_id' => $product->id,
                'branch_id' => $branchId,
                'batch_id' => $batch->id,
                'type' => $movementType,
                'quantity' => (int) $qtyToConsume,
                'reason' => $reason,
                'is_expired' => $batch->expiration_date ? Carbon::parse($batch->expiration_date)->lte(Carbon::today()) : false,
                'reference_type' => null,
                'reference_id' => null,
                'occurred_at' => now(),
            ]);

            InventoryLog::create([
                'business_id' => $businessId,
                'product_id' => $product->id,
                'type' => $type,
                'quantity_change' => -$qtyToConsume,
                'quantity_after' => (int) $product->fresh()->current_stock,
                'reference_type' => null,
                'reference_id' => null,
            ]);

            return $product->fresh();
        }

        // FEFO order consumption
        $qtyRemaining = $qtyToConsume;
        while ($qtyRemaining > 0) {
            $supportsLock = in_array(DB::getDriverName(), ['mysql', 'pgsql'], true);
            $batch = Batch::query()
                ->where('business_id', $businessId)
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->where('quantity_available', '>', 0)
                // FEFO: non-null expiration first, NULL goes last (legacy).
                ->orderByRaw('expiration_date IS NULL asc, expiration_date asc, received_at asc')
                ->when($supportsLock, fn ($q) => $q->lockForUpdate())
                ->first();

            if (!$batch) {
                throw new DomainException('Insufficient batch stock for adjustment.');
            }

            $allocQty = min($qtyRemaining, (int) $batch->quantity_available);

            $batch->quantity_available -= $allocQty;
            $batch->save();

            $this->decrementSummaryStock($product, $branchId, $allocQty);

            InventoryMovement::create([
                'business_id' => $businessId,
                'product_id' => $product->id,
                'branch_id' => $branchId,
                'batch_id' => $batch->id,
                'type' => $movementType,
                'quantity' => (int) $allocQty,
                'reason' => $reason,
                'is_expired' => $batch->expiration_date ? Carbon::parse($batch->expiration_date)->lte(Carbon::today()) : false,
                'reference_type' => null,
                'reference_id' => null,
                'occurred_at' => now(),
            ]);

            $qtyRemaining -= $allocQty;
        }

        InventoryLog::create([
            'business_id' => $businessId,
            'product_id' => $product->id,
            'type' => $type,
            'quantity_change' => $change,
            'quantity_after' => (int) $product->fresh()->current_stock,
            'reference_type' => null,
            'reference_id' => null,
        ]);

        return $product->fresh();
    }

    private function incrementSummaryStock(int $businessId, Product $product, int $branchId, int $qty): void
    {
        $product->current_stock += $qty;
        $product->save();

        $branchStock = ProductBranchStock::query()
            ->where('business_id', $businessId)
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->first();

        if (!$branchStock) {
            $branchStock = ProductBranchStock::create([
                'business_id' => $businessId,
                'product_id' => $product->id,
                'branch_id' => $branchId,
                'current_stock' => 0,
                'minimum_stock' => (int) $product->minimum_stock,
            ]);
        }

        $branchStock->current_stock += $qty;
        $branchStock->save();
    }

    private function decrementSummaryStock(Product $product, int $branchId, int $qty): void
    {
        if ($product->current_stock < $qty) {
            throw new DomainException('Resulting stock cannot be negative.');
        }

        $product->current_stock -= $qty;
        $product->save();

        $branchStock = ProductBranchStock::query()
            ->where('business_id', $product->business_id)
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->first();

        if (!$branchStock) {
            throw new DomainException('Branch stock not found for perishable adjustment.');
        }

        if ((int) $branchStock->current_stock < $qty) {
            throw new DomainException('Resulting branch stock cannot be negative.');
        }

        $branchStock->current_stock -= $qty;
        $branchStock->save();
    }
}

