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
    Schema::table('sale_items', function (Blueprint $table) {
        // حقل لتحديد هل البيع مخصص أم لا
        $table->boolean('is_custom')->default(false)->after('fraction_id');

        // حقل لتخزين اسم النوع (مثل: أربع زجاجات)
        $table->string('custom_name')->nullable()->after('is_custom');

        // حقل لتخزين نسبة الاستهلاك (مثلاً 0.20 لتمثيل 20%)
        $table->decimal('custom_consumption', 8, 2)->nullable()->after('custom_name');
    });
}

public function down()
{
    Schema::table('sale_items', function (Blueprint $table) {
        $table->dropColumn(['is_custom', 'custom_name', 'custom_consumption']);
    });
}
};
