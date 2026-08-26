<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * يكمل مخطط الطلبيات من دون إعادة إنشاء عمود أو جدول طبقته نسخة سابقة.
     */
    public function up(): void
    {
        if (Schema::hasTable('store_purchase_orders')) {
            Schema::table('store_purchase_orders', function (Blueprint $table): void {
                if (! Schema::hasColumn('store_purchase_orders', 'accountant_id')) {
                    $table->foreignId('accountant_id')->nullable()->after('user_id')->constrained('accountants')->nullOnDelete();
                }
                if (! Schema::hasColumn('store_purchase_orders', 'workflow_status')) {
                    $table->string('workflow_status', 50)->default('draft_accountant')->after('status');
                }
                if (! Schema::hasColumn('store_purchase_orders', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable();
                }
                if (! Schema::hasColumn('store_purchase_orders', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable();
                }
                if (! Schema::hasColumn('store_purchase_orders', 'receipt_actor_type')) {
                    $table->string('receipt_actor_type', 20)->nullable();
                }
                if (! Schema::hasColumn('store_purchase_orders', 'receipt_actor_id')) {
                    $table->unsignedBigInteger('receipt_actor_id')->nullable();
                }
                if (! Schema::hasColumn('store_purchase_orders', 'approval_operation_id')) {
                    $table->uuid('approval_operation_id')->nullable();
                }
                if (! Schema::hasColumn('store_purchase_orders', 'final_notice_until')) {
                    $table->timestamp('final_notice_until')->nullable();
                }
            });

            if (! Schema::hasIndex('store_purchase_orders', ['workflow_status'])) {
                Schema::table('store_purchase_orders', fn (Blueprint $table) => $table->index('workflow_status'));
            }
            $hasDuplicateApprovalOperations = DB::table('store_purchase_orders')
                ->whereNotNull('approval_operation_id')
                ->select('approval_operation_id')
                ->groupBy('approval_operation_id')
                ->havingRaw('COUNT(*) > 1')
                ->exists();
            if (! $hasDuplicateApprovalOperations && ! Schema::hasIndex('store_purchase_orders', ['approval_operation_id'], 'unique')) {
                Schema::table('store_purchase_orders', fn (Blueprint $table) => $table->unique('approval_operation_id'));
            }
        }

        if (Schema::hasTable('store_purchase_order_items')) {
            Schema::table('store_purchase_order_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('store_purchase_order_items', 'inventory_count_attempt')) {
                    $table->unsignedTinyInteger('inventory_count_attempt')->default(0);
                }
                if (! Schema::hasColumn('store_purchase_order_items', 'excluded_after_count')) {
                    $table->boolean('excluded_after_count')->default(false);
                }
                if (! Schema::hasColumn('store_purchase_order_items', 'excluded_at')) {
                    $table->timestamp('excluded_at')->nullable();
                }
                if (! Schema::hasColumn('store_purchase_order_items', 'exclusion_reason')) {
                    $table->text('exclusion_reason')->nullable();
                }
                if (! Schema::hasColumn('store_purchase_order_items', 'changed_by_type')) {
                    $table->string('changed_by_type', 20)->nullable();
                }
                if (! Schema::hasColumn('store_purchase_order_items', 'changed_by_id')) {
                    $table->unsignedBigInteger('changed_by_id')->nullable();
                }
            });

            if (! Schema::hasIndex('store_purchase_order_items', ['excluded_after_count'])) {
                Schema::table('store_purchase_order_items', fn (Blueprint $table) => $table->index('excluded_after_count'));
            }
        }

        if (! Schema::hasTable('store_purchase_order_events')) {
            Schema::create('store_purchase_order_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('store_purchase_order_id');
                $table->unsignedBigInteger('store_purchase_order_item_id')->nullable();
                $table->string('event', 60);
                $table->string('from_status', 50)->nullable();
                $table->string('to_status', 50)->nullable();
                $table->string('actor_type', 20);
                $table->unsignedBigInteger('actor_id');
                $table->text('note')->nullable();
                $table->json('data')->nullable();
                $table->timestamps();
                $table->index(['store_purchase_order_id', 'created_at'], 'po_events_order_created_index');
            });
        }

        if (! Schema::hasIndex('store_purchase_order_events', ['store_purchase_order_id', 'created_at'])) {
            Schema::table('store_purchase_order_events', fn (Blueprint $table) => $table->index(['store_purchase_order_id', 'created_at'], 'po_events_order_created_index'));
        }

        if (! $this->hasForeignKeyOnColumn('store_purchase_order_events', 'store_purchase_order_id')) {
            Schema::table('store_purchase_order_events', function (Blueprint $table): void {
                $table->foreign('store_purchase_order_id', 'po_events_order_fk')->references('id')->on('store_purchase_orders')->cascadeOnDelete();
            });
        }
        if (! $this->hasForeignKeyOnColumn('store_purchase_order_events', 'store_purchase_order_item_id')) {
            Schema::table('store_purchase_order_events', function (Blueprint $table): void {
                $table->foreign('store_purchase_order_item_id', 'po_events_item_fk')->references('id')->on('store_purchase_order_items')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('store_purchase_order_count_attempts')) {
            Schema::create('store_purchase_order_count_attempts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('store_purchase_order_id');
                $table->unsignedBigInteger('store_purchase_order_item_id');
                $table->unsignedTinyInteger('attempt');
                $table->decimal('counted_quantity', 15, 2);
                $table->decimal('system_quantity_image', 15, 2);
                $table->string('unit_type', 30);
                $table->unsignedBigInteger('accountant_id')->nullable();
                $table->text('note')->nullable();
                $table->timestamp('submitted_at');
                $table->timestamps();
                $table->unique(['store_purchase_order_item_id', 'attempt'], 'po_count_attempt_item_attempt_unique');
            });
        }

        if (! Schema::hasIndex('store_purchase_order_count_attempts', ['store_purchase_order_item_id', 'attempt'], 'unique')) {
            Schema::table('store_purchase_order_count_attempts', fn (Blueprint $table) => $table->unique(['store_purchase_order_item_id', 'attempt'], 'po_count_attempt_item_attempt_unique'));
        }

        if (! $this->hasForeignKeyOnColumn('store_purchase_order_count_attempts', 'store_purchase_order_id')) {
            Schema::table('store_purchase_order_count_attempts', function (Blueprint $table): void {
                $table->foreign('store_purchase_order_id', 'po_counts_order_fk')->references('id')->on('store_purchase_orders')->cascadeOnDelete();
            });
        }
        if (! $this->hasForeignKeyOnColumn('store_purchase_order_count_attempts', 'store_purchase_order_item_id')) {
            Schema::table('store_purchase_order_count_attempts', function (Blueprint $table): void {
                $table->foreign('store_purchase_order_item_id', 'po_counts_item_fk')->references('id')->on('store_purchase_order_items')->cascadeOnDelete();
            });
        }
        if (! $this->hasForeignKeyOnColumn('store_purchase_order_count_attempts', 'accountant_id')) {
            Schema::table('store_purchase_order_count_attempts', function (Blueprint $table): void {
                $table->foreign('accountant_id', 'po_counts_accountant_fk')->references('id')->on('accountants')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('store_purchase_orders', 'workflow_status')) {
            DB::table('store_purchase_orders')->where('status', 'draft')->whereIn('workflow_status', ['', 'draft_accountant'])->update(['workflow_status' => 'pending_owner_review']);
            DB::table('store_purchase_orders')->where('status', 'sent')->whereIn('workflow_status', ['', 'draft_accountant'])->update(['workflow_status' => 'pending_receipt_confirmation']);
            DB::table('store_purchase_orders')->where('status', 'received')->whereIn('workflow_status', ['', 'draft_accountant'])->update(['workflow_status' => 'pending_inventory_approval']);
            DB::table('store_purchase_orders')->where('status', 'approved')->whereIn('workflow_status', ['', 'draft_accountant'])->update(['workflow_status' => 'approved_and_supplied']);
            DB::table('store_purchase_orders')->where('status', 'cancelled')->whereIn('workflow_status', ['', 'draft_accountant'])->update(['workflow_status' => 'cancelled']);
        }
    }

    /**
     * لا نحذف شيئًا عند الرجوع؛ فقد تكون الأعمدة والجداول موجودة قبل هذه الهجرة
     * أو أصبحت تحتوي بيانات تشغيلية بعد تطبيق الدورة الجديدة.
     */
    public function down(): void
    {
    }

    private function hasForeignKeyOnColumn(string $table, string $column): bool
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return false;
        }

        return collect(Schema::getForeignKeys($table))->contains(
            fn (array $foreignKey): bool => in_array($column, $foreignKey['columns'] ?? [], true)
        );
    }
};
