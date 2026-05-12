<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('endpoint')->nullable();
            $table->boolean('success')->default(true);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('fallback_used')->nullable(); // local | null
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('business_id');
            $table->index(['business_id', 'created_at']);
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
