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
    Schema::table('invoices', function (Blueprint $table) {
        // إضافة عمود الوصف ويكون nullable (يسمح بالقيمة null)
        // نضعه بعد عمود الملاحظات (notes) للتنظيم
        $table->text('description')->nullable()->after('notes');
    });
}

public function down()
{
    Schema::table('invoices', function (Blueprint $table) {
        $table->dropColumn('description');
    });
}
};
