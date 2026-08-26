<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\ProductQuantityFormatter;
use PHPUnit\Framework\TestCase;

class ProductQuantityFormatterTest extends TestCase
{
    public function test_standard_product_is_displayed_as_pieces(): void
    {
        $product = $this->product(['quantity' => 5, 'min_stock' => 2]);

        $this->assertSame('5 قطعة', ProductQuantityFormatter::currentStock($product));
        $this->assertSame('2 قطعة', ProductQuantityFormatter::minimumStock($product));
    }

    public function test_partial_set_is_displayed_as_pieces_only(): void
    {
        $product = $this->product([
            'quantity' => 0.5,
            'min_stock' => 0.25,
            'is_splittable' => true,
            'items_per_unit' => 4,
        ]);

        $this->assertSame('2 حبة', ProductQuantityFormatter::currentStock($product));
        $this->assertSame('1 حبة', ProductQuantityFormatter::minimumStock($product));
    }

    public function test_complete_and_partial_sets_are_displayed_together(): void
    {
        $product = $this->product([
            'quantity' => 1.5,
            'min_stock' => 2,
            'is_splittable' => true,
            'items_per_unit' => 4,
        ]);

        $this->assertSame('1 طقم و2 حبة', ProductQuantityFormatter::currentStock($product));
        $this->assertSame('2 طقم', ProductQuantityFormatter::minimumStock($product));
    }

    public function test_minimum_stock_preserves_one_set_plus_one_piece(): void
    {
        $product = $this->product([
            'min_stock' => 25 / 24,
            'is_splittable' => true,
            'items_per_unit' => 24,
        ]);

        $this->assertSame('1 طقم و1 حبة', ProductQuantityFormatter::minimumStock($product));
    }

    public function test_set_stock_can_be_displayed_as_pieces_for_piece_default_sales(): void
    {
        $product = $this->product([
            'quantity' => 1.5,
            'is_splittable' => true,
            'items_per_unit' => 4,
        ]);

        $this->assertSame('6 حبة', ProductQuantityFormatter::currentStock($product, 'piece'));
        $this->assertSame('1 طقم و2 حبة', ProductQuantityFormatter::currentStock($product, 'unit'));
        $product->min_stock = 0.5;
        $this->assertSame('2 حبة', ProductQuantityFormatter::minimumStock($product, 'piece'));
    }

    public function test_partial_roll_is_displayed_as_meters_only(): void
    {
        $product = $this->product([
            'product_type' => 'fractional',
            'quantity' => 5,
            'min_stock' => 0.25,
            'roll_length' => 20,
        ]);

        $this->assertSame('5 متر', ProductQuantityFormatter::currentStock($product));
        $this->assertSame('5 متر', ProductQuantityFormatter::minimumStock($product));
    }

    public function test_complete_and_partial_rolls_are_displayed_together(): void
    {
        $product = $this->product([
            'product_type' => 'fractional',
            'quantity' => 50,
            'min_stock' => 1.5,
            'roll_length' => 20,
        ]);

        $this->assertSame('2 رول و10 متر', ProductQuantityFormatter::currentStock($product));
        $this->assertSame('1 رول و10 متر', ProductQuantityFormatter::minimumStock($product));
    }

    public function test_transfer_quantity_uses_human_readable_operation_units(): void
    {
        $set = $this->product(['is_splittable' => true, 'items_per_unit' => 4]);
        $roll = $this->product(['product_type' => 'fractional', 'roll_length' => 20]);
        $standard = $this->product([]);

        $this->assertSame('1 طقم', ProductQuantityFormatter::transferQuantity($set, 1, 'kit'));
        $this->assertSame('1 حبة من طقم', ProductQuantityFormatter::transferQuantity($set, 1, 'piece'));
        $this->assertSame('1 رول', ProductQuantityFormatter::transferQuantity($roll, 1, 'roll'));
        $this->assertSame('5 متر من رول', ProductQuantityFormatter::transferQuantity($roll, 5, 'meter'));
        $this->assertSame('2 حبة', ProductQuantityFormatter::transferQuantity($standard, 2, 'unit'));
    }

    public function test_transfer_quantity_remains_readable_when_the_historical_product_is_missing(): void
    {
        $this->assertSame('2 حبة', ProductQuantityFormatter::transferQuantity(null, 2, 'piece'));
        $this->assertSame('5 متر من رول', ProductQuantityFormatter::transferQuantity(null, 5, 'meter'));
    }

    public function test_inventory_snapshots_keep_the_product_stock_unit(): void
    {
        $standard = $this->product([]);
        $set = $this->product(['is_splittable' => true, 'items_per_unit' => 4]);
        $roll = $this->product(['product_type' => 'fractional', 'roll_length' => 20]);

        $this->assertSame('15 حبة', ProductQuantityFormatter::stockSnapshot($standard, 15));
        $this->assertSame('1 طقم و2 حبة', ProductQuantityFormatter::stockSnapshot($set, 1.5));
        $this->assertSame('2 رول و10 متر', ProductQuantityFormatter::stockSnapshot($roll, 50));
    }

    public function test_inventory_movement_uses_the_default_sale_unit(): void
    {
        $pieceDefault = $this->product([
            'is_splittable' => true,
            'items_per_unit' => 24,
            'quick_sale_default_unit' => 'piece',
        ]);
        $setDefault = $this->product([
            'is_splittable' => true,
            'items_per_unit' => 24,
            'quick_sale_default_unit' => 'unit',
        ]);

        $this->assertSame('حبة', ProductQuantityFormatter::inventoryDefaultUnit($pieceDefault));
        $this->assertSame(2.0, ProductQuantityFormatter::inventoryQuantity($pieceDefault, 2 / 24));
        $this->assertSame('طقم', ProductQuantityFormatter::inventoryDefaultUnit($setDefault));
        $this->assertSame(0.5, ProductQuantityFormatter::inventoryQuantity($setDefault, 0.5));
    }

    public function test_stored_number_is_not_rounded_for_inventory_display(): void
    {
        $this->assertSame('0.542083', ProductQuantityFormatter::storedNumber('0.54208300'));
        $this->assertSame('13', ProductQuantityFormatter::storedNumber('13.00'));
        $this->assertSame('10', ProductQuantityFormatter::storedNumber(10));
        $this->assertSame('0', ProductQuantityFormatter::storedNumber('0.00'));
    }

    public function test_sale_item_quantity_decomposes_sets_and_rolls(): void
    {
        $this->assertSame('1 حبة', ProductQuantityFormatter::saleItemQuantity(1, 'piece', 'standard', true, 4, 0, 0.25));
        $this->assertSame('1 طقم و2 حبة', ProductQuantityFormatter::saleItemQuantity(1.5, 'kit', 'standard', true, 4, 0, 1.5));
        $this->assertSame('1 رول و5 متر', ProductQuantityFormatter::saleItemQuantity(1.25, 'roll', 'fractional', false, 0, 20, 25));
        $this->assertSame('5 متر', ProductQuantityFormatter::saleItemQuantity(5, 'meter', 'fractional', false, 0, 20, 5, 5));
        $this->assertSame('2 حبة', ProductQuantityFormatter::saleItemQuantity(2, 'unit', 'standard', false, 0, 0, 2));
    }

    private function product(array $attributes): Product
    {
        return new Product(array_merge([
            'product_type' => 'standard',
            'quantity' => 0,
            'min_stock' => 0,
            'roll_length' => 0,
            'is_splittable' => false,
            'items_per_unit' => 1,
        ], $attributes));
    }
}
