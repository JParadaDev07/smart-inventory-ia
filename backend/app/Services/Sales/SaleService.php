<?php

namespace App\Services\Sales;

use App\Models\Branch;
use App\Models\ProductBranchStock;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function createSale(int $businessId, array $items, ?string $date = null, ?int $branchId = null): Sale
    {
        // Advanced inventory sale (FIFO/FEFO for perishable products).
        return app(FifoSaleService::class)->createSale($businessId, $items, $date, $branchId);
    }

    private function resolveBranchId(int $businessId, ?int $branchId): ?int
    {
        if ($branchId) return $branchId;
        return Branch::query()->where('business_id', $businessId)->orderBy('id')->value('id');
    }

    private function getOrCreateBranchStock(Product $product, int $branchId): ProductBranchStock
    {
        $stock = ProductBranchStock::query()
            ->where('business_id', $product->business_id)
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->first();

        if ($stock) return $stock;

        return ProductBranchStock::create([
            'business_id' => $product->business_id,
            'product_id' => $product->id,
            'branch_id' => $branchId,
            'current_stock' => 0,
            'minimum_stock' => (int) $product->minimum_stock,
        ]);
    }

    private function validateStock(array $items, int $businessId, ?int $branchId): void
    {
        foreach ($items as $item) {
            $product = Product::withoutGlobalScope('business')
                ->where('business_id', $businessId)
                ->where('id', $item['product_id'])
                ->first();
            if (!$product) {
                throw new \InvalidArgumentException("Product not found: {$item['product_id']}");
            }
            $qty = (int) $item['quantity'];
            if ($qty <= 0) {
                throw new \InvalidArgumentException("Invalid quantity for product {$product->id}");
            }
            if ($branchId) {
                $productStock = ProductBranchStock::query()
                    ->where('business_id', $product->business_id)
                    ->where('product_id', $product->id)
                    ->where('branch_id', $branchId)
                    ->first();

                $available = $productStock?->current_stock ?? 0;
                if ($available < $qty) {
                    throw new \InvalidArgumentException("Insufficient branch stock for product: {$product->name}. Available: {$available}");
                }
            } else {
                if ($product->current_stock < $qty) {
                    throw new \InvalidArgumentException("Insufficient stock for product: {$product->name}. Available: {$product->current_stock}");
                }
            }
        }
    }

    public function salesTotalLastDays(int $businessId, int $days = 30): float
    {
        return (float) Sale::forBusiness($businessId)
            ->where('created_at', '>=', now()->subDays($days))
            ->sum('total_amount');
    }

    /**
     * Top-selling products by quantity in the last N days.
     *
     * @return array<int, array{name: string, total_qty: int}>
     */
    public function topProductsLastDays(int $businessId, int $days = 30, int $limit = 5): array
    {
        $since = now()->subDays($days);

        $rows = SaleItem::query()
            ->whereHas('sale', function ($q) use ($businessId, $since) {
                $q->forBusiness($businessId)
                    ->where('created_at', '>=', $since);
            })
            ->selectRaw('product_id, SUM(quantity) as total_qty')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->with('product:id,name')
            ->get();

        return $rows->map(function (SaleItem $item) {
            return [
                'name' => $item->product?->name ?? 'Producto #' . $item->product_id,
                'total_qty' => (int) $item->total_qty,
            ];
        })->all();
    }

    /**
     * Sales totals grouped by day for the last N days (for charts).
     * Returns array of [ 'date' => 'Y-m-d', 'total' => float ] for each day, including days with zero.
     */
    public function salesByDayLastDays(int $businessId, int $days = 30): array
    {
        $start = now()->subDays($days)->startOfDay();

        $rows = Sale::forBusiness($businessId)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $result[] = [
                'date' => $date,
                'total' => round((float) ($rows[$date]->total ?? 0), 2),
            ];
        }

        return $result;
    }

    public function deleteSale(int $businessId, int $saleId): void
    {
        app(FifoSaleService::class)->deleteSale($businessId, $saleId);
    }
}
