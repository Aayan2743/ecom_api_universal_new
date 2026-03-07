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
        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();

            $table->string('razorpay_link_id')->unique(); // plink_xxx
            $table->string('payment_link');               // short_url

            $table->decimal('amount', 10, 2);

            $table->string('customer_name');
            $table->string('customer_phone', 15);

            $table->enum('status', ['pending', 'paid', 'cancelled', 'expired'])
                ->default('pending');

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_links');
    }
};
