<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StockAdjustmentRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Branch;
use App\Models\InventoryLog;
use App\Models\ProductBranchStock;
use App\Repositories\ProductRepository;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(
        private ProductRepository $productRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Product::class);
        $perPage = min((int) $request->get('per_page', 15), 50);
        $products = $this->productRepository->paginateForBusiness($request->user()->business_id, $perPage);
        return response()->json($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', \App\Models\Product::class);
        $businessId = (int) $request->user()->business_id;
        $validated = $request->validated();

        // For perishables, stock must come from batch receipts (needs expiration_date).
        if ((bool) ($validated['perecedero'] ?? false) && ((int) ($validated['current_stock'] ?? 0)) > 0) {
            return response()->json([
                'message' => 'For perishable products, initial stock must be 0. Use batch receipts/merma with expiration_date.',
            ], 422);
        }

        $product = $this->productRepository->createForBusiness($businessId, $validated);

        // Multi-sucursal (MVP): el stock inicial del producto se asigna a la sucursal por defecto.
        $branches = Branch::query()->where('business_id', $businessId)->get();
        if ($branches->isNotEmpty()) {
            $defaultBranchId = (int) $branches->first()->id;
            foreach ($branches as $branch) {
                ProductBranchStock::query()->updateOrInsert(
                    [
                        'business_id' => $businessId,
                        'product_id' => $product->id,
                        'branch_id' => (int) $branch->id,
                    ],
                    [
                        'current_stock' => (int) $branch->id === $defaultBranchId ? (int) $product->current_stock : 0,
                        'minimum_stock' => (int) $product->minimum_stock,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        return response()->json($product, 201);
    }

    public function show(Request $request, int $product): JsonResponse
    {
        $product = $this->productRepository->findForBusiness($product, $request->user()->business_id);
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
        $this->authorize('view', $product);
        return response()->json($product);
    }

    public function update(UpdateProductRequest $request, int $product): JsonResponse
    {
        $product = $this->productRepository->findForBusiness($product, $request->user()->business_id);
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
        $this->authorize('update', $product);

        $businessId = (int) $request->user()->business_id;
        $validated = $request->validated();

        // Perishable products should be adjusted via batches (stock adjustments),
        // not by directly editing current_stock.
        if ((bool) $product->perecedero && array_key_exists('current_stock', $validated)) {
            unset($validated['current_stock']);
        }

        $product = $this->productRepository->update($product, $validated);

        // Mantener coherencia con product_branch_stocks (MVP):
        // Si se actualiza stock (total compartido), redistribuimos proporcionalmente entre sucursales.
        $needsStockRedistribution = array_key_exists('current_stock', $validated) || array_key_exists('minimum_stock', $validated);
        if ($needsStockRedistribution) {
            $branches = Branch::query()->where('business_id', $businessId)->get();
            if ($branches->isNotEmpty()) {
                $defaultBranchId = (int) $branches->first()->id;
                $stocks = ProductBranchStock::query()
                    ->where('business_id', $businessId)
                    ->where('product_id', $product->id)
                    ->get();

                // Ensure rows exist for each branch.
                foreach ($branches as $branch) {
                    if (!$stocks->contains('branch_id', $branch->id)) {
                        ProductBranchStock::query()->create([
                            'business_id' => $businessId,
                            'product_id' => $product->id,
                            'branch_id' => (int) $branch->id,
                            'current_stock' => 0,
                            'minimum_stock' => (int) $product->minimum_stock,
                        ]);
                    }
                }
                $stocks = ProductBranchStock::query()
                    ->where('business_id', $businessId)
                    ->where('product_id', $product->id)
                    ->get();

                if (array_key_exists('current_stock', $validated)) {
                    $desiredTotal = (int) $product->current_stock;
                    $currentSum = (int) $stocks->sum('current_stock');

                    $distributedSum = 0;
                    $newValues = [];
                    foreach ($stocks as $s) {
                        if ($currentSum <= 0) {
                            $newValues[$s->id] = (int) ($s->branch_id === $defaultBranchId ? $desiredTotal : 0);
                        } else {
                            $newVal = (int) floor(((int) $s->current_stock / $currentSum) * $desiredTotal);
                            $newValues[$s->id] = $newVal;
                            $distributedSum += $newVal;
                        }
                    }

                    $remainder = $desiredTotal - $distributedSum;
                    if ($remainder !== 0) {
                        $defaultStock = $stocks->firstWhere('branch_id', $defaultBranchId);
                        if ($defaultStock) {
                            $newValues[$defaultStock->id] = (int) $newValues[$defaultStock->id] + $remainder;
                        }
                    }

                    foreach ($stocks as $s) {
                        $s->update(['current_stock' => (int) $newValues[$s->id]]);
                    }
                }

                if (array_key_exists('minimum_stock', $validated)) {
                    $desiredMinTotal = (int) $product->minimum_stock;
                    $minSum = (int) $stocks->sum('minimum_stock');

                    $distributedSum = 0;
                    $newValues = [];
                    foreach ($stocks as $s) {
                        if ($minSum <= 0) {
                            $newValues[$s->id] = (int) ($s->branch_id === $defaultBranchId ? $desiredMinTotal : 0);
                        } else {
                            $newVal = (int) floor(((int) $s->minimum_stock / $minSum) * $desiredMinTotal);
                            $newValues[$s->id] = $newVal;
                            $distributedSum += $newVal;
                        }
                    }

                    $remainder = $desiredMinTotal - $distributedSum;
                    if ($remainder !== 0) {
                        $defaultStock = $stocks->firstWhere('branch_id', $defaultBranchId);
                        if ($defaultStock) {
                            $newValues[$defaultStock->id] = (int) $newValues[$defaultStock->id] + $remainder;
                        }
                    }

                    foreach ($stocks as $s) {
                        $s->update(['minimum_stock' => (int) $newValues[$s->id]]);
                    }
                }
            }
        }

        return response()->json($product);
    }

    public function destroy(Request $request, int $product): JsonResponse
    {
        $product = $this->productRepository->findForBusiness($product, $request->user()->business_id);
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
        $this->authorize('delete', $product);
        $this->productRepository->delete($product);
        return response()->json(null, 204);
    }

    public function adjustStock(StockAdjustmentRequest $request, int $product): JsonResponse
    {
        $productModel = $this->productRepository->findForBusiness($product, $request->user()->business_id);
        if (!$productModel) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
        $this->authorize('update', $productModel);

        $change = (int) $request->validated()['quantity_change'];
        $type = $request->validated()['type'] ?? 'adjustment';

        $businessId = (int) $request->user()->business_id;
        $branchId = $request->validated()['branch_id'] ?? null;
        if ($branchId === null) {
            $branchId = Branch::query()->where('business_id', $businessId)->orderBy('id')->value('id');
        }

        try {
            $productModel = app(StockAdjustmentService::class)->adjust(
                businessId: $businessId,
                product: $productModel,
                data: $request->validated(),
                branchId: (int) $branchId
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($productModel->fresh());
    }
}
