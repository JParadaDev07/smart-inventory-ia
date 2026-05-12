<?php

namespace Tests\Unit;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Inventory\InventoryAnalysisService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerishableInventoryAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow();
    }

    private function createBusinessAndBranch(): array
    {
        $business = Business::create(['name' => 'Test Business']);
        $branch = Branch::create([
            'business_id' => $business->id,
            'name' => 'Main',
        ]);

        return [$business, $branch];
    }

    private function createPerishableProduct(Business $business, array $overrides = []): Product
    {
        $defaults = [
            'name' => 'Cement',
            'sku' => 'C-1',
            'cost_price' => 1000,
            'sale_price' => 2000,
            'current_stock' => 0,
            'minimum_stock' => 0,
            'supplier_lead_time_days' => 0,
            'perecedero' => true,
            'expired_mode' => 'bloquear',
            'expiration_alert_days' => 7,
        ];

        return Product::create(array_merge($defaults, $overrides, ['business_id' => $business->id]));
    }

    private function seedDailySales(
        Business $business,
        Branch $branch,
        Product $product,
        int $qtyPerDay,
        Carbon $today,
        int $days = 30
    ): void {
        for ($i = 0; $i < $days; $i++) {
            $createdAt = $today->copy()->subDays($days - 1 - $i);

            $sale = Sale::create([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'total_amount' => 0,
            ]);
            $sale->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();

            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'quantity' => $qtyPerDay,
                'unit_price' => 1,
            ]);
        }
    }

    public function test_bloquear_removes_remaining_qty_of_expired_batch_before_next_day_consumption(): void
    {
        $today = Carbon::create(2026, 3, 18)->startOfDay();
        Carbon::setTestNow($today);

        [$business, $branch] = $this->createBusinessAndBranch();

        $product = $this->createPerishableProduct($business, [
            'expired_mode' => 'bloquear',
        ]);

        // Avg daily demand = 8
        $this->seedDailySales($business, $branch, $product, 8, $today);

        // Batch expiring today has remaining qty after day-0 demand.
        Batch::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity_received' => 10,
            'quantity_available' => 10,
            'expiration_date' => $today->toDateString(),
            'cost_unit' => 1,
            'received_at' => $today->copy()->subDay(),
        ]);

        Batch::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity_received' => 5,
            'quantity_available' => 5,
            'expiration_date' => $today->copy()->addDay()->toDateString(),
            'cost_unit' => 1,
            'received_at' => $today->copy()->subHours(2),
        ]);

        $intel = app(InventoryAnalysisService::class)->getIntelligence($product, 30, $business->id);

        $this->assertSame($today->copy()->addDay()->toDateString(), $intel['estimated_stock_out_date']);
    }

    public function test_permitir_does_not_block_expired_qty_for_future_days(): void
    {
        $today = Carbon::create(2026, 3, 18)->startOfDay();
        Carbon::setTestNow($today);

        [$business, $branch] = $this->createBusinessAndBranch();

        $product = $this->createPerishableProduct($business, [
            'expired_mode' => 'permitir',
        ]);

        // Avg daily demand = 5
        $this->seedDailySales($business, $branch, $product, 5, $today);

        Batch::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity_received' => 10,
            'quantity_available' => 10,
            'expiration_date' => $today->toDateString(),
            'cost_unit' => 1,
            'received_at' => $today->copy()->subDay(),
        ]);

        // Small batch arriving later; under bloquear it would cause stock-out earlier,
        // but permitir keeps remaining qty from the expired batch usable.
        Batch::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity_received' => 1,
            'quantity_available' => 1,
            'expiration_date' => $today->copy()->addDays(10)->toDateString(),
            'cost_unit' => 1,
            'received_at' => $today->copy()->subHours(1),
        ]);

        $intel = app(InventoryAnalysisService::class)->getIntelligence($product, 30, $business->id);

        // total qty 11; demand 5/day => day0 uses 5 (remaining 6), day1 uses 5 (remaining 1), day2 fails => stock-out day2
        $this->assertSame($today->copy()->addDays(2)->toDateString(), $intel['estimated_stock_out_date']);
    }

    private function seedDailySalesForAnalysis(
        Business $business,
        Branch $branch,
        Product $product,
        int $qtyPerDay,
        Carbon $today,
        int $days = 30
    ): void {
        for ($i = 0; $i < $days; $i++) {
            $createdAt = $today->copy()->subDays($days - 1 - $i);

            $sale = Sale::create([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'total_amount' => 0,
            ]);
            $sale->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();

            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'quantity' => $qtyPerDay,
                'unit_price' => 1,
            ]);
        }
    }

    public function test_recommended_purchase_uses_sellable_stock_in_bloquear_mode(): void
    {
        $today = Carbon::create(2026, 3, 18)->startOfDay();
        Carbon::setTestNow($today);

        [$business, $branch] = $this->createBusinessAndBranch();

        // Avg daily demand = 1, lead_time_days = 0 => reorder_point = 3.
        $product = $this->createPerishableProduct($business, [
            'expired_mode' => 'bloquear',
            'supplier_lead_time_days' => 0,
            'current_stock' => 2, // includes expired batch, but should be excluded from "sellable" stock.
        ]);

        $this->seedDailySalesForAnalysis($business, $branch, $product, 1, $today);

        // Expired yesterday batch: should be excluded for sellable stock in bloquear mode.
        Batch::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity_received' => 2,
            'quantity_available' => 2,
            'expiration_date' => $today->copy()->subDay()->toDateString(),
            'cost_unit' => 1,
            'received_at' => $today->copy()->subDays(2),
        ]);

        $recommended = app(InventoryAnalysisService::class)->recommendedPurchase($product, 30, $business->id);
        $this->assertSame(3, $recommended, 'sellable stock must be 0 in bloquear mode when batch is expired before today');
    }

    public function test_recommended_purchase_in_permitir_mode_includes_expired_batches(): void
    {
        $today = Carbon::create(2026, 3, 18)->startOfDay();
        Carbon::setTestNow($today);

        [$business, $branch] = $this->createBusinessAndBranch();

        $product = $this->createPerishableProduct($business, [
            'expired_mode' => 'permitir',
            'supplier_lead_time_days' => 0,
            'current_stock' => 2,
        ]);

        $this->seedDailySalesForAnalysis($business, $branch, $product, 1, $today);

        Batch::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity_received' => 2,
            'quantity_available' => 2,
            'expiration_date' => $today->copy()->subDay()->toDateString(),
            'cost_unit' => 1,
            'received_at' => $today->copy()->subDays(2),
        ]);

        $recommended = app(InventoryAnalysisService::class)->recommendedPurchase($product, 30, $business->id);
        $this->assertSame(1, $recommended, 'sellable stock must include expired qty in permitir mode');
    }
}

