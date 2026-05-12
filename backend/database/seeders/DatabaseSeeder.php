<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::create([
            'name' => 'Demo Hardware Store',
            'address' => '123 Main St',
            'phone' => '+1 555 0100',
        ]);

        $user = User::create([
            'business_id' => $business->id,
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => Hash::make('password'),
        ]);

        $business->update(['owner_user_id' => $user->id]);

        Subscription::create([
            'business_id' => $business->id,
            'plan' => 'pro',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $products = [
            Product::create([
                'business_id' => $business->id,
                'name' => 'Steel Nails 2"',
                'sku' => 'NAIL-2',
                'cost_price' => 2.50,
                'sale_price' => 4.99,
                'current_stock' => 120,
                'minimum_stock' => 30,
                'supplier_lead_time_days' => 5,
            ]),
            Product::create([
                'business_id' => $business->id,
                'name' => 'Wood Screws Box',
                'sku' => 'SCRW-W',
                'cost_price' => 5.00,
                'sale_price' => 9.99,
                'current_stock' => 45,
                'minimum_stock' => 20,
                'supplier_lead_time_days' => 7,
            ]),
            Product::create([
                'business_id' => $business->id,
                'name' => 'Paint Brush Set',
                'sku' => 'BRUSH-1',
                'cost_price' => 8.00,
                'sale_price' => 14.99,
                'current_stock' => 15,
                'minimum_stock' => 10,
                'supplier_lead_time_days' => 3,
            ]),
        ];

        foreach ([
            [1 => 10, 2 => 2],
            [1 => 5, 3 => 1],
            [2 => 3],
        ] as $items) {
            $sale = Sale::create([
                'business_id' => $business->id,
                'total_amount' => 0,
            ]);
            $total = 0;
            foreach ($items as $productIndex => $qty) {
                $product = $products[$productIndex - 1];
                $unitPrice = $product->sale_price;
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                ]);
                $product->decrement('current_stock', $qty);
                $total += $qty * $unitPrice;
                InventoryLog::create([
                    'business_id' => $business->id,
                    'product_id' => $product->id,
                    'type' => 'sale',
                    'quantity_change' => -$qty,
                    'quantity_after' => $product->fresh()->current_stock,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                ]);
            }
            $sale->update(['total_amount' => round($total, 2)]);
        }
    }
}
