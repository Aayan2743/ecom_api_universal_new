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
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('dead_weight', 10, 2)->nullable()->after('id');
            $table->decimal('volumetric_weight', 10, 2)->nullable()->after('dead_weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
             $table->dropColumn(['dead_weight','volumetric_weight']);
        });
    }
};
