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
    Schema::table('stores', function (Blueprint $table) {
        // عدد الورديات
        $table->integer('number_of_shifts')->default(1);

        // أوقات بداية الورديات (نستخدم time للساعة)
        $table->time('shift_1_start')->nullable();
        $table->time('shift_2_start')->nullable();
        $table->time('shift_3_start')->nullable();

        // خيار إضافي: هل الإقفال إلزامي في وقت محدد؟
        $table->boolean('force_shift_closure')->default(false);
    });
}

public function down()
{
    Schema::table('stores', function (Blueprint $table) {
        $table->dropColumn(['number_of_shifts', 'shift_1_start', 'shift_2_start', 'shift_3_start', 'force_shift_closure']);
    });
}
};
