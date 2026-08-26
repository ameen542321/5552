<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_debts') && ! Schema::hasTable('debts')) {
            Schema::rename('employee_debts', 'debts');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('debts') && ! Schema::hasTable('employee_debts')) {
            Schema::rename('debts', 'employee_debts');
        }
    }
};
