<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('notifications', function (Blueprint $table) {
        $table->enum('target_type', [
            'all',
            'users',
            'accountants',
            'stores',
            'store',
            'user',
            'mixed'
        ])->change();
    });
}

public function down()
{
    Schema::table('notifications', function (Blueprint $table) {
        $table->enum('target_type', [
            'all',
            'users',
            'stores',
            'mixed',
            'user',
            'store'
        ])->change();
    });
}

};
