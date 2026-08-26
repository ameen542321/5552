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
    Schema::table('employee_withdrawals', function (Blueprint $table) {
        // 1) إعادة تسمية employee_id إلى person_id
        $table->renameColumn('employee_id', 'person_id');

        // 2) إضافة person_type بعد person_id
        $table->string('person_type')->nullable()->after('person_id');
    });

    // 3) تعبية البيانات القديمة: كل السجلات الحالية تعتبر لموظفين
    DB::table('employee_withdrawals')
        ->whereNull('person_type')
        ->update(['person_type' => \App\Models\Employee::class]);
}

public function down()
{
    Schema::table('employee_withdrawals', function (Blueprint $table) {
        // رجوعًا للوضع القديم
        $table->renameColumn('person_id', 'employee_id');
        $table->dropColumn('person_type');
    });
}

};
