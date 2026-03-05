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
        Schema::create('organization_subscriptions', function (Blueprint $table) {
           $table->id();

             $table->foreignId('organization_id')
          ->constrained()
          ->cascadeOnDelete();

            $table->integer('total_cards');      // e.g. 10
            $table->integer('used_cards')->default(0);
            $table->decimal('price', 10, 2);

            $table->enum('status', ['active', 'completed'])->default('active');

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_subscriptions');
    }
};