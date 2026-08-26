<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STORE_ID = 1;
    private const NOTE = 'توريد طلبية محمد';
    private const ORIGINAL_TIMESTAMP = '2026-08-01 00:00:00';
    private const CORRECTED_TIMESTAMP = '2026-08-20 00:00:00';

    public function up(): void
    {
        if (! Schema::hasTable('stock_movements')) {
            return;
        }

        // التقييد بالمتجر والملاحظة والتاريخين يمنع المساس بحركات المتاجر أو الطلبيات الأخرى.
        DB::table('stock_movements')
            ->where('store_id', self::STORE_ID)
            ->where('note', self::NOTE)
            ->whereDate('created_at', '2026-08-01')
            ->whereDate('updated_at', '2026-08-01')
            ->update([
                'created_at' => self::CORRECTED_TIMESTAMP,
                'updated_at' => self::CORRECTED_TIMESTAMP,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_movements')) {
            return;
        }

        DB::table('stock_movements')
            ->where('store_id', self::STORE_ID)
            ->where('note', self::NOTE)
            ->where('created_at', self::CORRECTED_TIMESTAMP)
            ->where('updated_at', self::CORRECTED_TIMESTAMP)
            ->update([
                'created_at' => self::ORIGINAL_TIMESTAMP,
                'updated_at' => self::ORIGINAL_TIMESTAMP,
            ]);
    }
};
