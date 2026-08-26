<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table): void {
            $table->date('business_date')->nullable()->after('type')->index();
        });

        Schema::table('store_purchase_orders', function (Blueprint $table): void {
            $table->date('approved_business_date')->nullable()->after('approved_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table): void {
            $table->dropIndex(['business_date']);
            $table->dropColumn('business_date');
        });

        Schema::table('store_purchase_orders', function (Blueprint $table): void {
            $table->dropIndex(['approved_business_date']);
            $table->dropColumn('approved_business_date');
        });
    }
};
