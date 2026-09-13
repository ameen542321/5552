<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_purchase_orders', function (Blueprint $table): void {
            $table->timestamp('reversed_at')->nullable()->after('approved_at');
            $table->foreignId('reversed_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable()->after('reversed_by');
            $table->uuid('reversal_operation_id')->nullable()->unique()->after('reversal_reason');
        });
    }

    public function down(): void
    {
        Schema::table('store_purchase_orders', function (Blueprint $table): void {
            $table->dropForeign(['reversed_by']);
            $table->dropUnique(['reversal_operation_id']);
            $table->dropColumn(['reversed_at', 'reversed_by', 'reversal_reason', 'reversal_operation_id']);
        });
    }
};
