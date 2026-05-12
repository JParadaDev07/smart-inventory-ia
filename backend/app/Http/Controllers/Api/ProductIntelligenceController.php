<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\ProductRepository;
use App\Services\AI\AIService;
use App\Services\Inventory\InventoryAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductIntelligenceController extends Controller
{
    public function __construct(
        private ProductRepository $productRepository,
        private InventoryAnalysisService $inventoryAnalysis,
        private AIService $aiService
    ) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $product = $this->productRepository->findForBusiness($id, $request->user()->business_id);
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
        $request->user()->can('view', $product);

        $intelligence = $this->inventoryAnalysis->getIntelligence($product, 30, $request->user()->business_id);
        $responseAiAdvanced = null;
        if ($request->user()->business->isOnProPlan()) {
            $responseAiAdvanced = $this->aiService->getAdvancedPrediction($product, $request->user()->business_id);
        }

        $response = [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'current_stock' => $product->current_stock,
                'minimum_stock' => $product->minimum_stock,
                'perecedero' => (bool) $product->perecedero,
                'expired_mode' => $product->expired_mode,
                'expiration_alert_days' => $product->expiration_alert_days,
            ],
            'intelligence' => [
                'average_daily_demand' => $intelligence['average_daily_demand'],
                'safety_stock' => $intelligence['safety_stock'],
                'reorder_point' => $intelligence['reorder_point'],
                'recommended_purchase_quantity' => $intelligence['recommended_purchase_quantity'],
                'estimated_stock_out_date' => $intelligence['estimated_stock_out_date'],
            ],
        ];

        if ($request->user()->business->isOnProPlan()) {
            $aiPrediction = $this->aiService->getPrediction($product, $request->user()->business_id);
            $response['ai_prediction'] = $aiPrediction;
            if ($responseAiAdvanced) {
                $response['ai_prediction_advanced'] = $responseAiAdvanced;
            }
        }

        return response()->json($response);
    }

    /**
     * Pro-only route: returns only AI prediction. Protected by subscription:pro middleware.
     */
    public function aiPrediction(Request $request, int $id): JsonResponse
    {
        $product = $this->productRepository->findForBusiness($id, $request->user()->business_id);
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
        $request->user()->can('view', $product);

        $aiPrediction = $this->aiService->getPrediction($product, $request->user()->business_id);
        return response()->json(['ai_prediction' => $aiPrediction]);
    }
}
