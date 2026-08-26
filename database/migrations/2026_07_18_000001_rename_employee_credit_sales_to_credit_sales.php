<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_credit_sales') && ! Schema::hasTable('credit_sales')) {
            Schema::rename('employee_credit_sales', 'credit_sales');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('credit_sales') && ! Schema::hasTable('employee_credit_sales')) {
            Schema::rename('credit_sales', 'employee_credit_sales');
        }
    }
};
