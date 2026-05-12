<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Product;
use App\Models\ProductBranchStock;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SaleDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_date_parameter_is_persisted_and_used_in_listing(): void
    {
        $business = Business::create(['name' => 'Test Business']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Test User',
            'email' => 'test-sale-date@example.com',
            'password' => Hash::make('password'),
        ]);
        $business->update(['owner_user_id' => $user->id]);

        Subscription::create([
            'business_id' => $business->id,
            'plan' => 'basic',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'current_period_end' => null,
        ]);

        $branch = Branch::create([
            'business_id' => $business->id,
            'name' => 'Main',
        ]);

        $product = Product::create([
            'business_id' => $business->id,
            'name' => 'Cement',
            'sku' => 'C-1',
            'cost_price' => 1000,
            'sale_price' => 2000,
            'current_stock' => 10,
            'minimum_stock' => 0,
            'supplier_lead_time_days' => 0,
            'perecedero' => false,
            'expired_mode' => 'permitir',
            'expiration_alert_days' => 7,
        ]);

        ProductBranchStock::create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'current_stock' => 10,
            'minimum_stock' => 0,
        ]);

        $saleDate = '2026-03-01';

        $create = $this->actingAs($user, 'sanctum')->postJson('/api/sales', [
            'date' => $saleDate,
            'branch_id' => $branch->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $create->assertCreated();
        $createdSale = $create->json();
        $this->assertIsArray($createdSale);

        $this->assertStringStartsWith($saleDate, (string) ($createdSale['created_at'] ?? ''));

        $list = $this->actingAs($user, 'sanctum')->getJson('/api/sales?per_page=15');
        $list->assertOk();
        $payload = $list->json();

        $this->assertArrayHasKey('data', $payload);
        $this->assertNotEmpty($payload['data']);

        $found = collect($payload['data'])->first(fn ($s) => (int) ($s['id'] ?? 0) === (int) ($createdSale['id'] ?? 0));
        $this->assertNotNull($found, 'Created sale must be present in listings');
        $this->assertStringStartsWith($saleDate, (string) ($found['created_at'] ?? ''));
    }
}

