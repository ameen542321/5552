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
    Schema::table('employee_absences', function (Blueprint $table) {

        // 1) إعادة تسمية employee_id → person_id
        if (Schema::hasColumn('employee_absences', 'employee_id')) {
            $table->renameColumn('employee_id', 'person_id');
        }

        // 2) إضافة person_type
        if (!Schema::hasColumn('employee_absences', 'person_type')) {
            $table->string('person_type')->nullable()->after('person_id');
        }
    });

    // 3) تحديث البيانات القديمة
    DB::table('employee_absences')
        ->whereNull('person_type')
        ->update(['person_type' => \App\Models\Employee::class]);
}

public function down()
{
    Schema::table('employee_absences', function (Blueprint $table) {
        if (Schema::hasColumn('employee_absences', 'person_id')) {
            $table->renameColumn('person_id', 'employee_id');
        }

        if (Schema::hasColumn('employee_absences', 'person_type')) {
            $table->dropColumn('person_type');
        }
    });
}

};
