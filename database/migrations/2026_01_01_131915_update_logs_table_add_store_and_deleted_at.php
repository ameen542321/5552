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
    Schema::table('logs', function (Blueprint $table) {

        // إضافة store_id إذا لم يكن موجودًا
        if (!Schema::hasColumn('logs', 'store_id')) {
            $table->unsignedBigInteger('store_id')->after('id');
        }

        // إضافة deleted_at إذا لم يكن موجودًا
        if (!Schema::hasColumn('logs', 'deleted_at')) {
            $table->softDeletes();
        }
    });
}

public function down()
{
    Schema::table('logs', function (Blueprint $table) {
        $table->dropColumn(['store_id', 'deleted_at']);
    });
}

};
