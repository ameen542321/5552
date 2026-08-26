<?php

namespace Tests\Unit;

use App\Models\StockMovement;
use PHPUnit\Framework\TestCase;

class StockMovementAccessorsTest extends TestCase
{
    public function test_previous_and_current_balance_accessors_read_existing_columns(): void
    {
        $movement = new StockMovement([
            'quantity' => '2.50',
            'roll_length_at_movement' => '15.75',
            'meters' => '18.25',
        ]);

        $this->assertSame(2.5, $movement->quantity);
        $this->assertSame(15.75, $movement->previous_balance);
        $this->assertSame(18.25, $movement->current_balance);
    }

    public function test_balance_accessors_default_to_zero_when_columns_are_missing(): void
    {
        $movement = new StockMovement();

        $this->assertSame(0.0, $movement->previous_balance);
        $this->assertSame(0.0, $movement->current_balance);
    }

    public function test_balance_accessors_prefer_dedicated_balance_columns(): void
    {
        $movement = new StockMovement([
            'balance_before' => '7',
            'balance_after' => '6',
            'roll_length_at_movement' => '70',
            'meters' => '60',
        ]);

        $this->assertSame(7.0, $movement->previous_balance);
        $this->assertSame(6.0, $movement->current_balance);
    }

    public function test_historical_set_snapshot_keeps_kit_unit_after_product_configuration_changes(): void
    {
        $movement = new StockMovement([
            'display_unit_label' => 'طقم',
            'unit_type_at_movement' => 'kit',
            'product_type_at_movement' => 'standard',
            'is_splittable_at_movement' => true,
            'items_per_unit_at_movement' => 10,
            'requested_quantity' => 1,
        ]);

        $this->assertSame('طقم', $movement->snapshotUnitLabel());
        $this->assertSame(1.0, $movement->quantityInSnapshotUnit(1));
    }

    public function test_historical_piece_snapshot_uses_items_per_unit_from_movement(): void
    {
        $movement = new StockMovement([
            'display_unit_label' => 'حبة',
            'unit_type_at_movement' => 'piece',
            'product_type_at_movement' => 'standard',
            'is_splittable_at_movement' => true,
            'items_per_unit_at_movement' => 10,
        ]);

        $this->assertSame(10.0, $movement->quantityInSnapshotUnit(1));
    }

    public function test_internal_use_movement_is_labeled_as_accountant_consumption(): void
    {
        $movement = new StockMovement([
            'type' => 'decrease',
            'note' => 'استهلاك داخلي: مواد تشغيلية',
        ]);

        $this->assertSame('استهلاك محاسب', $movement->operation_label);
    }

    public function test_accountant_consumption_adjustment_keeps_consumption_label(): void
    {
        $movement = new StockMovement([
            'type' => 'decrease',
            'note' => 'تعديل استهلاك محاسب (زيادة الخصم) - عملية #15',
        ]);

        $this->assertSame('استهلاك محاسب', $movement->operation_label);
    }

    public function test_manual_decrease_remains_labeled_as_withdrawal(): void
    {
        $movement = new StockMovement([
            'type' => 'decrease',
            'note' => 'تالف أثناء الجرد',
        ]);

        $this->assertSame('سحب', $movement->operation_label);
    }
}
