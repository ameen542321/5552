<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('supplier_name')->nullable();
            $table->enum('status', ['draft', 'sent', 'received', 'approved', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('store_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_purchase_order_id')->constrained('store_purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('matched_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('custom_product_name')->nullable();
            $table->decimal('quantity_requested', 15, 2);
            $table->decimal('quantity_received', 15, 2)->nullable();
            $table->string('unit_type', 30)->default('unit');
            $table->decimal('cost_price_at_order', 10, 2)->nullable();
            $table->decimal('cost_price_at_receipt', 10, 2)->nullable();
            $table->decimal('price_variance', 10, 2)->default(0);
            $table->decimal('price_variance_percent', 8, 2)->default(0);
            $table->boolean('update_product_cost')->default(false);
            $table->text('receipt_notes')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index('matched_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_purchase_order_items');
        Schema::dropIfExists('store_purchase_orders');
    }
};
