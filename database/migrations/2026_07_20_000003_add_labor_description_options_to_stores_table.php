<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('stores', 'labor_description_options')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table) {
            $column = $table->json('labor_description_options')->nullable();

            if (Schema::hasColumn('stores', 'bank_accounts')) {
                $column->after('bank_accounts');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('stores', 'labor_description_options')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('labor_description_options');
        });
    }
};
