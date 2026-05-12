<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('plan', 32);
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('COP');
            $table->string('reference', 255)->unique();
            $table->string('wompi_transaction_id', 255)->nullable();
            $table->string('wompi_status', 64)->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->index('business_id');
            $table->index('reference');
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
