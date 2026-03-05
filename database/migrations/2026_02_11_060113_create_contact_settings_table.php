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
        Schema::create('contact_settings', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('Contact Hamsini Silks');
            $table->text('description')->nullable();
            $table->string('address');
            $table->string('phone_1', 20);
            $table->string('phone_2', 20)->nullable();
            $table->string('working_days');
            $table->string('working_hours');
            $table->string('weekend_note')->nullable();
            $table->text('google_map_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_settings');
    }
};
