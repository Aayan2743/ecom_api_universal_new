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
        Schema::table('attendances', function (Blueprint $table) {

            // Update ENUM
            $table->enum('status', [
                'present',
                'absent',
                'leave',
                'ot',
                'c_off',
            ])->change();

            // Add OT amount column
            $table->decimal('ot_amount', 10, 2)
                ->nullable()
                ->after('out_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            //
        });
    }
};
