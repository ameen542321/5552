<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_purchase_order_items', function (Blueprint $table) {
            $table->unsignedInteger('items_per_unit')->nullable()->after('unit_type');
            $table->decimal('roll_length', 12, 2)->nullable()->after('items_per_unit');
        });
    }

    public function down(): void
    {
        Schema::table('store_purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['items_per_unit', 'roll_length']);
        });
    }
};
