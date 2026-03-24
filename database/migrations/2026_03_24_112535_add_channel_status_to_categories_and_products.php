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
        // 🗂 Categories table
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_active_pos')->default(1)->after('is_active');
            $table->boolean('is_active_ecom')->default(1)->after('is_active_pos');
        });

        // 🛒 Products table
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_active_pos')->default(1)->after('extra_details');
            $table->boolean('is_active_ecom')->default(1)->after('is_active_pos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['is_active_pos', 'is_active_ecom']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_active_pos', 'is_active_ecom']);
        });
    }
};
