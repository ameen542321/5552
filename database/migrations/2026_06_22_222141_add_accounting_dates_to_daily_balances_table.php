<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_balances', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_balances', 'business_date')) {
                $table->date('business_date')->nullable()->after('end_time')->index();
            }

            if (!Schema::hasColumn('daily_balances', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('business_date')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_balances', function (Blueprint $table) {
            if (Schema::hasColumn('daily_balances', 'closed_at')) {
                $table->dropIndex(['closed_at']);
                $table->dropColumn('closed_at');
            }

            if (Schema::hasColumn('daily_balances', 'business_date')) {
                $table->dropIndex(['business_date']);
                $table->dropColumn('business_date');
            }
        });
    }
};
