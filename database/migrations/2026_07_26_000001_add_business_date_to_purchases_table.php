<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // يبقى الحقل فارغًا لمشتريات المالك التي لا ترتبط بشفت، فتعود القراءة إلى created_at.
            if (! Schema::hasColumn('purchases', 'business_date')) {
                $table->date('business_date')->nullable()->after('description')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'business_date')) {
                $table->dropIndex(['business_date']);
                $table->dropColumn('business_date');
            }
        });
    }
};
