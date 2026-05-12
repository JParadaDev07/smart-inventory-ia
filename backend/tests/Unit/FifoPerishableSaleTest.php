<?php

namespace Tests\Unit;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Product;
use App\Models\ProductBranchStock;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\InventoryMovement;
use App\Services\Sales\FifoSaleService;
use App\Services\Inventory\StockAdjustmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use DomainException;
use Tests\TestCase;

class FifoPerishableSaleTest extends TestCase
{
    use RefreshDatabase;

    private function createBusinessAndBranch(): array
    {
        $business = Business::create(['name' => 'Test Business']);
        $branch = Branch::create([
            'business_id' => $business->id,
            'name' => 'Main',
        ]);

        return [$business, $branch];
    }

    private function createPerishableProduct(array $overrides = []): Product
    {
        // Keep summary stock in sync with batches created in each test.
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

        $businessId = $overrides['business_id'] ?? null;
        if (!$businessId) {
            throw new DomainException('business_id is required for createPerishableProduct()');
        }

        return Product::create(array_merge($defaults, $overrides, ['business_id' => $businessId]));
    }

    public function test_bloquear_blocks_batches_expired_before_today_but_allows_today_warning(): void
    {
        [$business, $branch] = $this->createBusinessAndBranch();
        $today = Carbon::today();

        $product = $this->createPerishableProduct([
            'business_id' => $business->id,
            'expired_mode' => 'bloquear',
            'current_stock' => 20,
        ]);

        ProductBranchStock::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'current_stock' => 20,
            'minimum_stock' => 0,
        ]);

        $bExpired = Batch::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity_received' => 10,
            'quantity_available' => 10,
            'expiration_date' => $today->copy()->subDay()->toDateString(),
            'cost_unit' => 11,
            'received_at' => $today->copy()->subDay(),
        ]);

        $bWarn = Batch::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity_received' => 10,
            'quantity_available' => 10,
            'expiration_date' => $today->toDateString(),
            'cost_unit' => 11,
            'received_at' => $today->copy()->subHours(1),
        ]);

        $service = app(FifoSaleService::class);

        $sale = $service->createSale(
            businessId: $business->id,
            items: [
                ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 99],
            ],
            date: $today->toDateString(),
            branchId: $branch->id,
        );

        $sale->refresh();

        $this->assertTrue($sale->has_expired_items, 'Expected warning items when consuming today-expiring lot.');
        $this->assertSame(3, (int) $sale->expired_quantity_total);

        $bExpired->refresh();
        $bWarn->refresh();
        $this->assertSame(10, (int) $bExpired->quantity_available, 'Expired-before-today batch must not be consumed in bloquear mode.');
        $this->assertSame(7, (int) $bWarn->quantity_available);

        $details = SaleDetail::query()->where('sale_id', $sale->id)->get();
        $this->assertCount(1, $details, 'Expected single batch consumption.');
        $this->assertSame($bWarn->id, $details->first()->batch_id);
        $this->assertTrue((bool) $details->first()->is_expired);
    }

    public function test_bloquear_throws_when_no_usable_batches_exist(): void
    {
        [$business, $branch] = $this->createBusinessAndBranch();
        $today = Carbon::today();

        $product = $this->createPerishableProduct([
            'business_id' => $business->id,
            'expired_mode' => 'bloquear',
            'current_stock' => 5,
        ]);

        ProductBranchStock::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'current_stock' => 5,
            'minimum_stock' => 0,
        ]);

        Batch::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity_received' => 5,
            'quantity_available' => 5,
            'expiration_date' => $today->copy()->subDay()->toDateString(),
            'cost_unit' => 11,
            'received_at' => $today->copy()->subDay(),
        ]);

        $service = app(FifoSaleService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Insufficient usable batch stock for product: Cement');

        $service->createSale(
            businessId: $business->id,
            items: [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10],
            ],
            date: $today->toDateString(),
            branchId: $branch->id,
        );
    }

    public function test_alerta_allows_consuming_expired_batches_and_marks_warning(): void
    {
        [$business, $branch] = $this->createBusinessAndBranch();
        $today = Carbon::today();

        $product = $this->createPerishableProduct([
            'business_id' => $business->id,
            'expired_mode' => 'alerta',
            'current_stock' => 8,
        ]);

        ProductBranchStock::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'current_stock' => 8,
            'minimum_stock' => 0,
        ]);

        $bExpired = Batch::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity_received' => 4,
            'quantity_available' => 4,
            'expiration_date' => $today->copy()->subDay()->toDateString(),
            'cost_unit' => 11,
            'received_at' => $today->copy()->subDays(2),
        ]);

        $bWarn = Batch::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity_received' => 4,
            'quantity_available' => 4,
            'expiration_date' => $today->toDateString(),
            'cost_unit' => 11,
            'received_at' => $today->copy()->subDay(),
        ]);

        $service = app(FifoSaleService::class);

        $sale = $service->createSale(
            businessId: $business->id,
            items: [
                ['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 99],
            ],
            date: $today->toDateString(),
            branchId: $branch->id,
        );

        $sale->refresh();

        $this->assertTrue($sale->has_expired_items);
        $this->assertSame(4, (int) $sale->expired_quantity_total);

        $bExpired->refresh();
        $bWarn->refresh();
        $this->assertSame(0, (int) $bExpired->quantity_available, 'Expired lot should be consumed in alerta mode.');
        $this->assertSame(4, (int) $bWarn->quantity_available);

        $details = SaleDetail::query()->where('sale_id', $sale->id)->get();
        $this->assertCount(1, $details);
        $this->assertSame($bExpired->id, $details->first()->batch_id);
        $this->assertTrue((bool) $details->first()->is_expired);
    }

    public function test_partial_consumption_creates_multiple_sale_details_rows(): void
    {
        [$business, $branch] = $this->createBusinessAndBranch();
        $today = Carbon::today();

        $product = $this->createPerishableProduct([
            'business_id' => $business->id,
            'expired_mode' => 'bloquear',
            'current_stock' => 7,
        ]);

        ProductBranchStock::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'current_stock' => 7,
            'minimum_stock' => 0,
        ]);

        $bToday = Batch::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity_received' => 2,
            'quantity_available' => 2,
            'expiration_date' => $today->toDateString(),
            'cost_unit' => 11,
            'received_at' => $today->copy()->subHours(1),
        ]);

        $bFuture = Batch::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity_received' => 5,
            'quantity_available' => 5,
            'expiration_date' => $today->copy()->addDays(10)->toDateString(),
            'cost_unit' => 11,
            'received_at' => $today->copy()->subHours(2),
        ]);

        $service = app(FifoSaleService::class);

        $sale = $service->createSale(
            businessId: $business->id,
            items: [
                ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 50],
            ],
            date: $today->toDateString(),
            branchId: $branch->id,
        );

        $sale->refresh();
        $this->assertTrue($sale->has_expired_items);
        $this->assertSame(2, (int) $sale->expired_quantity_total);

        $details = SaleDetail::query()->where('sale_id', $sale->id)->orderBy('id')->get();
        $this->assertCount(2, $details, 'Expected consumption from two batches.');
        $this->assertSame($bToday->id, $details[0]->batch_id);
        $this->assertSame(2, (int) $details[0]->quantity);
        $this->assertTrue((bool) $details[0]->is_expired);

        $this->assertSame($bFuture->id, $details[1]->batch_id);
        $this->assertSame(1, (int) $details[1]->quantity);
        $this->assertFalse((bool) $details[1]->is_expired);
    }

    public function test_restock_perishable_creates_batch_and_increments_summary_stock(): void
    {
        [$business, $branch] = $this->createBusinessAndBranch();
        $today = Carbon::today();

        $product = $this->createPerishableProduct([
            'business_id' => $business->id,
            'current_stock' => 0,
        ]);

        $service = app(StockAdjustmentService::class);

        $restocked = $service->adjust(
            businessId: $business->id,
            product: $product,
            data: [
                'quantity_change' => 5,
                'type' => 'restock',
                'expiration_date' => $today->copy()->addDays(10)->toDateString(),
                'cost_unit' => 12.5,
            ],
            branchId: $branch->id,
        );

        $restocked->refresh();
        $this->assertSame(5, (int) $restocked->current_stock);

        $batch = Batch::query()->where('product_id', $product->id)->where('branch_id', $branch->id)->first();
        $this->assertNotNull($batch);
        $this->assertSame(5, (int) $batch->quantity_available);
        $this->assertSame(
            $today->copy()->addDays(10)->toDateString(),
            Carbon::parse($batch->expiration_date)->toDateString()
        );

        $summary = ProductBranchStock::query()
            ->where('business_id', $business->id)
            ->where('product_id', $product->id)
            ->where('branch_id', $branch->id)
            ->first();

        $this->assertNotNull($summary);
        $this->assertSame(5, (int) $summary->current_stock);
    }

    public function test_merma_consumes_batches_creating_inventory_movements(): void
    {
        [$business, $branch] = $this->createBusinessAndBranch();
        $today = Carbon::today();

        $product = $this->createPerishableProduct([
            'business_id' => $business->id,
            'expired_mode' => 'bloquear',
            'current_stock' => 10,
        ]);

        ProductBranchStock::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'current_stock' => 10,
            'minimum_stock' => 0,
        ]);

        $bEarly = Batch::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity_received' => 4,
            'quantity_available' => 4,
            'expiration_date' => $today->copy()->subDay()->toDateString(),
            'cost_unit' => 1,
            'received_at' => $today->copy()->subDays(4),
        ]);

        $bLate = Batch::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity_received' => 6,
            'quantity_available' => 6,
            'expiration_date' => $today->copy()->addDays(5)->toDateString(),
            'cost_unit' => 1,
            'received_at' => $today->copy()->subDays(2),
        ]);

        $service = app(StockAdjustmentService::class);

        $service->adjust(
            businessId: $business->id,
            product: $product,
            data: [
                'quantity_change' => -3,
                'type' => 'adjustment',
                'reason' => 'damaged',
            ],
            branchId: $branch->id
        );

        $bEarly->refresh();
        $bLate->refresh();

        // FEFO/expiration asc should consume earliest batch.
        $this->assertSame(1, (int) $bEarly->quantity_available);
        $this->assertSame(6, (int) $bLate->quantity_available);

        $this->assertDatabaseCount('inventory_movements', 1);
        $movement = \App\Models\InventoryMovement::query()->where('business_id', $business->id)->first();
        $this->assertSame('merma', $movement->type);
        $this->assertSame($bEarly->id, (int) $movement->batch_id);
    }
}

