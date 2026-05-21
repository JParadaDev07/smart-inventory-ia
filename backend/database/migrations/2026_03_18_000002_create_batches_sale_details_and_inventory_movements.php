<?php

use App\Models\Branch;
use App\Models\Batch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend product configuration for perishables (guard against already applied migrations).
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'perecedero')) {
                $table->boolean('perecedero')->default(false)->after('supplier_lead_time_days');
            }
            if (!Schema::hasColumn('products', 'expired_mode')) {
                $table->string('expired_mode')->default('bloquear')->after('perecedero'); // bloquear|alerta|permitir
            }
            if (!Schema::hasColumn('products', 'expiration_alert_days')) {
                $table->unsignedInteger('expiration_alert_days')->default(7)->after('expired_mode');
            }
        });

        if (!Schema::hasTable('batches')) {
            Schema::create('batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('branch_id');

                $table->unsignedInteger('quantity_received')->default(0);
                $table->unsignedInteger('quantity_available')->default(0);

                $table->date('expiration_date')->nullable();
                $table->decimal('cost_unit', 12, 2)->default(0);
                $table->timestamp('received_at')->useCurrent();

                $table->timestamps();

                $table->index(['business_id', 'branch_id', 'product_id']);
                // FEFO/FIFO: non-null expiration first, then expiration asc, then received_at asc.
                $table->index(['business_id', 'branch_id', 'product_id', 'expiration_date', 'received_at'], 'batches_fefo_idx');

                $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
                $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('branch_id');
                $table->unsignedBigInteger('batch_id')->nullable();

                $table->string('type'); // entrada|salida|merma
                $table->unsignedInteger('quantity'); // positive
                $table->string('reason')->nullable();
                $table->boolean('is_expired')->default(false);

                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->timestamp('occurred_at')->useCurrent();

                $table->timestamps();

                $table->index(['business_id', 'branch_id', 'product_id', 'occurred_at'], 'inv_mov_biz_branch_prod_occurred_idx');
                $table->index(['business_id', 'batch_id']);

                $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
                $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
                $table->foreign('batch_id')->references('id')->on('batches')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('sale_details')) {
            Schema::create('sale_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id');
                $table->unsignedBigInteger('sale_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('batch_id');

                $table->unsignedInteger('quantity');
                $table->decimal('unit_price', 12, 2);
                $table->decimal('unit_cost', 12, 2)->default(0);

                $table->date('expiration_date')->nullable();
                $table->boolean('is_expired')->default(false); // expiration_date == hoy (advertencia) or earlier depending on mode

                $table->timestamps();

                $table->index(['business_id', 'sale_id']);
                $table->index(['business_id', 'product_id', 'batch_id']);

                $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
                $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
                $table->foreign('batch_id')->references('id')->on('batches')->cascadeOnDelete();
            });
        }

        // Extend sales with flags for expired items (perishable).
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'has_expired_items')) {
                $table->boolean('has_expired_items')->default(false)->after('total_amount');
            }
            if (!Schema::hasColumn('sales', 'expired_quantity_total')) {
                $table->unsignedInteger('expired_quantity_total')->default(0)->after('has_expired_items');
            }
        });

        // Seed batches for existing branch stocks (legacy data).
        // For now, new perishables defaults to perecedero=false, so expiration_date can be NULL safely.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        if (!Schema::hasTable('batches')) {
            return;
        }

        $rows = DB::table('product_branch_stocks')
            ->select(['business_id', 'product_id', 'branch_id', 'current_stock'])
            ->where('current_stock', '>', 0)
            ->get();

        if ($rows->isNotEmpty() && DB::table('batches')->count() === 0) {
            $today = now();
            foreach ($rows as $r) {
                $cost = DB::table('products')->where('id', $r->product_id)->value('cost_price');

                DB::table('batches')->insert([
                    'business_id' => $r->business_id,
                    'product_id' => $r->product_id,
                    'branch_id' => $r->branch_id,
                    'quantity_received' => (int) $r->current_stock,
                    'quantity_available' => (int) $r->current_stock,
                    'expiration_date' => null,
                    'cost_unit' => $cost ?? 0,
                    'received_at' => $today,
                    'created_at' => $today,
                    'updated_at' => $today,
                ]);
            }
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'has_expired_items')) {
                $table->dropColumn(['has_expired_items']);
            }
            if (Schema::hasColumn('sales', 'expired_quantity_total')) {
                $table->dropColumn(['expired_quantity_total']);
            }
        });

        Schema::dropIfExists('sale_details');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('batches');

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'perecedero')) {
                $table->dropColumn(['perecedero']);
            }
            if (Schema::hasColumn('products', 'expired_mode')) {
                $table->dropColumn(['expired_mode']);
            }
            if (Schema::hasColumn('products', 'expiration_alert_days')) {
                $table->dropColumn(['expiration_alert_days']);
            }
        });
    }
};

