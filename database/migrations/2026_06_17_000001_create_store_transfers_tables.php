<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('receiver_store_id')->constrained('stores')->cascadeOnDelete();
            $table->enum('status', ['pending', 'completed', 'rejected', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('created_by_type')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('action_by_type')->nullable();
            $table->unsignedBigInteger('action_by_id')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('receiver_seen_at')->nullable();
            $table->timestamps();

            $table->index(['sender_store_id', 'status']);
            $table->index(['receiver_store_id', 'status']);
            $table->index(['created_by_type', 'created_by_id']);
            $table->index(['action_by_type', 'action_by_id']);
        });

        Schema::create('store_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_transfer_id')->constrained('store_transfers')->cascadeOnDelete();
            $table->foreignId('sender_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('receiver_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('requested_quantity', 15, 3);
            $table->decimal('normalized_quantity', 15, 3);
            $table->string('unit_type', 30)->default('unit');
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('sender_stock_before', 15, 3)->nullable();
            $table->decimal('sender_stock_after', 15, 3)->nullable();
            $table->decimal('receiver_stock_before', 15, 3)->nullable();
            $table->decimal('receiver_stock_after', 15, 3)->nullable();
            $table->timestamps();

            $table->index('sender_product_id');
            $table->index('receiver_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_transfer_items');
        Schema::dropIfExists('store_transfers');
    }
};
