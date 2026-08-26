<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sales', 'expenses', 'employee_withdrawals'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'business_date')) {
                    $table->date('business_date')->nullable()->after('created_at')->index();
                }

                if (! Schema::hasColumn($tableName, 'daily_balance_id')) {
                    $table->foreignId('daily_balance_id')
                        ->nullable()
                        ->after('business_date')
                        ->constrained('daily_balances')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['employee_withdrawals', 'expenses', 'sales'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'daily_balance_id')) {
                    $table->dropConstrainedForeignId('daily_balance_id');
                }

                if (Schema::hasColumn($tableName, 'business_date')) {
                    $table->dropIndex(['business_date']);
                    $table->dropColumn('business_date');
                }
            });
        }
    }
};
