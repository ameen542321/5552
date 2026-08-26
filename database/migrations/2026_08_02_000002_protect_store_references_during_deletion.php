<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->makeNullable('logs', 'store_id');

        $this->nullOrphanedReference('users', 'current_store_id', 'stores');
        $this->deleteOrphanedReferences('logs', 'store_id', 'stores');

        // قد تكون الهجرة السابقة فشلت بعد تطبيق بعض أوامر MySQL؛ لذلك كل قيد يضاف بصورة idempotent.
        $this->ensureForeignKey('users', 'current_store_id', 'stores', 'id', 'null');
        $this->ensureForeignKey('logs', 'store_id', 'stores', 'id', 'cascade');
        $this->ensureForeignKey('accountants', 'store_id', 'stores', 'id', 'cascade');

        foreach ([
            'inventory_logs',
            'purchases',
            'store_settings',
            'employee_logs',
            'employee_absences',
            'employee_withdrawals',
            'debts',
            'employee_credit_collections',
            'employee_salary_reports',
        ] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'store_id')) {
                continue;
            }

            // لا حاجة للاحتفاظ بصف مرتبط بمتجر غير موجود قبل إضافة قيد الحماية.
            DB::table($table)
                ->whereNotExists(function ($query) use ($table): void {
                    $query->selectRaw('1')
                        ->from('stores')
                        ->whereColumn('stores.id', $table.'.store_id');
                })
                ->delete();

            $this->ensureForeignKey($table, 'store_id', 'stores', 'id', 'cascade');
        }
    }

    public function down(): void
    {
        foreach ([
            'inventory_logs',
            'purchases',
            'store_settings',
            'employee_logs',
            'employee_absences',
            'employee_withdrawals',
            'debts',
            'employee_credit_collections',
        ] as $table) {
            $this->dropForeignKeyIfExists($table, 'store_id');
        }

        $this->dropForeignKeyIfExists('accountants', 'store_id');
        if (Schema::hasTable('accountants') && Schema::hasColumn('accountants', 'store_id')) {
            Schema::table('accountants', function (Blueprint $table): void {
                $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
            });
        }

        $this->dropForeignKeyIfExists('logs', 'store_id');
        $this->dropForeignKeyIfExists('users', 'current_store_id');
    }

    private function ensureForeignKey(
        string $tableName,
        string $column,
        string $foreignTable,
        string $foreignColumn,
        string $onDelete
    ): void {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $column)) {
            return;
        }

        $existing = $this->foreignKeyFor($tableName, $column);
        if ($existing && strtolower((string) ($existing['on_delete'] ?? '')) === $onDelete) {
            return;
        }

        if ($existing) {
            $this->dropForeignKey($tableName, $existing);
        }

        Schema::table($tableName, function (Blueprint $table) use ($column, $foreignTable, $foreignColumn, $onDelete): void {
            $foreign = $table->foreign($column)->references($foreignColumn)->on($foreignTable);

            if ($onDelete === 'cascade') {
                $foreign->cascadeOnDelete();
            } else {
                $foreign->nullOnDelete();
            }
        });
    }

    private function dropForeignKeyIfExists(string $tableName, string $column): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $foreign = $this->foreignKeyFor($tableName, $column);
        if ($foreign) {
            $this->dropForeignKey($tableName, $foreign);
        }
    }

    private function dropForeignKey(string $tableName, array $foreign): void
    {
        $name = $foreign['name'] ?? null;
        $columns = $foreign['columns'] ?? [];
        if (! $name || $columns === []) {
            return;
        }

        Schema::table($tableName, static function (Blueprint $table) use ($name, $columns): void {
            // SQLite يعيد بناء الجدول عند حذف القيد ويحتاج أسماء الأعمدة،
            // بينما MySQL يتعامل مباشرة مع اسم القيد.
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? $columns : $name);
        });
    }

    private function foreignKeyFor(string $tableName, string $column): ?array
    {
        return collect(Schema::getForeignKeys($tableName))
            ->first(static fn (array $foreign): bool => in_array($column, $foreign['columns'] ?? [], true));
    }

    private function makeNullable(string $tableName, string $column): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $column)) {
            return;
        }

        $columnDefinition = collect(Schema::getColumns($tableName))
            ->firstWhere('name', $column);

        if ((bool) ($columnDefinition['nullable'] ?? false)) {
            return;
        }

        Schema::table($tableName, static function (Blueprint $table) use ($column): void {
            $table->unsignedBigInteger($column)->nullable()->change();
        });
    }

    private function nullOrphanedReference(string $table, string $column, string $foreignTable): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->whereNotNull($column)
            ->whereNotExists(function ($query) use ($table, $column, $foreignTable): void {
                $query->selectRaw('1')
                    ->from($foreignTable)
                    ->whereColumn($foreignTable.'.id', $table.'.'.$column);
            })
            ->update([$column => null]);
    }

    private function deleteOrphanedReferences(string $table, string $column, string $foreignTable): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->whereNotNull($column)
            ->whereNotExists(function ($query) use ($table, $column, $foreignTable): void {
                $query->selectRaw('1')
                    ->from($foreignTable)
                    ->whereColumn($foreignTable.'.id', $table.'.'.$column);
            })
            ->delete();
    }
};
