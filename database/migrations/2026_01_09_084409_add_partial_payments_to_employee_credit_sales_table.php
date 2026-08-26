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
    Schema::table('employee_credit_sales', function (Blueprint $table) {
        $table->decimal('remaining_amount', 10, 2)->default(0)->after('amount');
        $table->json('partial_payments')->nullable()->after('remaining_amount');
    });
}

public function down()
{
    Schema::table('employee_credit_sales', function (Blueprint $table) {
        $table->dropColumn(['remaining_amount', 'partial_payments']);
    });
}

};
