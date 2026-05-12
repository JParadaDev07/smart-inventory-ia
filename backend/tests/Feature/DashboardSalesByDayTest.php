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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardSalesByDayTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_sales_by_day_uses_sale_date_for_grouping(): void
    {
        $today = Carbon::create(2026, 3, 18)->startOfDay();
        Carbon::setTestNow($today);

        $business = Business::create(['name' => 'Test Business']);
        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Test User',
            'email' => 'test-dashboard@example.com',
            'password' => Hash::make('password'),
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);
        $business->update(['owner_user_id' => $user->id]);

        // Create an active trial subscription so middleware doesn't block anything unexpected.
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

        // Create a past sale (within last 30 days).
        $saleDate = $today->copy()->subDays(5)->toDateString(); // 2026-03-13

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

        $dashboard = $this->actingAs($user, 'sanctum')->getJson('/api/dashboard');
        $dashboard->assertOk();

        $payload = $dashboard->json();
        $this->assertArrayHasKey('sales_by_day', $payload);
        $this->assertIsArray($payload['sales_by_day']);

        $found = collect($payload['sales_by_day'])->first(fn ($row) => ($row['date'] ?? null) === $saleDate);
        $this->assertNotNull($found, 'Expected sale date to exist in sales_by_day output');
        $this->assertSame(100.0, (float) ($found['total'] ?? 0), 'Expected daily total to match sale date');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow();
    }
}

