<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->decimal('balance_before', 18, 6)->nullable()->after('display_unit_label');
            $table->decimal('balance_after', 18, 6)->nullable()->after('balance_before');
        });

        DB::table('stock_movements')->update([
            'balance_before' => DB::raw('roll_length_at_movement'),
            'balance_after' => DB::raw('meters'),
        ]);

        $fractionalProductIds = DB::table('products')->where('product_type', 'fractional')->pluck('id');
        DB::table('stock_movements')
            ->where(function ($query) use ($fractionalProductIds): void {
                $query->where(function ($snapshotQuery): void {
                    $snapshotQuery->whereNotNull('product_type_at_movement')
                        ->where('product_type_at_movement', '<>', 'fractional');
                })->orWhere(function ($legacyQuery) use ($fractionalProductIds): void {
                    $legacyQuery->whereNull('product_type_at_movement')
                        ->whereNotIn('product_id', $fractionalProductIds);
                });
            })
            ->update(['roll_length_at_movement' => null, 'meters' => null]);
    }

    public function down(): void
    {
        DB::table('stock_movements')->whereNull('roll_length_at_movement')->update([
            'roll_length_at_movement' => DB::raw('balance_before'),
            'meters' => DB::raw('balance_after'),
        ]);

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropColumn(['balance_before', 'balance_after']);
        });
    }
};
