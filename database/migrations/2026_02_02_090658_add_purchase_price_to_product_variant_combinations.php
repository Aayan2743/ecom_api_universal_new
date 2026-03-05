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
        Schema::table('product_variant_combinations', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)
                ->default(0)
                ->after('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variant_combinations', function (Blueprint $table) {
            //
        });
    }
};
