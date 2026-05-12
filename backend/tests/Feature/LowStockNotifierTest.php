<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Inventory\InventoryAnalysisService;
use App\Services\Notifications\LowStockNotifier;
use App\Services\Notifications\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_not_notify_when_not_pro(): void
    {
        $business = Business::factory()->create([
            'whatsapp_number' => '573001112233',
            'whatsapp_enabled' => true,
        ]);
        $product = Product::factory()->create([
            'business_id' => $business->id,
        ]);

        $whatsapp = $this->createMock(WhatsAppService::class);
        $whatsapp->expects($this->never())->method('sendLowStockMenu');
        $whatsapp->method('isEnabled')->willReturn(true);

        $analysis = $this->createMock(InventoryAnalysisService::class);
        $analysis->method('getIntelligence')->willReturn([
            'recommended_purchase_quantity' => 10,
            'estimated_stock_out_date' => now()->toDateString(),
        ]);

        $notifier = new LowStockNotifier($whatsapp, $analysis);

        $notifier->notify($business, $product);
    }
}

