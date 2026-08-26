<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_items', 'cost_price')) {
                $table->decimal('cost_price', 10, 2)->nullable()->after('unit_type');
            }

            if (!Schema::hasColumn('sale_items', 'total_cost')) {
                $table->decimal('total_cost', 10, 2)->nullable()->after('cost_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_items', 'total_cost')) {
                $table->dropColumn('total_cost');
            }

            if (Schema::hasColumn('sale_items', 'cost_price')) {
                $table->dropColumn('cost_price');
            }
        });
    }
};
