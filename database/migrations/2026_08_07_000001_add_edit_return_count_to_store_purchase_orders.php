<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_purchase_orders') && ! Schema::hasColumn('store_purchase_orders', 'edit_return_count')) {
            Schema::table('store_purchase_orders', function (Blueprint $table): void {
                $table->unsignedTinyInteger('edit_return_count')->default(0)->after('inventory_review_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('store_purchase_orders') && Schema::hasColumn('store_purchase_orders', 'edit_return_count')) {
            Schema::table('store_purchase_orders', function (Blueprint $table): void {
                $table->dropColumn('edit_return_count');
            });
        }
    }
};
