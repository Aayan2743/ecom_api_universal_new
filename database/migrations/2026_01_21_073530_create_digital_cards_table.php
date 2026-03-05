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
        Schema::create('digital_cards', function (Blueprint $table) {
           $table->id();

    $table->foreignId('organization_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->foreignId('subscription_id')
          ->constrained('organization_subscriptions');

    $table->string('card_number')->unique(); // optional
    $table->json('card_data'); // name, phone, email, links, etc.

    $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('digital_cards');
    }
};