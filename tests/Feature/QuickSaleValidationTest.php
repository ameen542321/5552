<?php

namespace Tests\Feature;

use App\Models\Accountant;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuickSaleValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeating_the_same_client_operation_id_cannot_create_a_second_sale(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'subscription_end_at' => now()->addDays(30),
        ]);
        $store = Store::factory()->create(['user_id' => $owner->id, 'status' => 'active']);
        $accountant = Accountant::create([
            'user_id' => $owner->id,
            'store_id' => $store->id,
            'name' => 'Duplicate Guard Accountant',
            'email' => 'duplicate-guard@example.com',
            'phone' => '0500000099',
            'password' => 'password',
            'status' => 'active',
        ]);
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'Duplicate Guard Product',
            'slug' => 'duplicate-guard-product',
            'price' => 50,
            'cost_price' => 20,
            'quantity' => 5,
            'status' => 'active',
            'product_type' => 'standard',
            'is_splittable' => false,
            'items_per_unit' => 1,
            'piece_price' => 0,
            'min_stock' => 1,
        ]);
        $operationId = (string) Str::uuid();
        $submitToken = (string) Str::uuid();
        $payload = [
            'items' => json_encode([[
                'product_id' => $product->id,
                'sale_unit' => 'unit',
                'quantity' => 1,
                'price' => 50,
                'total' => 50,
                'fraction_id' => '0',
            ]], JSON_THROW_ON_ERROR),
            'labor_total' => 0,
            'tax_rate' => 0,
            'paid_amount' => 50,
            'sale_type' => 'cash',
            'description' => 'Duplicate protection test',
            'has_invoice' => 0,
            'client_operation_id' => $operationId,
            'quick_sale_submit_token' => $submitToken,
        ];

        $this->actingAs($accountant, 'accountant')
            ->withSession(['quick_sale_submit_token' => $submitToken])
            ->post(route('accountant.quick-sale.submit'), $payload);
        $this->actingAs($accountant, 'accountant')
            ->post(route('accountant.quick-sale.submit'), $payload);

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(4.0, (float) $product->fresh()->quantity);
    }

    public function test_fractional_product_requires_fraction_selection_before_sale_submission(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'subscription_end_at' => now()->addDays(30),
        ]);

        $store = Store::factory()->create([
            'user_id' => $owner->id,
            'status' => 'active',
        ]);

        $accountant = Accountant::create([
            'user_id' => $owner->id,
            'store_id' => $store->id,
            'employee_id' => null,
            'name' => 'Tester Accountant',
            'email' => 'accountant@example.com',
            'phone' => '0500000000',
            'password' => 'password',
            'status' => 'active',
        ]);

        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'category_id' => null,
            'name' => 'Fractional Item',
            'slug' => 'fractional-item',
            'price' => 100,
            'cost_price' => 50,
            'quantity' => 40,
            'status' => 'active',
            'product_type' => 'fractional',
            'roll_length' => 20,
            'is_splittable' => false,
            'items_per_unit' => 1,
            'piece_price' => 0,
            'min_stock' => 1,
        ]);

        $items = [[
            'product_id' => $product->id,
            'sale_unit' => 'unit',
            'quantity' => 1,
            'price' => 100,
            'total' => 100,
            'fraction_id' => '0',
        ]];

        $submitToken = (string) Str::uuid();
        $response = $this
            ->actingAs($accountant, 'accountant')
            ->withSession(['quick_sale_submit_token' => $submitToken])
            ->from(route('accountant.quick-sale.index'))
            ->post(route('accountant.quick-sale.submit'), [
                'items' => json_encode($items, JSON_THROW_ON_ERROR),
                'labor_total' => 0,
                'tax_rate' => 0,
                'paid_amount' => 100,
                'sale_type' => 'cash',
                'description' => 'Test sale',
                'has_invoice' => 0,
                'quick_sale_submit_token' => $submitToken,
            ]);

        $response->assertRedirect(route('accountant.quick-sale.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('sales', 0);
    }


    public function test_splittable_piece_quick_sale_persists_unit_type_and_normalized_stock_deduction(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'subscription_end_at' => now()->addDays(30),
        ]);

        $store = Store::factory()->create([
            'user_id' => $owner->id,
            'status' => 'active',
        ]);

        $accountant = Accountant::create([
            'user_id' => $owner->id,
            'store_id' => $store->id,
            'employee_id' => null,
            'name' => 'Piece Sale Accountant',
            'email' => 'piece-sale@example.com',
            'phone' => '0500000002',
            'password' => 'password',
            'status' => 'active',
        ]);

        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'category_id' => null,
            'name' => 'Piece Sale Kit',
            'slug' => 'piece-sale-kit',
            'price' => 120,
            'cost_price' => 80,
            'quantity' => 10,
            'status' => 'active',
            'product_type' => 'standard',
            'roll_length' => 0,
            'is_splittable' => true,
            'items_per_unit' => 4,
            'piece_price' => 35,
            'min_stock' => 1,
        ]);

        $items = [[
            'product_id' => $product->id,
            'sale_unit' => 'piece',
            'quantity' => 2,
            'price' => 35,
            'total' => 70,
            'fraction_id' => '0',
        ]];

        $submitToken = (string) Str::uuid();
        $response = $this
            ->actingAs($accountant, 'accountant')
            ->withSession(['quick_sale_submit_token' => $submitToken])
            ->from(route('accountant.quick-sale.index'))
            ->post(route('accountant.quick-sale.submit'), [
                'items' => json_encode($items, JSON_THROW_ON_ERROR),
                'labor_total' => 0,
                'tax_rate' => 0,
                'paid_amount' => 70,
                'sale_type' => 'cash',
                'description' => 'Piece sale test',
                'has_invoice' => 0,
                'quick_sale_submit_token' => $submitToken,
            ]);

        $response->assertRedirect(route('accountant.quick-sale.index'));
        $response->assertSessionHas('success');

        $this->assertSame(9.5, (float) $product->fresh()->quantity);
        $this->assertDatabaseHas('sale_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_type' => 'piece',
            'total' => 70,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'user_id' => $owner->id,
            'type' => 'decrease',
            'quantity' => 0.5,
            'balance_before' => 10,
            'balance_after' => 9.5,
            'roll_length_at_movement' => null,
            'meters' => null,
        ]);
    }


    public function test_full_credit_quick_sale_allows_zero_paid_amount_and_creates_credit_record(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'subscription_end_at' => now()->addDays(30),
        ]);

        $store = Store::factory()->create([
            'user_id' => $owner->id,
            'status' => 'active',
        ]);

        $accountant = Accountant::create([
            'user_id' => $owner->id,
            'store_id' => $store->id,
            'employee_id' => null,
            'name' => 'Credit Sale Accountant',
            'email' => 'credit-sale@example.com',
            'phone' => '0500000004',
            'password' => 'password',
            'status' => 'active',
        ]);

        $employee = \App\Models\Employee::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'Credit Employee',
            'phone' => '0500000100',
            'email' => 'credit-employee@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'category_id' => null,
            'name' => 'Credit Sale Product',
            'slug' => 'credit-sale-product',
            'price' => 20,
            'cost_price' => 12,
            'quantity' => 10,
            'status' => 'active',
            'product_type' => 'standard',
            'roll_length' => 0,
            'is_splittable' => false,
            'items_per_unit' => 1,
            'piece_price' => 0,
            'min_stock' => 1,
        ]);

        $items = [[
            'product_id' => $product->id,
            'sale_unit' => 'unit',
            'quantity' => 1,
            'price' => 20,
            'total' => 20,
            'fraction_id' => '0',
        ]];

        $submitToken = (string) Str::uuid();
        $response = $this
            ->actingAs($accountant, 'accountant')
            ->withSession(['quick_sale_submit_token' => $submitToken])
            ->from(route('accountant.quick-sale.index'))
            ->post(route('accountant.quick-sale.submit'), [
                'items' => json_encode($items, JSON_THROW_ON_ERROR),
                'labor_total' => 0,
                'tax_rate' => 0,
                'paid_amount' => 0,
                'sale_type' => 'credit',
                'employee_id' => $employee->id,
                'description' => 'Full credit sale test',
                'has_invoice' => 0,
                'quick_sale_submit_token' => $submitToken,
            ]);

        $response->assertRedirect(route('accountant.quick-sale.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('sales', [
            'store_id' => $store->id,
            'sale_type' => 'credit',
            'employee_id' => $employee->id,
            'paid_amount' => 0,
            'remaining_amount' => 20,
        ]);
    }

    public function test_fractional_product_rejects_quantity_greater_than_one_in_same_sale_line(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'subscription_end_at' => now()->addDays(30),
        ]);

        $store = Store::factory()->create([
            'user_id' => $owner->id,
            'status' => 'active',
        ]);

        $accountant = Accountant::create([
            'user_id' => $owner->id,
            'store_id' => $store->id,
            'employee_id' => null,
            'name' => 'Fractional Quantity Accountant',
            'email' => 'fractional-quantity@example.com',
            'phone' => '0500000003',
            'password' => 'password',
            'status' => 'active',
        ]);

        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'category_id' => null,
            'name' => 'Tint Roll',
            'slug' => 'tint-roll',
            'price' => 100,
            'cost_price' => 50,
            'quantity' => 40,
            'status' => 'active',
            'product_type' => 'fractional',
            'roll_length' => 20,
            'is_splittable' => false,
            'items_per_unit' => 1,
            'piece_price' => 0,
            'min_stock' => 1,
        ]);

        $fraction = $product->fractions()->create([
            'option_label' => 'سيارة صغيرة',
            'deduction_value' => 0.5,
            'price' => 100,
        ]);

        $items = [[
            'product_id' => $product->id,
            'sale_unit' => 'unit',
            'quantity' => 2,
            'price' => 100,
            'total' => 200,
            'fraction_id' => (string) $fraction->id,
        ]];

        $submitToken = (string) Str::uuid();
        $response = $this
            ->actingAs($accountant, 'accountant')
            ->withSession(['quick_sale_submit_token' => $submitToken])
            ->from(route('accountant.quick-sale.index'))
            ->post(route('accountant.quick-sale.submit'), [
                'items' => json_encode($items, JSON_THROW_ON_ERROR),
                'labor_total' => 0,
                'tax_rate' => 0,
                'paid_amount' => 200,
                'sale_type' => 'cash',
                'description' => 'Fractional quantity validation',
                'has_invoice' => 0,
                'quick_sale_submit_token' => $submitToken,
            ]);

        $response->assertRedirect(route('accountant.quick-sale.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('sales', 0);
    }

}
