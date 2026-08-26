<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {

            // slug لروابط نظيفة
            $table->string('slug')->unique()->after('name');

            // وصف القسم
            $table->text('description')->nullable()->after('slug');

            // حالة القسم
            $table->enum('status', ['active', 'inactive'])
                  ->default('active')
                  ->after('description');

            // السوفت ديليت
            $table->softDeletes()->after('status'); // يضيف عمود deleted_at
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['slug', 'description', 'status']);
            $table->dropSoftDeletes();
        });
    }
};
