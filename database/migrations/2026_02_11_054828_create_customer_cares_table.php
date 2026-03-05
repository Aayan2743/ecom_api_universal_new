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
        Schema::create('customer_cares', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('CUSTOMER CARE');
            $table->string('time');         // 10 AM – 7 PM
            $table->string('working_days'); // Mon – Sat
            $table->string('whatsapp_number', 20);
            $table->string('email');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_cares');
    }
};
