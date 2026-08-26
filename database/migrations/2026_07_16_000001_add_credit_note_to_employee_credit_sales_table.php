<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_credit_sales', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_credit_sales', 'credit_note')) {
                $table->text('credit_note')->nullable()->after('description');
            }
        });

        DB::table('employee_credit_sales')
            ->whereNull('credit_note')
            ->where('description', 'like', '% - ملاحظة: %')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    [$description, $creditNote] = explode(' - ملاحظة: ', (string) $row->description, 2);

                    DB::table('employee_credit_sales')
                        ->where('id', $row->id)
                        ->update([
                            'description' => $description,
                            'credit_note' => $creditNote,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('employee_credit_sales', function (Blueprint $table) {
            if (Schema::hasColumn('employee_credit_sales', 'credit_note')) {
                $table->dropColumn('credit_note');
            }
        });
    }
};
