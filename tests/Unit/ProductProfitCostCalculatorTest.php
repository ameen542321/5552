<?php

namespace Tests\Unit;

use App\Support\ProductProfitCostCalculator;
use PHPUnit\Framework\TestCase;

class ProductProfitCostCalculatorTest extends TestCase
{
    public function test_fractional_item_uses_custom_consumption_when_available(): void
    {
        $product = [
            'cost_price' => 100,
            'product_type' => 'fractional',
            'roll_length' => 20,
        ];

        $item = [
            'quantity' => 5,
            'unit_type' => 'meters',
            'custom_consumption' => 0.25,
        ];

        $this->assertSame(25.0, ProductProfitCostCalculator::calculateItemCost($product, $item));
    }

    public function test_fractional_item_converts_meters_to_roll_fraction_when_needed(): void
    {
        $product = [
            'cost_price' => 80,
            'product_type' => 'fractional',
            'roll_length' => 20,
        ];

        $item = [
            'quantity' => 5,
            'unit_type' => 'meters',
            'custom_consumption' => 0,
        ];

        $this->assertSame(20.0, ProductProfitCostCalculator::calculateItemCost($product, $item));
    }

    public function test_splittable_piece_item_uses_piece_cost_only_for_piece_sales(): void
    {
        $product = [
            'cost_price' => 120,
            'is_splittable' => 1,
            'items_per_unit' => 4,
            'product_type' => 'standard',
        ];

        $pieceItem = [
            'quantity' => 2,
            'unit_type' => 'piece',
        ];

        $kitItem = [
            'quantity' => 2,
            'unit_type' => 'unit',
        ];

        $this->assertSame(60.0, ProductProfitCostCalculator::calculateItemCost($product, $pieceItem));
        $this->assertSame(240.0, ProductProfitCostCalculator::calculateItemCost($product, $kitItem));
    }
}
