<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Modules\PurchaseOrders\Support\PurchaseOrderCostCalculator;
use PHPUnit\Framework\TestCase;

class PurchaseOrderCostCalculatorTest extends TestCase
{
    private PurchaseOrderCostCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PurchaseOrderCostCalculator();
    }

    public function test_standard_product_uses_latest_receipt_unit_cost(): void
    {
        $product = $this->product(['quantity' => 10, 'cost_price' => 8]);

        $cost = $this->calculator->normalizedProductCostFromReceipt($product, 50, 5, 'unit');

        $this->assertSame(10.0, $cost);
    }

    public function test_fractional_product_normalizes_meter_receipt_to_roll_cost(): void
    {
        $product = $this->product([
            'product_type' => 'fractional',
            'roll_length' => 20,
            'quantity' => 20,
            'cost_price' => 80,
        ]);

        $cost = $this->calculator->normalizedProductCostFromReceipt($product, 50, 10, 'meter');

        $this->assertSame(100.0, $cost);
    }

    private function product(array $attributes): Product
    {
        return new Product(array_merge([
            'product_type' => 'standard',
            'roll_length' => 0,
            'is_splittable' => false,
            'items_per_unit' => 1,
            'quantity' => 0,
            'cost_price' => 0,
        ], $attributes));
    }
}
