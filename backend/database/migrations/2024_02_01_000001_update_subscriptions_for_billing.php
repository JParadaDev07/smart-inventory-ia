<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('status', 32)->default('trial')->after('plan');
            $table->timestamp('current_period_end')->nullable()->after('trial_ends_at');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['status', 'current_period_end']);
            $table->timestamp('ends_at')->nullable()->after('trial_ends_at');
        });
    }
};
