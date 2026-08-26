<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $foreign = collect(Schema::getForeignKeys('employee_logs'))
            ->first(fn (array $key): bool =>
                ($key['name'] ?? null) === 'employee_logs_employee_id_foreign'
                || collect($key['columns'] ?? [])->contains(fn (string $column): bool => in_array($column, ['employee_id', 'loggable_id'], true))
            );
        $foreignColumns = $foreign['columns'] ?? [];
        $hasLegacyIndex = collect(Schema::getIndexes('employee_logs'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === 'employee_logs_employee_id_foreign');

        // يجب تنفيذ حذف القيد قبل حذف العمود في عمليتي ALTER منفصلتين؛
        // وإلا يعيد SQLite بناء الجدول بينما تعريف القيد ما زال يشير إلى العمود المحذوف.
        if ($foreignColumns !== []) {
            Schema::table('employee_logs', function (Blueprint $table) use ($foreignColumns) {
                $table->dropForeign($foreignColumns);
            });
        }

        if ($hasLegacyIndex) {
            Schema::table('employee_logs', function (Blueprint $table) {
                $table->dropIndex('employee_logs_employee_id_foreign');
            });
        }

        Schema::table('employee_logs', function (Blueprint $table) {
            foreach ([
                'loggable_id',
                'loggable_type',
                'type',
                'amount',
                'added_by',
                'logged_at',
                'category',
            ] as $col) {
                if (Schema::hasColumn('employee_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down()
    {
        Schema::table('employee_logs', function (Blueprint $table) {

            // إعادة الأعمدة فقط لو احتجت rollback
            $table->unsignedBigInteger('loggable_id')->nullable();
            $table->string('loggable_type')->nullable();
            $table->string('type')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamp('logged_at')->nullable();
            $table->string('category')->default('operation')->nullable();
        });
    }
};
