<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up()
{
    // إضافة mixed إلى قيم ENUM مع بقاء internal_use موجوداً
    if (DB::getDriverName() === 'sqlite') {
        Schema::table('sales', fn (Blueprint $table) => $table->enum('sale_type', ['cash', 'card', 'credit', 'internal_use', 'mixed'])->default('cash')->change());
    } else {
        DB::statement("ALTER TABLE sales MODIFY sale_type ENUM('cash', 'card', 'credit', 'internal_use', 'mixed') DEFAULT 'cash'");
    }
}

public function down()
{
    // العودة للقيم الأصلية الأربعة
    if (DB::getDriverName() === 'sqlite') {
        Schema::table('sales', fn (Blueprint $table) => $table->enum('sale_type', ['cash', 'card', 'credit', 'internal_use'])->default('cash')->change());
    } else {
        DB::statement("ALTER TABLE sales MODIFY sale_type ENUM('cash', 'card', 'credit', 'internal_use') DEFAULT 'cash'");
    }
}
};
