<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_balances', function (Blueprint $table) {
            if (! Schema::hasColumn('daily_balances', 'next_shift_business_date')) {
                $table->date('next_shift_business_date')->nullable()->after('closed_at')->index();
            }

            if (! Schema::hasColumn('daily_balances', 'next_shift_decision')) {
                $table->string('next_shift_decision', 40)->nullable()->after('next_shift_business_date');
            }

            if (! Schema::hasColumn('daily_balances', 'next_shift_decided_by')) {
                $table->foreignId('next_shift_decided_by')
                    ->nullable()
                    ->after('next_shift_decision')
                    ->constrained('accountants')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_balances', function (Blueprint $table) {
            if (Schema::hasColumn('daily_balances', 'next_shift_decided_by')) {
                $table->dropConstrainedForeignId('next_shift_decided_by');
            }

            if (Schema::hasColumn('daily_balances', 'next_shift_decision')) {
                $table->dropColumn('next_shift_decision');
            }

            if (Schema::hasColumn('daily_balances', 'next_shift_business_date')) {
                $table->dropIndex(['next_shift_business_date']);
                $table->dropColumn('next_shift_business_date');
            }
        });
    }
};
