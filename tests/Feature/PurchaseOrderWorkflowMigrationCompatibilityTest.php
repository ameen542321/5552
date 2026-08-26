<?php

namespace Tests\Feature;

use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseOrderWorkflowMigrationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_migration_can_run_again_without_deleting_existing_schema(): void
    {
        $migration = require database_path('migrations/2026_08_06_180000_rebuild_purchase_order_workflow.php');

        $migration->up();

        $this->assertTrue(Schema::hasColumn('store_purchase_orders', 'accountant_id'));
        $this->assertTrue(Schema::hasColumn('store_purchase_orders', 'workflow_status'));
        $this->assertTrue(Schema::hasTable('store_purchase_order_events'));
        $this->assertTrue(Schema::hasTable('store_purchase_order_count_attempts'));
        $countForeignColumns = collect(Schema::getForeignKeys('store_purchase_order_count_attempts'))
            ->flatMap(fn (array $foreignKey) => $foreignKey['columns'] ?? [])
            ->all();
        $this->assertContains('store_purchase_order_id', $countForeignColumns);
        $this->assertContains('store_purchase_order_item_id', $countForeignColumns);
        $this->assertContains('accountant_id', $countForeignColumns);
    }

    public function test_down_keeps_preexisting_purchase_order_data_structures(): void
    {
        $migration = require database_path('migrations/2026_08_06_180000_rebuild_purchase_order_workflow.php');

        $migration->down();

        $this->assertTrue(Schema::hasColumn('store_purchase_orders', 'accountant_id'));
        $this->assertTrue(Schema::hasTable('store_purchase_order_events'));
    }
}
