<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // إضافة حقل للإشارة إلى وجود آجل جزئي
            $table->boolean('has_partial_credit')
                  ->default(false)
                  ->after('sale_type')
                  ->comment('هل تحتوي الفاتورة على آجل جزئي مع كاش أو شبكة');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('has_partial_credit');
        });
    }
};
