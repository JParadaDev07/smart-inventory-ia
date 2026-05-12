<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Services\Sales\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(
        private SaleService $saleService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 50);
        $query = \App\Models\Sale::with('items.product');

        $from = $request->get('from');
        $to = $request->get('to');
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = strtolower($request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if ($sortBy === 'total') {
            $query->orderBy('total_amount', $sortDir);
        } elseif ($sortBy === 'updated_at') {
            $query->orderBy('updated_at', $sortDir);
        } else {
            $query->orderBy('created_at', $sortDir);
        }

        $sales = $query->paginate($perPage);
        return response()->json($sales);
    }

    public function show(int $id): JsonResponse
    {
        $sale = \App\Models\Sale::with('items.product')
            ->where('business_id', request()->user()->business_id)
            ->findOrFail($id);
        return response()->json($sale);
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $sale = $this->saleService->createSale(
                $request->user()->business_id,
                $data['items'],
                $data['date'] ?? null,
                $data['branch_id'] ?? null
            );
            return response()->json($sale, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $sale = \App\Models\Sale::forBusiness($request->user()->business_id)->findOrFail($id);
        $this->saleService->deleteSale($request->user()->business_id, $sale->id);

        return response()->json(null, 204);
    }
}
