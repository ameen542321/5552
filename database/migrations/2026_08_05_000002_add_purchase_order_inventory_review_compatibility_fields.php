<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * يكمّل مخطط قواعد البيانات التي طبقت نسخة دورة الجرد السابقة بالفعل.
     * جميع الإضافات شرطية حتى لا يعاد إنشاء أي عمود موجود أو المساس ببياناته.
     */
    public function up(): void
    {
        if (Schema::hasTable('store_purchase_orders')) {
            Schema::table('store_purchase_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('store_purchase_orders', 'inventory_review_status')) {
                    $table->string('inventory_review_status', 40)->nullable()->after('status')->index();
                }
                if (! Schema::hasColumn('store_purchase_orders', 'inventory_review_note')) {
                    $table->text('inventory_review_note')->nullable()->after('notes');
                }
                if (! Schema::hasColumn('store_purchase_orders', 'inventory_returned_at')) {
                    $table->timestamp('inventory_returned_at')->nullable()->after('sent_at');
                }
                if (! Schema::hasColumn('store_purchase_orders', 'inventory_draft_saved_at')) {
                    $table->timestamp('inventory_draft_saved_at')->nullable()->after('inventory_returned_at');
                }
                if (! Schema::hasColumn('store_purchase_orders', 'inventory_submitted_at')) {
                    $table->timestamp('inventory_submitted_at')->nullable()->after('inventory_draft_saved_at');
                }
                if (! Schema::hasColumn('store_purchase_orders', 'inventory_submitted_by')) {
                    $table->unsignedBigInteger('inventory_submitted_by')->nullable()->after('inventory_submitted_at');
                }
            });
        }

        if (Schema::hasTable('store_purchase_order_items')) {
            Schema::table('store_purchase_order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('store_purchase_order_items', 'inventory_count_required')) {
                    $table->boolean('inventory_count_required')->default(false)->after('receipt_notes');
                }
                if (! Schema::hasColumn('store_purchase_order_items', 'inventory_count_quantity')) {
                    $table->decimal('inventory_count_quantity', 15, 2)->nullable()->after('inventory_count_required');
                }
                if (! Schema::hasColumn('store_purchase_order_items', 'inventory_count_note')) {
                    $table->text('inventory_count_note')->nullable()->after('inventory_count_quantity');
                }
                if (! Schema::hasColumn('store_purchase_order_items', 'system_quantity_snapshot')) {
                    $table->decimal('system_quantity_snapshot', 15, 2)->nullable()->after('inventory_count_note');
                }
                if (! Schema::hasColumn('store_purchase_order_items', 'inventory_snapshot_at')) {
                    $table->timestamp('inventory_snapshot_at')->nullable()->after('system_quantity_snapshot');
                }
                if (! Schema::hasColumn('store_purchase_order_items', 'inventory_count_submitted_at')) {
                    $table->timestamp('inventory_count_submitted_at')->nullable()->after('inventory_snapshot_at');
                }
                if (! Schema::hasColumn('store_purchase_order_items', 'inventory_count_submitted_by')) {
                    $table->unsignedBigInteger('inventory_count_submitted_by')->nullable()->after('inventory_count_submitted_at');
                }
                if (! Schema::hasColumn('store_purchase_order_items', 'inventory_changed_after_count')) {
                    $table->boolean('inventory_changed_after_count')->default(false)->after('inventory_count_submitted_by');
                }
            });
        }
    }

    /**
     * لا نحذف أعمدة توافقية في الرجوع، فقد تكون منشأة أصلًا بواسطة نسخة أقدم
     * ومحتوية على بيانات تشغيلية لا تخص هذه الهجرة وحدها.
     */
    public function down(): void
    {
    }
};
