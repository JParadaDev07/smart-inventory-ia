<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductBranchStock;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DatabaseSeeder — Ferretería Demo Colombia
 *
 * Escenarios cubiertos para las secciones de Alertas e Inteligencia de Producto:
 *
 *  ① recommended_purchase  →  productos con ventas históricas que superan el
 *                              punto de reorden (demand > stock / lead_time + safety_stock).
 *
 *  ② expiring_soon         →  lotes perecederos con expiration_date dentro de
 *                              los próximos `expiration_alert_days` del producto.
 *
 *  ③ low_stock             →  productos cuyo current_stock <= minimum_stock.
 *
 *  Stocks deliberadamente NO lineales: cada branch puede tener distribuciones
 *  asimétricas (ej. 70 % en sucursal principal, 30 % en sucursal norte).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─────────────────────────────────────────────
        // 1. BUSINESS
        // ─────────────────────────────────────────────
        $business = Business::create([
            'name'    => 'Ferretería El Maestro',
            'address' => 'Cra. 15 #45-32, Bogotá',
            'phone'   => '+57 310 555 0100',
        ]);

        $user = User::create([
            'business_id' => $business->id,
            'name'        => 'Julián Maestro',
            'email'       => 'demo@ferreteria.co',
            'password'    => Hash::make('password'),
        ]);

        $business->update(['owner_user_id' => $user->id]);

        Subscription::create([
            'business_id'   => $business->id,
            'plan'          => 'pro',
            'status'        => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        // ─────────────────────────────────────────────
        // 2. BRANCHES (dos sucursales)
        // ─────────────────────────────────────────────
        $branchPrincipal = Branch::create([
            'business_id' => $business->id,
            'name'        => 'Sucursal Principal',
        ]);

        $branchNorte = Branch::create([
            'business_id' => $business->id,
            'name'        => 'Sucursal Norte',
        ]);

        // ─────────────────────────────────────────────
        // 3. PRODUCTOS
        //    (precio COP, sin decimales en ferretería)
        //
        //  Columnas extra (migración 2026_03_18_000002):
        //    perecedero, expired_mode, expiration_alert_days
        // ─────────────────────────────────────────────

        /**
         * Estructura helper:
         *  name, sku, cost_price, sale_price,
         *  current_stock, minimum_stock, supplier_lead_time_days,
         *  perecedero, expired_mode, expiration_alert_days,
         *  stock_principal, stock_norte   ← distribución por sucursal
         */
        $productDefs = [
            // ── No perecederos ──────────────────────────────────────────
            [
                // ① Alta rotación — ventas históricas harán que recommended_purchase se dispare
                'name' => 'Puntilla galvanizada 2"',
                'sku'  => 'PNT-GAL-2',
                'cost_price' => 3_800, 'sale_price' => 6_500,
                'current_stock' => 28, 'minimum_stock' => 50,
                'supplier_lead_time_days' => 5,
                'perecedero' => false, 'expired_mode' => 'bloquear', 'expiration_alert_days' => 7,
                'stock_principal' => 20, 'stock_norte' => 8,
            ],
            [
                // ③ low_stock: stock justo en el mínimo
                'name' => 'Tornillo cabeza plana 1/2"',
                'sku'  => 'TRN-CHP-1/2',
                'cost_price' => 1_200, 'sale_price' => 2_200,
                'current_stock' => 30, 'minimum_stock' => 30,
                'supplier_lead_time_days' => 7,
                'perecedero' => false, 'expired_mode' => 'bloquear', 'expiration_alert_days' => 7,
                'stock_principal' => 22, 'stock_norte' => 8,
            ],
            [
                // Normal — buen nivel de stock
                'name' => 'Alambre de amarre calibre 18',
                'sku'  => 'ALM-AMR-18',
                'cost_price' => 8_500, 'sale_price' => 14_900,
                'current_stock' => 95, 'minimum_stock' => 20,
                'supplier_lead_time_days' => 4,
                'perecedero' => false, 'expired_mode' => 'bloquear', 'expiration_alert_days' => 7,
                'stock_principal' => 60, 'stock_norte' => 35,
            ],
            [
                // ① Alta rotación — recomendación de compra
                'name' => 'Bisagra de acero 3"',
                'sku'  => 'BSG-ACR-3',
                'cost_price' => 2_900, 'sale_price' => 5_500,
                'current_stock' => 15, 'minimum_stock' => 40,
                'supplier_lead_time_days' => 6,
                'perecedero' => false, 'expired_mode' => 'bloquear', 'expiration_alert_days' => 7,
                'stock_principal' => 10, 'stock_norte' => 5,
            ],
            [
                // Normal — volumen medio
                'name' => 'Lija al agua grano 120',
                'sku'  => 'LJA-AGU-120',
                'cost_price' => 1_500, 'sale_price' => 2_800,
                'current_stock' => 200, 'minimum_stock' => 50,
                'supplier_lead_time_days' => 3,
                'perecedero' => false, 'expired_mode' => 'bloquear', 'expiration_alert_days' => 7,
                'stock_principal' => 140, 'stock_norte' => 60,
            ],
            [
                // ③ low_stock: debajo del mínimo
                'name' => 'Cinta de enmascarar 1"',
                'sku'  => 'CNT-ENM-1',
                'cost_price' => 3_200, 'sale_price' => 5_900,
                'current_stock' => 12, 'minimum_stock' => 25,
                'supplier_lead_time_days' => 4,
                'perecedero' => false, 'expired_mode' => 'bloquear', 'expiration_alert_days' => 7,
                'stock_principal' => 8, 'stock_norte' => 4,
            ],
            [
                // Alta rotación + lead_time largo → recommended_purchase
                'name' => 'Maceta de cantero 10 kg',
                'sku'  => 'MCT-CNT-10',
                'cost_price' => 12_000, 'sale_price' => 19_900,
                'current_stock' => 40, 'minimum_stock' => 35,
                'supplier_lead_time_days' => 10,
                'perecedero' => false, 'expired_mode' => 'bloquear', 'expiration_alert_days' => 7,
                'stock_principal' => 25, 'stock_norte' => 15,
            ],
            [
                // Poco movimiento — no genera alertas relevantes
                'name' => 'Candado de arco 40 mm',
                'sku'  => 'CDL-ARC-40',
                'cost_price' => 15_000, 'sale_price' => 26_500,
                'current_stock' => 60, 'minimum_stock' => 15,
                'supplier_lead_time_days' => 8,
                'perecedero' => false, 'expired_mode' => 'bloquear', 'expiration_alert_days' => 7,
                'stock_principal' => 45, 'stock_norte' => 15,
            ],

            // ── Perecederos (② expiring_soon) ───────────────────────────
            [
                // Lote por vencer en 3 días (dentro del umbral de 7 días)
                'name' => 'Sellante de silicona transparente 280 ml',
                'sku'  => 'SLL-SIL-280',
                'cost_price' => 8_500, 'sale_price' => 14_900,
                'current_stock' => 18, 'minimum_stock' => 10,
                'supplier_lead_time_days' => 5,
                'perecedero' => true, 'expired_mode' => 'bloquear', 'expiration_alert_days' => 7,
                'stock_principal' => 12, 'stock_norte' => 6,
            ],
            [
                // Lote por vencer en 5 días
                'name' => 'Pegante de contacto 250 ml',
                'sku'  => 'PGT-CTT-250',
                'cost_price' => 6_200, 'sale_price' => 11_500,
                'current_stock' => 22, 'minimum_stock' => 8,
                'supplier_lead_time_days' => 6,
                'perecedero' => true, 'expired_mode' => 'alerta', 'expiration_alert_days' => 10,
                'stock_principal' => 14, 'stock_norte' => 8,
            ],
            [
                // Lote por vencer en 12 días (dentro de umbral 15 días)
                'name' => 'Pintura vinilo blanco 1 galón',
                'sku'  => 'PNT-VNL-BLC-GL',
                'cost_price' => 28_000, 'sale_price' => 45_900,
                'current_stock' => 30, 'minimum_stock' => 12,
                'supplier_lead_time_days' => 7,
                'perecedero' => true, 'expired_mode' => 'alerta', 'expiration_alert_days' => 15,
                'stock_principal' => 20, 'stock_norte' => 10,
            ],
            [
                // Perecedero con buen stock — lote más lejano (no dispara alerta)
                'name' => 'Disolvente thinner x litro',
                'sku'  => 'DSL-THN-1L',
                'cost_price' => 9_800, 'sale_price' => 16_500,
                'current_stock' => 50, 'minimum_stock' => 15,
                'supplier_lead_time_days' => 5,
                'perecedero' => true, 'expired_mode' => 'bloquear', 'expiration_alert_days' => 7,
                'stock_principal' => 35, 'stock_norte' => 15,
            ],
        ];

        /** @var Product[] $products */
        $products = [];

        foreach ($productDefs as $def) {
            $product = Product::create([
                'business_id'            => $business->id,
                'name'                   => $def['name'],
                'sku'                    => $def['sku'],
                'cost_price'             => $def['cost_price'],
                'sale_price'             => $def['sale_price'],
                'current_stock'          => $def['current_stock'],
                'minimum_stock'          => $def['minimum_stock'],
                'supplier_lead_time_days' => $def['supplier_lead_time_days'],
                'perecedero'             => $def['perecedero'],
                'expired_mode'           => $def['expired_mode'],
                'expiration_alert_days'  => $def['expiration_alert_days'],
            ]);

            // ProductBranchStock — distribución no lineal por sucursal
            ProductBranchStock::create([
                'business_id'   => $business->id,
                'product_id'    => $product->id,
                'branch_id'     => $branchPrincipal->id,
                'current_stock' => $def['stock_principal'],
                'minimum_stock' => (int) ceil($def['minimum_stock'] * 0.7),
            ]);

            ProductBranchStock::create([
                'business_id'   => $business->id,
                'product_id'    => $product->id,
                'branch_id'     => $branchNorte->id,
                'current_stock' => $def['stock_norte'],
                'minimum_stock' => (int) floor($def['minimum_stock'] * 0.3),
            ]);

            $products[$def['sku']] = $product;
        }

        // ─────────────────────────────────────────────
        // 4. BATCHES
        //    No perecederos → expiration_date NULL.
        //    Perecederos   → múltiples lotes con fechas reales.
        // ─────────────────────────────────────────────

        $today = Carbon::today();

        // Helper para crear lote + inventario de entrada
        $makeBatch = function (
            Product $product,
            Branch $branch,
            int $qty,
            ?string $expirationDate,
            ?string $receivedAt = null
        ) use ($business, $today): Batch {
            $received = $receivedAt ? Carbon::parse($receivedAt) : $today->copy()->subDays(rand(1, 30));

            $batch = Batch::create([
                'business_id'      => $business->id,
                'product_id'       => $product->id,
                'branch_id'        => $branch->id,
                'quantity_received' => $qty,
                'quantity_available' => $qty,
                'expiration_date'  => $expirationDate,
                'cost_unit'        => $product->cost_price,
                'received_at'      => $received,
            ]);

            InventoryMovement::create([
                'business_id'    => $business->id,
                'product_id'     => $product->id,
                'branch_id'      => $branch->id,
                'batch_id'       => $batch->id,
                'type'           => 'entrada',
                'quantity'       => $qty,
                'reason'         => 'Inventario inicial seeder',
                'is_expired'     => false,
                'reference_type' => null,
                'reference_id'   => null,
                'occurred_at'    => $received,
            ]);

            return $batch;
        };

        // ── No perecederos (lote único sin vencimiento) ──────────────────
        $nonPerishableSkus = [
            'PNT-GAL-2', 'TRN-CHP-1/2', 'ALM-AMR-18',
            'BSG-ACR-3', 'LJA-AGU-120', 'CNT-ENM-1',
            'MCT-CNT-10', 'CDL-ARC-40',
        ];

        foreach ($nonPerishableSkus as $sku) {
            $p = $products[$sku];
            $def = collect($productDefs)->firstWhere('sku', $sku);
            $makeBatch($p, $branchPrincipal, $def['stock_principal'], null);
            $makeBatch($p, $branchNorte, $def['stock_norte'], null);
        }

        // ── Perecederos — lotes con fechas específicas ───────────────────

        // Sellante: lote A vence en 3 días (DISPARA alerta), lote B vence en 60 días
        $pSellante = $products['SLL-SIL-280'];
        $makeBatch($pSellante, $branchPrincipal, 8, $today->copy()->addDays(3)->toDateString());
        $makeBatch($pSellante, $branchPrincipal, 4, $today->copy()->addDays(60)->toDateString());
        $makeBatch($pSellante, $branchNorte, 6, $today->copy()->addDays(3)->toDateString());

        // Pegante: lote A vence en 5 días (DISPARA alerta ≤10d), lote B en 45 días
        $pPegante = $products['PGT-CTT-250'];
        $makeBatch($pPegante, $branchPrincipal, 10, $today->copy()->addDays(5)->toDateString());
        $makeBatch($pPegante, $branchPrincipal, 4, $today->copy()->addDays(45)->toDateString());
        $makeBatch($pPegante, $branchNorte, 8, $today->copy()->addDays(5)->toDateString());

        // Pintura: lote A vence en 12 días (DISPARA alerta ≤15d), lote B en 90 días
        $pPintura = $products['PNT-VNL-BLC-GL'];
        $makeBatch($pPintura, $branchPrincipal, 14, $today->copy()->addDays(12)->toDateString());
        $makeBatch($pPintura, $branchPrincipal, 6, $today->copy()->addDays(90)->toDateString());
        $makeBatch($pPintura, $branchNorte, 10, $today->copy()->addDays(12)->toDateString());

        // Thinner: lote A vence en 45 días (no dispara alerta ≤7d), lote B en 120 días
        $pThinner = $products['DSL-THN-1L'];
        $makeBatch($pThinner, $branchPrincipal, 20, $today->copy()->addDays(45)->toDateString());
        $makeBatch($pThinner, $branchPrincipal, 15, $today->copy()->addDays(120)->toDateString());
        $makeBatch($pThinner, $branchNorte, 15, $today->copy()->addDays(45)->toDateString());

        // ─────────────────────────────────────────────
        // 5. VENTAS HISTÓRICAS — no lineales
        //
        //    Objetivo: que InventoryAnalysisService calcule
        //    avg_daily_demand > 0 en los últimos 30 días
        //    y dispare recommended_purchase para los SKU de alta rotación.
        //
        //    Patrón: venta alta en semanas 3-4, baja en semana 1-2
        //    (simula pico de fin de mes típico en Colombia).
        // ─────────────────────────────────────────────

        /**
         * Ventas ficticias: cada entrada = [daysAgo, [sku => qty]]
         * daysAgo dentro de los últimos 30 días para que el análisis los capture.
         */
        $ventasDefs = [
            // Semana 4 (hace 1-7 días) — pico
            [1, ['PNT-GAL-2' => 12, 'BSG-ACR-3' => 8, 'MCT-CNT-10' => 5]],
            [2, ['TRN-CHP-1/2' => 15, 'LJA-AGU-120' => 30, 'CNT-ENM-1' => 6]],
            [3, ['PNT-GAL-2' => 10, 'ALM-AMR-18' => 8, 'BSG-ACR-3' => 6]],
            [4, ['SLL-SIL-280' => 4, 'PGT-CTT-250' => 5, 'PNT-VNL-BLC-GL' => 3]],
            [5, ['PNT-GAL-2' => 14, 'MCT-CNT-10' => 7, 'CDL-ARC-40' => 3]],
            [6, ['TRN-CHP-1/2' => 10, 'BSG-ACR-3' => 9, 'LJA-AGU-120' => 20]],
            [7, ['CNT-ENM-1' => 5, 'SLL-SIL-280' => 3, 'DSL-THN-1L' => 4]],

            // Semana 3 (hace 8-14 días) — demanda moderada-alta
            [8,  ['PNT-GAL-2' => 8, 'ALM-AMR-18' => 5, 'MCT-CNT-10' => 4]],
            [9,  ['TRN-CHP-1/2' => 12, 'BSG-ACR-3' => 7, 'PNT-VNL-BLC-GL' => 4]],
            [10, ['PNT-GAL-2' => 9, 'LJA-AGU-120' => 25, 'CNT-ENM-1' => 4]],
            [11, ['SLL-SIL-280' => 5, 'PGT-CTT-250' => 4, 'CDL-ARC-40' => 2]],
            [12, ['PNT-GAL-2' => 7, 'MCT-CNT-10' => 6, 'ALM-AMR-18' => 7]],
            [13, ['TRN-CHP-1/2' => 8, 'BSG-ACR-3' => 5, 'DSL-THN-1L' => 3]],
            [14, ['CNT-ENM-1' => 3, 'LJA-AGU-120' => 18, 'PNT-VNL-BLC-GL' => 2]],

            // Semana 2 (hace 15-21 días) — demanda baja
            [15, ['PNT-GAL-2' => 4, 'BSG-ACR-3' => 3, 'CDL-ARC-40' => 1]],
            [16, ['TRN-CHP-1/2' => 5, 'ALM-AMR-18' => 4, 'MCT-CNT-10' => 2]],
            [17, ['PNT-GAL-2' => 3, 'LJA-AGU-120' => 10]],
            [18, ['SLL-SIL-280' => 2, 'PGT-CTT-250' => 2]],
            [19, ['CNT-ENM-1' => 2, 'BSG-ACR-3' => 2]],
            [20, ['PNT-GAL-2' => 5, 'MCT-CNT-10' => 3, 'DSL-THN-1L' => 2]],
            [21, ['TRN-CHP-1/2' => 4, 'ALM-AMR-18' => 3]],

            // Semana 1 (hace 22-30 días) — demanda mínima
            [22, ['PNT-GAL-2' => 2, 'LJA-AGU-120' => 8]],
            [23, ['BSG-ACR-3' => 2, 'PNT-VNL-BLC-GL' => 1]],
            [24, ['TRN-CHP-1/2' => 3, 'CDL-ARC-40' => 1]],
            [25, ['PNT-GAL-2' => 2, 'MCT-CNT-10' => 1]],
            [26, ['ALM-AMR-18' => 2, 'CNT-ENM-1' => 1]],
            [27, ['PNT-GAL-2' => 1, 'SLL-SIL-280' => 1]],
            [28, ['TRN-CHP-1/2' => 2, 'DSL-THN-1L' => 1]],
            [29, ['BSG-ACR-3' => 1, 'LJA-AGU-120' => 5]],
            [30, ['PNT-GAL-2' => 1, 'MCT-CNT-10' => 1]],
        ];

        foreach ($ventasDefs as [$daysAgo, $items]) {
            $saleDate = now()->subDays($daysAgo);
            $sale = Sale::create([
                'business_id'  => $business->id,
                'branch_id'    => ($daysAgo % 3 === 0) ? $branchNorte->id : $branchPrincipal->id,
                'total_amount' => 0,
            ]);
            $sale->created_at = $saleDate;
            $sale->updated_at = $saleDate;
            $sale->saveQuietly();

            $total = 0;

            foreach ($items as $sku => $qty) {
                if (!isset($products[$sku])) continue;
                $product = $products[$sku];
                $unitPrice = $product->sale_price;

                SaleItem::create([
                    'sale_id'    => $sale->id,
                    'product_id' => $product->id,
                    'quantity'   => $qty,
                    'unit_price' => $unitPrice,
                ]);

                $total += $qty * $unitPrice;

                // InventoryLog histórico
                InventoryLog::create([
                    'business_id'    => $business->id,
                    'product_id'     => $product->id,
                    'type'           => 'sale',
                    'quantity_change' => -$qty,
                    'quantity_after'  => max(0, (int) $product->current_stock),
                    'reference_type'  => Sale::class,
                    'reference_id'    => $sale->id,
                    'created_at'      => $saleDate,
                    'updated_at'      => $saleDate,
                ]);
            }

            $sale->update(['total_amount' => round($total, 2)]);
        }
    }
}
