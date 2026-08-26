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
    $foreign = collect(Schema::getForeignKeys('employee_withdrawals'))
        ->first(fn (array $key): bool => in_array('employee_id', $key['columns'] ?? [], true));

    if (! $foreign) {
        return;
    }

    Schema::table('employee_withdrawals', function (Blueprint $table) {
        // تمرير العمود بدل اسم القيد متوافق مع SQLite وMySQL.
        $table->dropForeign(['employee_id']);
    });
}

public function down()
{
    Schema::table('employee_withdrawals', function (Blueprint $table) {
        // لا نعيد الـ FK لأنه غير مناسب للنظام polymorphic
    });
}

};
