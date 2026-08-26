<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. إضافة حقل الملاحظات الداخلية
        Schema::table('sales', function (Blueprint $table) {
            $table->text('internal_notes')->nullable()->after('description');
        });

        // 2. تعديل sale_type ليشمل 'internal_use'
        // Note: هذا التعديل يحتاج إلى إعادة إنشاء ENUM في MySQL
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('sales', fn (Blueprint $table) => $table->enum('sale_type', ['cash', 'card', 'credit', 'internal_use'])->default('cash')->change());
        } else {
            DB::statement("ALTER TABLE sales MODIFY COLUMN sale_type ENUM('cash','card','credit','internal_use') NOT NULL DEFAULT 'cash'");
        }
    }

    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('internal_notes');
        });

        // الرجوع للقيم القديمة
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('sales', fn (Blueprint $table) => $table->enum('sale_type', ['cash', 'card', 'credit'])->default('cash')->change());
        } else {
            DB::statement("ALTER TABLE sales MODIFY COLUMN sale_type ENUM('cash','card','credit') NOT NULL DEFAULT 'cash'");
        }
    }
};
