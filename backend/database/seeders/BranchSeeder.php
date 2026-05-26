<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * BranchSeeder
 *
 * Idempotente: detecta qué businesses no tienen branch y crea:
 *   1. Un branch "Sucursal Principal" por business.
 *   2. Un ProductBranchStock por cada producto de ese business,
 *      copiando current_stock y minimum_stock del producto.
 *
 * Seguro de correr múltiples veces — usa ON DUPLICATE KEY UPDATE
 * para product_branch_stocks (unique key: product_id + branch_id).
 *
 * Uso:
 *   php artisan db:seed --class=BranchSeeder
 */
class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('BranchSeeder: buscando businesses sin branch...');

        // Obtener todos los business_id que NO tienen ningún branch registrado
        $businessesWithoutBranch = DB::table('businesses')
            ->leftJoin('branches', 'businesses.id', '=', 'branches.business_id')
            ->whereNull('branches.id')
            ->pluck('businesses.id');

        if ($businessesWithoutBranch->isEmpty()) {
            $this->command->info('BranchSeeder: todos los businesses ya tienen al menos un branch. Nada que hacer.');
            return;
        }

        $this->command->info("BranchSeeder: encontrados {$businessesWithoutBranch->count()} business(es) sin branch.");

        foreach ($businessesWithoutBranch as $businessId) {
            // 1. Crear el branch por defecto
            $branchId = DB::table('branches')->insertGetId([
                'business_id' => $businessId,
                'name'        => 'Sucursal Principal',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $this->command->line("  → Business #{$businessId}: branch creado con id={$branchId}");

            // 2. Crear product_branch_stocks para cada producto del business
            $products = DB::table('products')
                ->where('business_id', $businessId)
                ->select('id', 'current_stock', 'minimum_stock')
                ->get();

            if ($products->isEmpty()) {
                $this->command->line("    (sin productos para este business, omitiendo stocks)");
                continue;
            }

            $rows = $products->map(fn ($p) => [
                'business_id'   => $businessId,
                'product_id'    => $p->id,
                'branch_id'     => $branchId,
                'current_stock' => (int) $p->current_stock,
                'minimum_stock' => (int) $p->minimum_stock,
                'created_at'    => now(),
                'updated_at'    => now(),
            ])->toArray();

            // upsert es idempotente gracias al unique(product_id, branch_id)
            DB::table('product_branch_stocks')->upsert(
                $rows,
                ['product_id', 'branch_id'],        // unique key
                ['current_stock', 'minimum_stock', 'updated_at']  // columnas a actualizar si existe
            );

            $this->command->line("    → {$products->count()} product_branch_stock(s) creados/actualizados.");
        }

        $this->command->info('BranchSeeder: completado.');
    }
}
