<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_transfers', function (Blueprint $table) {
            $table->date('request_business_date')->nullable()->after('status')->index();
            $table->date('action_business_date')->nullable()->after('request_business_date')->index();
        });
    }

    public function down(): void
    {
        Schema::table('store_transfers', function (Blueprint $table) {
            $table->dropIndex(['request_business_date']);
            $table->dropIndex(['action_business_date']);
            $table->dropColumn(['request_business_date', 'action_business_date']);
        });
    }
};
