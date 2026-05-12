<?php

namespace App\Services\AI;

use App\Models\Business;
use App\Models\AiUsageLog;
use App\Models\Product;
use App\Models\SaleItem;
use App\Services\Inventory\InventoryAnalysisService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    public function __construct(
        private InventoryAnalysisService $localIntelligence
    ) {
        // FastAPI URL and timeout read from config when needed
    }

    public function getPrediction(Product $product, int $businessId): array
    {
        // Perishable products must be predicted using batch-level FEFO/expiry logic.
        // The external FastAPI model currently consumes only `current_stock`.
        if ((bool) $product->perecedero) {
            $start = microtime(true);
            $fallback = $this->fallbackToLocal($product, $businessId);
            $responseTimeMs = (int) ((microtime(true) - $start) * 1000);
            $this->logUsage($businessId, $product->id, false, $responseTimeMs, 'local');
            return $fallback;
        }

        $business = Business::query()->find($businessId);
        if (!$business || !$business->isOnProPlan()) {
            $start = microtime(true);
            $fallback = $this->fallbackToLocal($product, $businessId);
            $responseTimeMs = (int) ((microtime(true) - $start) * 1000);
            $this->logUsage($businessId, $product->id, false, $responseTimeMs, 'plan');
            return $fallback;
        }

        if (!config('services.ai.enabled', true)) {
            $start = microtime(true);
            $fallback = $this->fallbackToLocal($product, $businessId);
            $responseTimeMs = (int) ((microtime(true) - $start) * 1000);
            $this->logUsage($businessId, $product->id, false, $responseTimeMs, 'disabled');
            return $fallback;
        }

        $start = microtime(true);
        $historicalSales = $this->collectHistoricalSales($product->id, $businessId);
        $baseUrl = rtrim(config('services.fastapi.url'), '/');
        $timeout = (int) config('services.fastapi.timeout', 5);

        try {
            $response = Http::timeout($timeout)
                ->post("{$baseUrl}/predict", [
                    'historical_sales' => $historicalSales,
                    'lead_time_days' => $product->supplier_lead_time_days,
                    'current_stock' => $product->current_stock,
                ]);

            $responseTimeMs = (int) ((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                $this->logUsage($businessId, $product->id, true, $responseTimeMs, null);
                return [
                    'predicted_next_30_days' => $data['predicted_next_30_days'] ?? 0,
                    'predicted_daily_average' => $data['predicted_daily_average'] ?? 0,
                    'predicted_stock_out_date' => $data['predicted_stock_out_date'] ?? null,
                    'recommended_purchase_quantity' => $data['recommended_purchase_quantity'] ?? 0,
                    'source' => 'ai',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('FastAPI predict failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
        }

        $responseTimeMs = (int) ((microtime(true) - $start) * 1000);
        $this->logUsage($businessId, $product->id, false, $responseTimeMs, 'local');

        return $this->fallbackToLocal($product, $businessId);
    }

    public function getAdvancedPrediction(Product $product, int $businessId): ?array
    {
        if ((bool) $product->perecedero) {
            return null;
        }

        $business = Business::query()->find($businessId);
        if (!$business || !$business->isOnProPlan()) {
            return null;
        }

        if (!config('services.ai.enabled', true)) {
            return null;
        }

        $start = microtime(true);
        $historicalSales = $this->collectHistoricalSales($product->id, $businessId);
        $baseUrl = rtrim(config('services.fastapi.url'), '/');
        $timeout = (int) config('services.fastapi.timeout', 5);

        try {
            $response = Http::timeout($timeout)
                ->post("{$baseUrl}/predict/advanced", [
                    'historical_sales' => $historicalSales,
                    'lead_time_days' => $product->supplier_lead_time_days,
                    'current_stock' => $product->current_stock,
                ]);

            $responseTimeMs = (int) ((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                $this->logUsage($businessId, $product->id, true, $responseTimeMs, null);
                /** @var array<string, array<string, mixed>> $horizons */
                $horizons = $data['horizons'] ?? [];

                return [
                    '7' => $horizons['7'] ?? null,
                    '30' => $horizons['30'] ?? null,
                    '90' => $horizons['90'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('FastAPI predict_advanced failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
        }

        return null;
    }

    private function collectHistoricalSales(int $productId, int $businessId): array
    {
        $items = SaleItem::query()
            ->where('product_id', $productId)
            ->whereHas('sale', fn ($q) => $q->where('business_id', $businessId))
            ->with('sale:id,created_at')
            ->orderBy('sale_id')
            ->get();

        $byDate = [];
        foreach ($items as $item) {
            $date = Carbon::parse($item->sale->created_at)->toDateString();
            $byDate[$date] = ($byDate[$date] ?? 0) + $item->quantity;
        }

        return collect($byDate)
            ->map(fn ($qty, $date) => ['date' => $date, 'quantity' => $qty])
            ->values()
            ->all();
    }

    private function fallbackToLocal(Product $product, int $businessId): array
    {
        $intel = $this->localIntelligence->getIntelligence($product, 30, $businessId);
        return [
            'predicted_next_30_days' => (int) round($intel['average_daily_demand'] * 30),
            'predicted_daily_average' => $intel['average_daily_demand'],
            'predicted_stock_out_date' => $intel['estimated_stock_out_date'],
            'recommended_purchase_quantity' => $intel['recommended_purchase_quantity'],
            'source' => 'local',
        ];
    }

    private function logUsage(int $businessId, ?int $productId, bool $success, int $responseTimeMs, ?string $fallbackUsed): void
    {
        AiUsageLog::create([
            'business_id' => $businessId,
            'product_id' => $productId,
            'endpoint' => '/predict',
            'success' => $success,
            'response_time_ms' => $responseTimeMs,
            'fallback_used' => $fallbackUsed,
        ]);
    }
}
