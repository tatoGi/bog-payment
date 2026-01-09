<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bog_payments', function (Blueprint $table) {
            $table->id();
            $table->string('bog_order_id')->unique();
            $table->string('external_order_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GEL');
            $table->string('status')->default('pending');
            $table->string('redirect_url')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_data')->nullable();
            $table->json('callback_data')->nullable();
            $table->timestamps();

            $table->index('bog_order_id');
            $table->index('external_order_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bog_payments');
    }
};
