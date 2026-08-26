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
    Schema::table('products', function (Blueprint $table) {
        // إضافة فهرس عادي لتسريع عمليات البحث بـ LIKE
        $table->index('description'); 
        
        // ملاحظة: إذا كان الوصف طويلاً جداً (Text)، يفضل استخدام FULLTEXT index
        // $table->fulltext('description'); 
    });
}

public function down()
{
    Schema::table('products', function (Blueprint $table) {
        $table->dropIndex(['description']);
    });
}
};
