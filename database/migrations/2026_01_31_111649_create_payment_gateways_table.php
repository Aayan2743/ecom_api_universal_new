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
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();

            // Razorpay
            $table->string('razorpay_key')->nullable();
            $table->string('razorpay_secret')->nullable();
            $table->tinyInteger('razorpay_enabled')->default(0);

            // Cashfree
            $table->string('cashfree_app_id')->nullable();
            $table->string('cashfree_secret')->nullable();
            $table->tinyInteger('cashfree_enabled')->default(0);

            // PhonePe
            $table->string('phonepe_merchant_id')->nullable();
            $table->string('phonepe_secret')->nullable();
            $table->tinyInteger('phonepe_enabled')->default(0);

            // COD
            $table->tinyInteger('cod_enabled')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
