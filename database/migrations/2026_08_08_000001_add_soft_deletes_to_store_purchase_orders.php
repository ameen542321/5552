<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_purchase_orders') && ! Schema::hasColumn('store_purchase_orders', 'deleted_at')) {
            Schema::table('store_purchase_orders', fn (Blueprint $table) => $table->softDeletes());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('store_purchase_orders') && Schema::hasColumn('store_purchase_orders', 'deleted_at')) {
            Schema::table('store_purchase_orders', fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};
