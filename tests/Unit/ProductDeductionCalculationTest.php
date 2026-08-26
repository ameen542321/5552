<?php

namespace Tests\Unit;

use App\Models\Product;
use PHPUnit\Framework\TestCase;

class ProductDeductionCalculationTest extends TestCase
{
    public function test_fractional_custom_meters_are_converted_to_roll_fraction(): void
    {
        $product = new Product([
            'product_type' => 'fractional',
            'roll_length' => 20,
        ]);

        $this->assertSame(0.25, $product->calculateFinalDeduction(5, 'custom'));
        $this->assertSame(0.25, $product->calculateFinalDeduction(5, 'meters'));
    }

    public function test_fractional_ready_option_deduction_stays_as_provided_fraction(): void
    {
        $product = new Product([
            'product_type' => 'fractional',
            'roll_length' => 20,
        ]);

        $this->assertSame(0.50, $product->calculateFinalDeduction(0.50, 'default'));
    }

    public function test_splittable_piece_sale_is_converted_to_kit_fraction(): void
    {
        $product = new Product([
            'product_type' => 'standard',
            'is_splittable' => true,
            'items_per_unit' => 8,
        ]);

        $this->assertSame(0.25, $product->calculateFinalDeduction(2, 'piece'));
        $this->assertSame(1.0, $product->calculateFinalDeduction(1, 'default'));
    }

    public function test_waste_percentage_is_added_after_base_deduction(): void
    {
        $product = new Product([
            'product_type' => 'fractional',
            'roll_length' => 10,
            'waste_percentage' => 10,
        ]);

        // 5m من رول 10m = 0.5 رول، وبعد 10% هالك تصبح 0.55
        $this->assertSame(0.55, $product->calculateFinalDeduction(5, 'custom'));
    }
}
