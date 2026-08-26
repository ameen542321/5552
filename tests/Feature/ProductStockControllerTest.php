<?php

namespace Tests\Feature;

use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProductStockControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_product_stock_page_with_movements(): void
    {
        [$owner, $store, $product] = $this->createOwnerStoreAndProduct();

        $product->increaseStock(3, 'Initial supply', $owner->id, 'unit');
        $movement = $product->fresh()->stockMovements()->latest('id')->firstOrFail();

        $response = $this
            ->actingAs($owner)
            ->get(route('user.stores.products.stock', [$store, $product]));

        $response->assertOk();
        $response->assertViewIs('user.stores.products.stock.index');
        $response->assertViewHas('store', fn ($viewStore) => $viewStore->is($store));
        $response->assertViewHas('product', fn ($viewProduct) => $viewProduct->is($product));
        $response->assertViewHas('movements', function ($movements) use ($movement) {
            return $movements->contains(fn ($item) => $item->is($movement));
        });
    }

    public function test_stock_page_explains_set_balance_and_default_sale_unit(): void
    {
        [$owner, $store, $product] = $this->createOwnerStoreAndProduct(quantity: 3);
        $product->update([
            'is_splittable' => true,
            'items_per_unit' => 6,
            'quick_sale_default_unit' => 'piece',
            'min_stock' => 1,
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('user.stores.products.stock', [$store, $product]));

        $response->assertOk();
        $response->assertSeeTextInOrder([
            'الكمية الحالية (حبة)',
            '18',
            'الطقم = 6 حبة',
        ]);
        $response->assertDontSeeText('إجمالي: 18 حبة');
        $response->assertSeeText('الحد الأدنى للمخزون');
        $response->assertSeeText('6 حبة');
        $response->assertSeeText('نوع البيع الافتراضي: حبة');
        $response->assertDontSeeText('يتم التنبيه عند الوصول لهذا الحد');
    }

    public function test_stock_routes_return_404_when_product_does_not_belong_to_store(): void
    {
        [$owner, $store] = $this->createOwnerStoreAndProduct();
        $otherStore = $owner->stores()->create([
            'name' => 'Secondary Store',
            'status' => 'active',
            'slug' => 'secondary-store-' . $owner->id,
        ]);

        $foreignProduct = Product::create([
            'store_id' => $otherStore->id,
            'user_id' => $owner->id,
            'category_id' => null,
            'name' => 'Foreign Product',
            'slug' => 'foreign-product',
            'price' => 50,
            'cost_price' => 25,
            'quantity' => 3,
            'status' => 'active',
            'product_type' => 'standard',
            'waste_percentage' => 0,
            'roll_length' => 0,
            'is_splittable' => false,
            'items_per_unit' => 1,
            'piece_price' => 0,
            'min_stock' => 1,
        ]);

        $this->actingAs($owner)
            ->get(route('user.stores.products.stock', [$store, $foreignProduct]))
            ->assertNotFound();
    }

    public function test_owner_can_increase_stock_and_record_movement(): void
    {
        [$owner, $store, $product] = $this->createOwnerStoreAndProduct(quantity: 10);

        $response = $this
            ->actingAs($owner)
            ->from(route('user.stores.products.stock', [$store, $product]))
            ->post(route('user.stores.products.stock.increase', [$store, $product]), [
                'quantity' => 4,
                'unit_type' => 'unit',
                'note' => 'Restock shipment',
                'business_date' => '2026-08-01',
            ]);

        $response->assertRedirect(route('user.stores.products.stock', [$store, $product]));
        $response->assertSessionHas('success', 'تمت زيادة المخزون بنجاح');

        $this->assertSame(14.0, (float) $product->fresh()->quantity);

        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'user_id' => $owner->id,
            'type' => 'increase',
            'quantity' => 4,
            'balance_before' => 10,
            'balance_after' => 14,
            'roll_length_at_movement' => null,
            'meters' => null,
            'note' => 'Restock shipment',
        ]);

        $movement = $product->stockMovements()->where('note', 'Restock shipment')->firstOrFail();
        $this->assertSame('2026-08-01', $movement->business_date->toDateString());
    }

    public function test_owner_can_confirm_inventory_audit_on_selected_date(): void
    {
        [$owner, $store, $product] = $this->createOwnerStoreAndProduct();

        $this->actingAs($owner)
            ->post(route('user.stores.products.stock.audit-confirm', [$store, $product]), [
                'business_date' => '2026-07-31',
                'audit_note' => 'جرد نهاية الشهر',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_logs', [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'type' => Product::INVENTORY_AUDIT_CONFIRMED_TYPE,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        $inventoryLog = InventoryLog::query()
            ->where('product_id', $product->id)
            ->where('type', Product::INVENTORY_AUDIT_CONFIRMED_TYPE)
            ->firstOrFail();
        $stockMovement = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('note', 'like', 'تأكيد جرد المنتج%')
            ->firstOrFail();

        $this->assertSame('2026-07-31', $inventoryLog->business_date->toDateString());
        $this->assertSame('2026-07-31', $stockMovement->business_date->toDateString());
    }

    public function test_inventory_audit_date_field_is_editable_for_owner(): void
    {
        [$owner, $store, $product] = $this->createOwnerStoreAndProduct();

        $this->actingAs($owner)
            ->get(route('user.stores.products.stock', [$store, $product]))
            ->assertOk()
            ->assertSee('name="business_date"', false)
            ->assertDontSee('readonly', false);
    }

    public function test_owner_can_cancel_the_current_inventory_audit_confirmation(): void
    {
        [$owner, $store, $product] = $this->createOwnerStoreAndProduct();

        $this->actingAs($owner)
            ->post(route('user.stores.products.stock.audit-confirm', [$store, $product]))
            ->assertSessionHas('success');

        $confirmation = $product->inventoryLogs()->latest('id')->firstOrFail();
        $movement = $product->stockMovements()->where('quantity', 0)->latest('id')->firstOrFail();

        $this->actingAs($owner)
            ->delete(route('user.stores.products.stock.audit-confirm.cancel', [$store, $product]))
            ->assertSessionHas('success', 'تم إلغاء تأكيد الجرد.');

        $this->assertSoftDeleted('inventory_logs', ['id' => $confirmation->id]);
        $this->assertDatabaseMissing('stock_movements', ['id' => $movement->id]);
    }

    public function test_piece_receipt_preserves_exact_set_fraction_and_displays_requested_pieces(): void
    {
        [$owner, $store, $product] = $this->createOwnerStoreAndProduct(quantity: 0);
        $product->update([
            'is_splittable' => true,
            'items_per_unit' => 24,
            'quick_sale_default_unit' => 'piece',
        ]);

        $this->actingAs($owner)
            ->post(route('user.stores.products.stock.increase', [$store, $product]), [
                'quantity' => 3,
                'unit_type' => 'piece',
                'note' => 'توريد ثلاث حبات',
            ])
            ->assertSessionHas('success');

        $this->assertEqualsWithDelta(0.125, (float) $product->fresh()->quantity, 0.000001);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity' => 0.125,
            'requested_quantity' => 3,
            'unit_type_at_movement' => 'piece',
        ]);

        $this->actingAs($owner)
            ->get(route('user.stores.products.stock', [$store, $product]))
            ->assertOk()
            ->assertSeeTextInOrder(['الكمية الحالية (حبة)', '3', 'الطقم = 24 حبة']);
    }

    public function test_movement_keeps_historical_kit_unit_after_product_changes_to_single_piece(): void
    {
        [$owner, $store, $product] = $this->createOwnerStoreAndProduct(quantity: 3);
        $product->update(['is_splittable' => true, 'items_per_unit' => 10]);

        \App\Models\StockMovement::recordForProduct(
            $product->fresh(),
            'decrease',
            1,
            3,
            2,
            $owner->id,
            'عملية بيع POS #999',
            1,
            'kit',
            now()->toDateString()
        );

        $product->update(['is_splittable' => false, 'items_per_unit' => 1]);

        $this->actingAs($owner)
            ->get(route('user.stores.products.stock', [$store, $product]))
            ->assertOk()
            ->assertSeeText('بيع (1 طقم) (POS #999)')
            ->assertSeeTextInOrder(['1', 'طقم', '3', 'طقم', '2', 'طقم']);
    }

    public function test_piece_withdrawal_above_available_stock_returns_visible_quantity_error(): void
    {
        [$owner, $store, $product] = $this->createOwnerStoreAndProduct(quantity: 0.125);
        $product->update([
            'is_splittable' => true,
            'items_per_unit' => 24,
            'quick_sale_default_unit' => 'piece',
        ]);

        $response = $this
            ->actingAs($owner)
            ->from(route('user.stores.products.stock', [$store, $product]))
            ->post(route('user.stores.products.stock.decrease', [$store, $product]), [
                'quantity' => 4,
                'unit_type' => 'piece',
            ]);

        $response->assertRedirect(route('user.stores.products.stock', [$store, $product]));
        $response->assertSessionHasErrors(['quantity' => 'الكمية المتوفرة لا تكفي']);
        $this->followRedirects($response)->assertSeeText('الكمية المتوفرة لا تكفي');
        $this->assertEqualsWithDelta(0.125, (float) $product->fresh()->quantity, 0.000001);
    }

    public function test_owner_cannot_decrease_stock_beyond_available_quantity(): void
    {
        [$owner, $store, $product] = $this->createOwnerStoreAndProduct(quantity: 5);

        $response = $this
            ->actingAs($owner)
            ->from(route('user.stores.products.stock', [$store, $product]))
            ->post(route('user.stores.products.stock.decrease', [$store, $product]), [
                'quantity' => 6,
                'unit_type' => 'unit',
                'note' => 'Oversell attempt',
            ]);

        $response->assertRedirect(route('user.stores.products.stock', [$store, $product]));
        $response->assertSessionHasErrors(['quantity' => 'الكمية المتوفرة لا تكفي']);

        $this->assertSame(5.0, (float) $product->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_owner_can_decrease_stock_and_record_movement(): void
    {
        [$owner, $store, $product] = $this->createOwnerStoreAndProduct(quantity: 9);

        $response = $this
            ->actingAs($owner)
            ->from(route('user.stores.products.stock', [$store, $product]))
            ->post(route('user.stores.products.stock.decrease', [$store, $product]), [
                'quantity' => 4,
                'unit_type' => 'unit',
                'note' => 'Manual adjustment',
            ]);

        $response->assertRedirect(route('user.stores.products.stock', [$store, $product]));
        $response->assertSessionHas('success', 'تم خصم الكمية من المخزن بنجاح');

        $this->assertSame(5.0, (float) $product->fresh()->quantity);

        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'user_id' => $owner->id,
            'type' => 'decrease',
            'quantity' => 4,
            'balance_before' => 9,
            'balance_after' => 5,
            'roll_length_at_movement' => null,
            'meters' => null,
            'note' => 'Manual adjustment',
        ]);
    }

    private function createOwnerStoreAndProduct(float $quantity = 10): array
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);

        $store = $owner->stores()->firstOrFail();
        $store->update(['status' => 'active']);

        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'category_id' => null,
            'name' => 'Stock Test Product',
            'slug' => 'stock-test-product',
            'price' => 100,
            'cost_price' => 60,
            'quantity' => $quantity,
            'status' => 'active',
            'product_type' => 'standard',
            'waste_percentage' => 0,
            'roll_length' => 0,
            'is_splittable' => false,
            'items_per_unit' => 1,
            'piece_price' => 0,
            'min_stock' => 1,
        ]);

        return [$owner, $store, $product];
    }
}
