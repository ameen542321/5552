<?php

namespace Tests\Unit;

use App\Models\Product;
use PHPUnit\Framework\TestCase;

class ProductUnitNormalizationTest extends TestCase
{
    public function test_fractional_roll_quantity_is_converted_to_meters(): void
    {
        $product = new Product([
            'product_type' => 'fractional',
            'roll_length' => 30,
        ]);

        $this->assertSame(60.0, $product->normalizeQuantityByUnit(2, 'roll'));
        $this->assertSame(60.0, $product->normalizeQuantityByUnit(2, 'unit'));
    }

    public function test_fractional_meter_quantity_stays_in_base_unit(): void
    {
        $product = new Product([
            'product_type' => 'fractional',
            'roll_length' => 30,
        ]);

        $this->assertSame(5.5, $product->normalizeQuantityByUnit(5.5, 'meter'));
        $this->assertSame(5.5, $product->normalizeQuantityByUnit(5.5, 'meters'));
        $this->assertSame(5.5, $product->normalizeQuantityByUnit(5.5, 'custom'));
    }

    public function test_splittable_piece_quantity_is_converted_to_kit_fraction(): void
    {
        $product = new Product([
            'product_type' => 'standard',
            'is_splittable' => true,
            'items_per_unit' => 4,
        ]);

        $this->assertSame(0.5, $product->normalizeQuantityByUnit(2, 'piece'));
        $this->assertSame(3.0, $product->normalizeQuantityByUnit(3, 'kit'));
        $this->assertSame(3.0, $product->normalizeQuantityByUnit(3, 'unit'));
    }

    public function test_default_mode_preserves_pre_normalized_quantities(): void
    {
        $fractional = new Product([
            'product_type' => 'fractional',
            'roll_length' => 25,
        ]);

        $splittable = new Product([
            'product_type' => 'standard',
            'is_splittable' => true,
            'items_per_unit' => 6,
        ]);

        $this->assertSame(2.75, $fractional->normalizeQuantityByUnit(2.75, 'default'));
        $this->assertSame(1.5, $splittable->normalizeQuantityByUnit(1.5, 'normalized'));
    }
}
