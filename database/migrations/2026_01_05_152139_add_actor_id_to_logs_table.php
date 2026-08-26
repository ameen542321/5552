<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->unsignedBigInteger('actor_id')->nullable()->after('actor_type');
        });
    }

    public function down()
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->dropColumn('actor_id');
        });
    }
};
