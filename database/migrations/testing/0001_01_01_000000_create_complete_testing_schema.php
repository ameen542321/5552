<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('هيكل الاختبارات الموحد مخصص لاتصال SQLite فقط.');
        }

        $schemaPath = database_path('testing/sqlite-schema.sql');
        $schema = file_get_contents($schemaPath);

        if ($schema === false) {
            throw new RuntimeException("تعذر قراءة هيكل SQLite الاختباري: {$schemaPath}");
        }

        DB::connection()->getPdo()->exec($schema);
    }

    public function down(): void
    {
        // migrate:fresh يحذف جميع جداول SQLite قبل إعادة تحميل الهيكل الموحد.
    }
};
