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
        Schema::create('product_variant_combination_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variant_combination_id');
            $table->unsignedBigInteger('variation_value_id');

            // Optional but HIGHLY recommended
            // Composite primary key to avoid duplicates
            // $table->primary(['variant_combination_id', 'variation_value_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_combination_values');
    }
};