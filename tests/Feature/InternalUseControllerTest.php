<?php

namespace Tests\Feature;

use App\Models\Accountant;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class InternalUseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_purchase_create_and_update_use_explicit_business_date(): void
    {
        $owner = User::factory()->create([
            'status' => 'active',
            'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);
        $store = Store::factory()->create(['user_id' => $owner->id, 'status' => 'active']);
        $firstDate = now()->subDay()->toDateString();
        $secondDate = now()->toDateString();

        $this->actingAs($owner)->post(route('user.stores.internal-use.add-consumption.store', $store), [
            'type' => 'أداة للمالك',
            'amount' => 25,
            'description' => 'لا تخصم من المخزون',
            'business_date' => $firstDate,
        ])->assertRedirect();

        $purchase = Purchase::where('store_id', $store->id)->latest('id')->firstOrFail();
        $this->assertSame($firstDate, $purchase->business_date->toDateString());

        $this->actingAs($owner)->put(route('user.stores.internal-use.add-consumption.update', [$store, $purchase]), [
            'type' => 'أداة محدثة',
            'amount' => 30,
            'description' => 'تعديل يوم العملية المالية',
            'business_date' => $secondDate,
        ])->assertRedirect();

        $this->assertSame($secondDate, $purchase->fresh()->business_date->toDateString());
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_owner_purchase_page_uses_project_modals_and_help_content(): void
    {
        $owner = User::factory()->create([
            'status' => 'active',
            'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);
        $store = Store::factory()->create(['user_id' => $owner->id, 'status' => 'active']);

        $this->actingAs($owner)
            ->get(route('user.stores.internal-use.report.view', $store))
            ->assertOk()
            ->assertSee('data-owner-purchase-create-modal', false)
            ->assertSee('data-owner-purchase-edit-modal', false)
            ->assertSee('مشتريات المالك واستهلاك المحاسب منفصلان عن المصاريف المالية')
            ->assertDontSee('(بدون خصم مخزون)');
    }

    public function test_accountant_internal_use_piece_flow_preserves_unit_type_and_normalized_stock_deduction(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);

        $store = Store::factory()->create([
            'user_id' => $owner->id,
            'status' => 'active',
        ]);

        $employee = Employee::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'Internal use employee',
            'phone' => '0500000022',
            'salary' => 1000,
            'status' => 'active',
        ]);

        $accountant = Accountant::create([
            'user_id' => $owner->id,
            'store_id' => $store->id,
            'employee_id' => $employee->id,
            'name' => 'Internal Use Accountant',
            'email' => 'internal-use@example.com',
            'phone' => '0500000001',
            'password' => 'password',
            'status' => 'active',
        ]);

        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'category_id' => null,
            'name' => 'Splittable Kit',
            'slug' => 'splittable-kit',
            'price' => 100,
            'cost_price' => 70,
            'quantity' => 10,
            'status' => 'active',
            'product_type' => 'standard',
            'waste_percentage' => 0,
            'roll_length' => 0,
            'is_splittable' => true,
            'items_per_unit' => 4,
            'piece_price' => 30,
            'min_stock' => 1,
        ]);

        $response = $this
            ->actingAs($accountant, 'accountant')
            ->from(route('accountant.internal-use.create'))
            ->post(route('accountant.internal-use.store'), [
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_type' => 'piece',
                'reason' => 'Internal consumption',
                'internal_notes' => 'Two pieces used',
            ]);

        $response->assertRedirect(route('accountant.internal-use.create'));
        $response->assertSessionHas('success');

        $this->assertSame(9.5, (float) $product->fresh()->quantity);

        $sale = Sale::query()->firstOrFail();
        $saleItem = SaleItem::query()->firstOrFail();

        $this->assertSame('internal_use', $sale->sale_type);
        $this->assertSame(35.0, (float) $sale->total);
        $this->assertSame(17.5, (float) $saleItem->price);
        $this->assertSame(35.0, (float) $saleItem->total);
        $this->assertSame(2.0, (float) $saleItem->quantity);
        $this->assertSame('piece', $saleItem->unit_type);

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
}
