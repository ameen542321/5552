<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderItem;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderOwnerProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_missing_owner_purchase_item_as_a_non_sellable_product(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);
        $store = $owner->stores()->firstOrFail();
        $store->update(['status' => 'active']);
        $category = Category::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'مشتريات المالك',
            'status' => 'active',
        ]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'مورد الاختبار',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'custom_product_name' => 'طقم ضيافة المالك',
            'quantity_requested' => 2,
            'unit_type' => 'kit',
            'items_per_unit' => 6,
            'cost_price_at_order' => 100,
            'add_to_owner_purchases' => true,
        ]);

        $response = $this->actingAs($owner)->postJson(
            route('user.stores.purchase-orders.items.owner-product.store', [$store, $order, $item]),
            [
                'name' => 'طقم ضيافة المالك',
                'category_id' => $category->id,
                'owner_unit_type' => 'kit',
                'receipt_total_cost' => 120,
                'received_quantity' => 2,
                'items_per_unit' => 12,
                'usage_type' => Product::USAGE_TYPE_OWNER_PURCHASE,
                'selling_price' => 80,
                'piece_price' => 8,
                'min_stock' => 4,
                'barcode' => 'OWNER-KIT-1',
                'carton_qty' => 24,
                'waste_percentage' => 3,
            ]
        );

        $response->assertCreated();
        $product = Product::where('name', 'طقم ضيافة المالك')->firstOrFail();
        $this->assertSame(Product::USAGE_TYPE_OWNER_PURCHASE, $product->usage_type);
        $this->assertSame(0.0, (float) $product->quantity);
        $this->assertSame(4.0, (float) $product->min_stock);
        $this->assertSame(80.0, (float) $product->price);
        $this->assertSame(8.0, (float) $product->piece_price);
        $this->assertSame(24, (int) $product->carton_qty);
        $this->assertSame('OWNER-KIT-1', $product->barcode);
        $this->assertSame(0.0, (float) $product->waste_percentage);
        $this->assertSame(60.0, (float) $product->cost_price);
        $this->assertTrue((bool) $product->is_splittable);
        $this->assertSame(12, (int) $product->items_per_unit);
        $this->assertSame(5.0, round((float) $product->cost_price / (int) $product->items_per_unit, 2));
        $this->assertFalse(Product::sellable()->whereKey($product->id)->exists());
        $this->assertDatabaseHas('store_purchase_order_items', [
            'id' => $item->id,
            'product_id' => $product->id,
            'add_to_owner_purchases' => true,
        ]);
    }

    public function test_owner_can_save_item_as_a_sale_product_after_receipt_review(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);
        $store = $owner->stores()->firstOrFail();
        $store->update(['status' => 'active']);
        $category = Category::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتجات البيع',
            'status' => 'active',
        ]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'مورد الاختبار',
            'status' => 'received',
            'workflow_status' => 'pending_owner_receipt_review',
            'received_at' => now(),
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'custom_product_name' => 'منتج بيع جديد',
            'quantity_requested' => 3,
            'quantity_received' => 3,
            'unit_type' => 'piece',
            'cost_price_at_receipt' => 90,
            'add_to_owner_purchases' => true,
        ]);

        $response = $this->actingAs($owner)->postJson(
            route('user.stores.purchase-orders.items.owner-product.store', [$store, $order, $item]),
            [
                'name' => 'منتج بيع جديد',
                'category_id' => $category->id,
                'owner_unit_type' => 'piece',
                'receipt_total_cost' => 90,
                'received_quantity' => 3,
                'usage_type' => Product::USAGE_TYPE_SALE,
                'selling_price' => 45,
                'min_stock' => 2,
                'carton_qty' => 12,
                'waste_percentage' => 3.5,
                'quick_sale_default_unit' => 'piece',
            ]
        );

        $response->assertCreated();
        $product = Product::where('name', 'منتج بيع جديد')->firstOrFail();
        $this->assertSame(Product::USAGE_TYPE_SALE, $product->usage_type);
        $this->assertSame(45.0, (float) $product->price);
        $this->assertSame(2.0, (float) $product->min_stock);
        $this->assertSame(12, (int) $product->carton_qty);
        $this->assertSame(0.0, (float) $product->waste_percentage);
        $this->assertTrue(Product::sellable()->whereKey($product->id)->exists());
        $this->assertDatabaseHas('store_purchase_order_items', [
            'id' => $item->id,
            'product_id' => $product->id,
            'add_to_owner_purchases' => false,
        ]);
    }

    public function test_owner_can_link_an_existing_store_product_from_the_single_product_dialog(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);
        $store = $owner->stores()->firstOrFail();
        $store->update(['status' => 'active']);
        $category = Category::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتجات البيع',
            'status' => 'active',
        ]);
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'name' => 'منتج موجود',
            'slug' => 'existing-product-'.$store->id,
            'price' => 20,
            'cost_price' => 10,
            'quantity' => 5,
            'min_stock' => 1,
            'status' => 'active',
            'product_type' => 'standard',
            'usage_type' => Product::USAGE_TYPE_SALE,
        ]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'مورد الاختبار',
            'status' => 'received',
            'workflow_status' => 'pending_owner_receipt_review',
            'received_at' => now(),
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'custom_product_name' => 'بند غير مربوط',
            'quantity_requested' => 2,
            'quantity_received' => 2,
            'unit_type' => 'piece',
            'cost_price_at_receipt' => 20,
        ]);

        $response = $this->actingAs($owner)->postJson(
            route('user.stores.purchase-orders.items.owner-product.store', [$store, $order, $item]),
            [
                'existing_product_id' => $product->id,
                'owner_unit_type' => 'piece',
                'usage_type' => Product::USAGE_TYPE_SALE,
            ]
        );

        $response->assertOk()->assertJsonPath('product.id', $product->id);
        $this->assertDatabaseHas('store_purchase_order_items', [
            'id' => $item->id,
            'product_id' => $product->id,
            'matched_product_id' => null,
            'add_to_owner_purchases' => false,
        ]);
    }

    public function test_owner_can_create_a_complete_roll_product_with_meter_sale_options(): void
    {
        $owner = User::factory()->create([
            'role' => 'user', 'status' => 'active', 'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);
        $store = $owner->stores()->firstOrFail();
        $store->update(['status' => 'active']);
        $category = Category::create([
            'store_id' => $store->id, 'user_id' => $owner->id,
            'name' => 'منتجات الرول', 'status' => 'active',
        ]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id, 'user_id' => $owner->id,
            'status' => 'received', 'workflow_status' => 'pending_owner_receipt_review',
            'received_at' => now(),
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'custom_product_name' => 'رول جديد كامل',
            'quantity_requested' => 2,
            'quantity_received' => 2,
            'unit_type' => 'roll',
            'cost_price_at_receipt' => 600,
        ]);

        $response = $this->actingAs($owner)->postJson(
            route('user.stores.purchase-orders.items.owner-product.store', [$store, $order, $item]),
            [
                'product_action' => 'create',
                'name' => 'رول جديد كامل',
                'category_id' => $category->id,
                'owner_unit_type' => 'roll',
                'receipt_total_cost' => 600,
                'received_quantity' => 2,
                'roll_length' => 30,
                'description' => 'رول يباع كاملًا أو بالمتر',
                'usage_type' => Product::USAGE_TYPE_SALE,
                'selling_price' => 450,
                'min_stock' => 3,
                'barcode' => 'ROLL-NEW-1',
                'carton_qty' => 4,
                'waste_percentage' => 5,
                'status' => 'active',
                'fractions' => [
                    ['option_label' => 'متر', 'deduction_value' => 1, 'price' => 20],
                    ['option_label' => 'نصف متر', 'deduction_value' => 0.5, 'price' => 12],
                ],
            ]
        );

        $response->assertCreated();
        $product = Product::where('name', 'رول جديد كامل')->firstOrFail();
        $this->assertSame('fractional', $product->product_type);
        $this->assertSame(30.0, (float) $product->roll_length);
        $this->assertSame(300.0, (float) $product->cost_price);
        $this->assertSame(0.0, (float) $product->quantity);
        $this->assertNull($product->carton_qty);
        $this->assertSame(5.0, (float) $product->waste_percentage);
        $this->assertSame(2, $product->fractions()->count());
        $this->assertDatabaseHas('product_fractions', [
            'product_id' => $product->id,
            'option_label' => 'نصف متر',
            'deduction_value' => 0.5,
            'price' => 12,
        ]);
    }
}
