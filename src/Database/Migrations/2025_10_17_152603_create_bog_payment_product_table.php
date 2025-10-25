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
        Schema::create('bog_payment_product', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bog_payment_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();

            // Foreign keys
            $table->foreign('bog_payment_id')
                ->references('id')
                ->on('bog_payments')
                ->onDelete('cascade');

                // $table->foreign('product_id')
                //     ->references('id')
                //     ->on('products')
                //     ->onDelete('cascade');

            // Indexes
            $table->index(['bog_payment_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bog_payment_product');
    }
};
