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
        Schema::table('bog_cards', function (Blueprint $table) {
            $table->string('card_holder_name')->nullable()->after('card_type');
            $table->string('card_brand')->nullable()->after('card_holder_name');
            $table->timestamp('last_used_at')->nullable()->after('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bog_cards', function (Blueprint $table) {
            $table->dropColumn(['card_holder_name', 'card_brand', 'last_used_at']);
        });
    }
};
