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
    Schema::table('subscriptions', function (Blueprint $table) {
        if (!Schema::hasColumn('subscriptions', 'deleted_at')) {
            $table->softDeletes();
        }
    });
}

public function down()
{
    Schema::table('subscriptions', function (Blueprint $table) {
        $table->dropColumn('deleted_at');
    });
}

};
