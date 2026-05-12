<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\ProductBranchStock;
use App\Http\Controllers\Controller;
use App\Repositories\ProductRepository;
use App\Services\Sales\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private ProductRepository $productRepository,
        private SaleService $saleService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $businessId = $request->user()->business_id;

        $totalProducts = $this->productRepository->countForBusiness($businessId);
        $lowStockProducts = $this->productRepository->lowStockCountForBusiness($businessId);
        $salesLast30Days = $this->saleService->salesTotalLastDays($businessId, 30);
        $salesByDay = $this->saleService->salesByDayLastDays($businessId, 30);
        $topProducts = $this->saleService->topProductsLastDays($businessId, 30, 5);

        // Multi-sucursal: resumen separado por sucursal + conteo total compartido (los campos
        // existentes arriba ya representan el conteo total compartido, porque `products.current_stock`
        // se mantiene como suma de los stocks por sucursal).
        $branches = Branch::query()->where('business_id', $businessId)->get();
        $branchSummaries = $branches->map(function (Branch $branch) use ($businessId) {
            $total = (int) ProductBranchStock::query()
                ->where('business_id', $businessId)
                ->where('branch_id', $branch->id)
                ->distinct('product_id')
                ->count('product_id');

            $lowStock = (int) ProductBranchStock::query()
                ->where('business_id', $businessId)
                ->where('branch_id', $branch->id)
                ->whereColumn('current_stock', '<=', 'minimum_stock')
                ->count();

            $sales = (float) \App\Models\Sale::query()
                ->withoutGlobalScope('business')
                ->where('business_id', $businessId)
                ->where('branch_id', $branch->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->sum('total_amount');

            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'total_products' => $total,
                'low_stock_products' => $lowStock,
                'sales_last_30_days' => round($sales, 2),
            ];
        })->values()->all();

        $aiAlerts = 0;
        if ($request->user()->business->isOnProPlan()) {
            $products = $this->productRepository->allForBusiness($businessId);
            foreach ($products as $product) {
                $recommended = app(\App\Services\Inventory\InventoryAnalysisService::class)->recommendedPurchase($product, 30, $businessId);
                if ($recommended > 0) {
                    $aiAlerts++;
                }
            }
        } else {
            $products = $this->productRepository->allForBusiness($businessId);
            foreach ($products as $product) {
                if (app(\App\Services\Inventory\InventoryAnalysisService::class)->recommendedPurchase($product, 30, $businessId) > 0) {
                    $aiAlerts++;
                }
            }
        }

        return response()->json([
            'total_products' => $totalProducts,
            'low_stock_products' => $lowStockProducts,
            'sales_last_30_days' => round($salesLast30Days, 2),
            'sales_by_day' => $salesByDay,
            'ai_alerts' => $aiAlerts,
            'top_products' => $topProducts,
            'branch_summaries' => $branchSummaries,
        ]);
    }
}
