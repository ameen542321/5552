<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    if (DB::getDriverName() === 'sqlite') {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->enum('sender_type', ['admin', 'user', 'accountant', 'system', 'CARLED'])->change();
        });

        return;
    }

    DB::statement("
        ALTER TABLE notifications
        MODIFY COLUMN sender_type
        ENUM('admin', 'user', 'accountant', 'system', 'CARLED')
        NOT NULL
    ");
}

public function down()
{
    if (DB::getDriverName() === 'sqlite') {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->enum('sender_type', ['admin', 'user', 'accountant', 'system'])->change();
        });

        return;
    }

    DB::statement("
        ALTER TABLE notifications
        MODIFY COLUMN sender_type
         ENUM('admin', 'user', 'accountant', 'system')
        NOT NULL
    ");
}

};
