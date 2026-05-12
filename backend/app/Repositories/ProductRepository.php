<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    public function __construct(
        private Product $model
    ) {}

    public function paginateForBusiness(int $businessId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->forBusiness($businessId)->orderBy('name')->paginate($perPage);
    }

    public function findForBusiness(int $id, int $businessId): ?Product
    {
        return $this->model->forBusiness($businessId)->find($id);
    }

    public function createForBusiness(int $businessId, array $data): Product
    {
        $data['business_id'] = $businessId;
        return $this->model->create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);
        return $product->fresh();
    }

    public function delete(Product $product): bool
    {
        return $product->delete();
    }

    public function countForBusiness(int $businessId): int
    {
        return $this->model->forBusiness($businessId)->count();
    }

    public function lowStockCountForBusiness(int $businessId): int
    {
        return $this->model->forBusiness($businessId)
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->count();
    }

    /**
     * @return Collection<int, Product>
     */
    public function lowStockForBusiness(int $businessId): Collection
    {
        return $this->model->forBusiness($businessId)
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    public function allForBusiness(int $businessId): Collection
    {
        return $this->model->forBusiness($businessId)->orderBy('name')->get();
    }
}
