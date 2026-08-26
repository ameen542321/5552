<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_debts', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_debts', 'debt_parent_id')) {
                // يربط سجل التحصيل (المبلغ السالب) بالمديونية الأصلية (المبلغ الموجب).
                // أبقيناه nullable حتى لا تتأثر السجلات القديمة ولا تحتاج المديونيات الأصلية إلى قيمة parent.
                $table->unsignedBigInteger('debt_parent_id')->nullable()->after('person_id');
                // الفهرس يسرّع جلب كل التحصيلات التابعة لمديونية واحدة ويجعل تقارير المديونية أقل تكلفة.
                $table->index('debt_parent_id', 'employee_debts_parent_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_debts', function (Blueprint $table) {
            if (Schema::hasColumn('employee_debts', 'debt_parent_id')) {
                $table->dropIndex('employee_debts_parent_id_index');
                $table->dropColumn('debt_parent_id');
            }
        });
    }
};
