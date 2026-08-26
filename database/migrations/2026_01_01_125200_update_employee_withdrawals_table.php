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
    Schema::table('employee_withdrawals', function (Blueprint $table) {

        if (!Schema::hasColumn('employee_withdrawals', 'store_id')) {
            $table->unsignedBigInteger('store_id')->after('id');
        }

        if (!Schema::hasColumn('employee_withdrawals', 'deleted_at')) {
            $table->softDeletes();
        }
    });
}

public function down()
{
    Schema::table('employee_withdrawals', function (Blueprint $table) {
        $table->dropColumn(['store_id', 'deleted_at']);
    });
}

};
