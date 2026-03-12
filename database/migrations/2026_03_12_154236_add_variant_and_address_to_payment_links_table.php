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
        Schema::table('payment_links', function (Blueprint $table) {
              $table->unsignedBigInteger('variant_id')->nullable()->after('customer_phone');
            $table->unsignedBigInteger('address_id')->nullable()->after('variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_links', function (Blueprint $table) {
              $table->dropColumn(['variant_id','address_id']);
        });
    }
};
