<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedInteger('amount_in_cents')->nullable()->after('amount');
            $table->string('status', 32)->default('pending')->after('currency');
            $table->unsignedBigInteger('business_id')->nullable()->change();
            $table->string('plan', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('business_id')->nullable(false)->change();
            $table->string('plan', 32)->nullable(false)->change();
            $table->dropColumn(['amount_in_cents', 'status']);
        });
    }
};
