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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shiprocket_order_id')->nullable()->after('status');
            $table->string('awb_code')->nullable()->after('shiprocket_order_id');
            $table->string('courier_name')->nullable()->after('awb_code');
            $table->enum('shipment_status', [
                'pending',
                'created',
                'pickup_scheduled',
                'shipped',
                'delivered',
                'cancelled',
            ])->default('pending')->after('courier_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
};
