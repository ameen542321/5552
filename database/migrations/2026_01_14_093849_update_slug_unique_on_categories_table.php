<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            // إزالة الـ UNIQUE القديم على slug فقط
            $table->dropUnique('categories_slug_unique');

            // إضافة UNIQUE مركّب بين store_id و slug
            $table->unique(['store_id', 'slug'], 'categories_store_slug_unique');
        });
    }

    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            // إزالة الـ UNIQUE المركّب
            $table->dropUnique('categories_store_slug_unique');

            // إعادة UNIQUE القديم
            $table->unique('slug', 'categories_slug_unique');
        });
    }
};
