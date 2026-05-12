<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\Product;
use App\Services\AI\AIService;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class AIServicePlanFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_fastapi_not_called_when_business_is_not_pro(): void
    {
        $business = Business::create(['name' => 'Basic Biz']);
        Subscription::create([
            'business_id' => $business->id,
            'plan' => 'basic',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
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

        $this->setUpHttpFakeNoCalls();

        config(['services.ai.enabled' => true]);

        $result = app(AIService::class)->getPrediction($product, $business->id);
        $this->assertSame('local', $result['source'] ?? null);

        Http::assertNothingSent();
    }

    public function test_fastapi_called_for_pro_when_enabled(): void
    {
        $business = Business::create(['name' => 'Pro Biz']);
        Subscription::create([
            'business_id' => $business->id,
            'plan' => 'pro',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
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

        config(['services.ai.enabled' => true]);
        config(['services.fastapi.url' => 'http://fastapi.test']);
        config(['services.fastapi.timeout' => 5]);

        Http::fake(function ($request) {
            return Http::response([
                'predicted_next_30_days' => 123,
                'predicted_daily_average' => 4.2,
                'predicted_stock_out_date' => now()->addDays(5)->toDateString(),
                'recommended_purchase_quantity' => 7,
            ], 200);
        });

        $result = app(AIService::class)->getPrediction($product, $business->id);
        $this->assertSame('ai', $result['source'] ?? null);
    }

    private function setUpHttpFakeNoCalls(): void
    {
        Http::fake(function () {
            $this->fail('FastAPI should not be called for non-Pro business.');
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}

