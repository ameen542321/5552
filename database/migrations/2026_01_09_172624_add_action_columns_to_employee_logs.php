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
    Schema::table('employee_logs', function (Blueprint $table) {
        $table->string('action_name')->nullable()->after('store_id');
        $table->decimal('amount', 10, 2)->nullable()->after('action_name');
        $table->json('meta')->nullable()->after('amount');
    });
}

public function down()
{
    Schema::table('employee_logs', function (Blueprint $table) {
        $table->dropColumn(['action_name', 'amount', 'meta']);
    });
}

};
