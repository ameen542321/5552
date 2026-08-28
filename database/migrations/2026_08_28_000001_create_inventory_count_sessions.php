<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_count_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('accountant_id')->nullable()->constrained('accountants')->nullOnDelete();
            $table->string('status', 40)->default('draft')->index();
            $table->string('source_type', 30)->default('standalone');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('sent_to_accountant_at')->nullable();
            $table->timestamp('submitted_to_owner_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['store_id', 'status']);
        });

        Schema::create('inventory_count_session_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_count_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name_snapshot');
            $table->text('product_description_snapshot')->nullable();
            $table->string('count_type', 30)->default('periodic');
            $table->string('unit_type', 20)->default('unit');
            $table->decimal('accountant_quantity', 14, 3)->nullable();
            $table->date('count_business_date')->nullable()->index();
            $table->timestamp('accountant_updated_at')->nullable();
            $table->text('accountant_note')->nullable();
            $table->decimal('system_quantity_snapshot', 14, 3)->nullable();
            $table->timestamp('system_snapshot_at')->nullable();
            $table->decimal('owner_quantity', 14, 3)->nullable();
            $table->text('owner_adjustment_reason')->nullable();
            $table->string('decision', 30)->default('pending')->index();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['inventory_count_session_id', 'product_id'], 'inventory_session_product_unique');
        });

        Schema::table('inventory_logs', function (Blueprint $table): void {
            $table->foreignId('inventory_count_session_item_id')->nullable()->after('product_id')
                ->constrained('inventory_count_session_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inventory_count_session_item_id');
        });
        Schema::dropIfExists('inventory_count_session_items');
        Schema::dropIfExists('inventory_count_sessions');
    }
};
