<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            if (! Schema::hasColumn('debts', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('description');
                $table->string('payment_method_label')->nullable()->after('payment_method');
                $table->decimal('cash_amount', 10, 2)->default(0)->after('payment_method_label');
                $table->decimal('card_amount', 10, 2)->default(0)->after('cash_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            foreach (['payment_method', 'payment_method_label', 'cash_amount', 'card_amount'] as $column) {
                if (Schema::hasColumn('debts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
