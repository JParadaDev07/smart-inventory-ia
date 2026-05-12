<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->timestamps();

            $table->index('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        Schema::create('product_branch_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedInteger('current_stock')->default(0);
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'branch_id']);
            $table->index(['business_id', 'branch_id']);

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('business_id');
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        // Data migration: create a default branch per business and seed its branch stock
        // from the existing shared product stock.
        $businesses = DB::table('businesses')->pluck('id');
        if ($businesses->isNotEmpty()) {
            /** @var array<int, int> $defaultBranchByBusiness */
            $defaultBranchByBusiness = [];

            foreach ($businesses as $businessId) {
                $defaultBranchByBusiness[$businessId] = DB::table('branches')->insertGetId([
                    'business_id' => $businessId,
                    'name' => 'Default',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $products = DB::table('products')->select(['id', 'business_id', 'current_stock', 'minimum_stock'])->get();
            foreach ($products as $p) {
                $branchId = $defaultBranchByBusiness[(int) $p->business_id] ?? null;
                if (!$branchId) continue;

                DB::table('product_branch_stocks')->insert([
                    'business_id' => (int) $p->business_id,
                    'product_id' => (int) $p->id,
                    'branch_id' => (int) $branchId,
                    'current_stock' => (int) $p->current_stock,
                    'minimum_stock' => (int) $p->minimum_stock,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::dropIfExists('product_branch_stocks');
        Schema::dropIfExists('branches');
    }
};

