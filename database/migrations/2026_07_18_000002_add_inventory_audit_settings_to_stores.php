<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'inventory_audit_cycle_months')) {
                $table->unsignedTinyInteger('inventory_audit_cycle_months')->default(6)->after('force_shift_closure');
            }

            if (! Schema::hasColumn('stores', 'inventory_audit_start_mode')) {
                $table->string('inventory_audit_start_mode', 20)->default('store_created_at')->after('inventory_audit_cycle_months');
            }

            if (! Schema::hasColumn('stores', 'inventory_audit_start_date')) {
                $table->date('inventory_audit_start_date')->nullable()->after('inventory_audit_start_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'inventory_audit_start_date')) {
                $table->dropColumn('inventory_audit_start_date');
            }

            if (Schema::hasColumn('stores', 'inventory_audit_start_mode')) {
                $table->dropColumn('inventory_audit_start_mode');
            }

            if (Schema::hasColumn('stores', 'inventory_audit_cycle_months')) {
                $table->dropColumn('inventory_audit_cycle_months');
            }
        });
    }
};
