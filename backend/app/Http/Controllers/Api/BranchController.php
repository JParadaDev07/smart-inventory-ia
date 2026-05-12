<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBranchStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $businessId = (int) $request->user()->business_id;

        $branches = Branch::query()
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->get(['id', 'name']);

        return response()->json([
            'data' => $branches,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $businessId = (int) $request->user()->business_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $branch = Branch::create([
            'business_id' => $businessId,
            'name' => $data['name'],
        ]);

        // Seed branch stock rows for all existing products (initial stock = 0).
        $products = Product::query()->where('business_id', $businessId)->get(['id', 'minimum_stock']);
        foreach ($products as $product) {
            ProductBranchStock::query()->updateOrCreate(
                [
                    'business_id' => $businessId,
                    'product_id' => (int) $product->id,
                    'branch_id' => (int) $branch->id,
                ],
                [
                    'current_stock' => 0,
                    'minimum_stock' => (int) $product->minimum_stock,
                ]
            );
        }

        return response()->json([
            'data' => [
                'id' => $branch->id,
                'name' => $branch->name,
            ],
        ], 201);
    }
}

