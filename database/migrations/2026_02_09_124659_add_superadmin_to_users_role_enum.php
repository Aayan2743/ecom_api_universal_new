<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        DB::statement("
            ALTER TABLE users
            MODIFY role ENUM(
                'superadmin',
                'admin',
                'user',
                'employee',
                'employeer'
            ) NOT NULL DEFAULT 'user'
        ");
    }

    public function down()
    {
        DB::statement("
            ALTER TABLE users
            MODIFY role ENUM(
                'admin',
                'user',
                'employee',
                'employeer'
            ) NOT NULL DEFAULT 'user'
        ");
    }
};
