<?php

namespace Tests\Unit;

use App\Models\Purchase;
use App\Models\Product;
use App\Models\Store;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderItem;
use App\Models\User;
use App\Modules\PurchaseOrders\Services\StorePurchaseOrderService;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StorePurchaseOrderServiceOwnerPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_receive_uses_saved_order_price_for_owner_purchase_lines(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);

        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'المورد',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'custom_product_name' => 'مشتريات خاصة',
            'quantity_requested' => 2,
            'quantity_received' => 2,
            'unit_type' => 'unit',
            'cost_price_at_order' => 20,
            'add_to_owner_purchases' => true,
        ]);

        $receivedOrder = (new StorePurchaseOrderService())->receive($order, $owner, [
            $item->id => [
                'quantity_received' => 2,
                'cost_price_at_receipt' => null,
                'unit_type' => 'unit',
            ],
        ]);

        $this->assertSame('received', $receivedOrder->status);
        $this->assertSame(20.0, (float) $item->fresh()->cost_price_at_receipt);
    }

    public function test_receive_requires_price_when_owner_purchase_has_no_saved_price(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'المورد',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'custom_product_name' => 'مشتريات بلا سعر',
            'quantity_requested' => 2,
            'unit_type' => 'unit',
            'cost_price_at_order' => 0,
            'add_to_owner_purchases' => true,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('سعر الاستلام إلزامي');

        (new StorePurchaseOrderService())->receive($order, $owner, [
            $item->id => ['quantity_received' => 2, 'cost_price_at_receipt' => null, 'unit_type' => 'unit'],
        ]);
    }

    public function test_approve_creates_owner_purchase_for_custom_item_without_stock_change(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);

        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'المورد',
            'status' => 'received',
            'received_at' => now(),
        ]);

        StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'custom_product_name' => 'مشتريات خاصة',
            'quantity_requested' => 3,
            'quantity_received' => 3,
            'unit_type' => 'unit',
            'cost_price_at_order' => 45,
            'cost_price_at_receipt' => 60,
            'add_to_owner_purchases' => true,
        ]);

        $service = new StorePurchaseOrderService();
        $approvedOrder = $service->approve($order, $owner, '2026-08-01');

        $this->assertSame('approved', $approvedOrder->status);
        $this->assertSame('2026-08-01', $approvedOrder->approved_business_date?->format('Y-m-d'));
        $this->assertSame(1, Purchase::count());
        $this->assertDatabaseHas('purchases', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'purchase_name' => 'مشتريات خاصة',
            'quantity' => 3,
            'cost' => 60.0,
            'business_date' => '2026-08-01',
        ]);
    }

    public function test_owner_purchase_product_receives_with_explicit_quantity_without_review_requirements(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتج مشتريات مالك',
            'price' => 0,
            'cost_price' => 0,
            'quantity' => 0,
            'status' => 'active',
            'usage_type' => Product::USAGE_TYPE_OWNER_PURCHASE,
        ]);

        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'المورد',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity_requested' => 3,
            'unit_type' => 'unit',
            'cost_price_at_order' => 0,
            'add_to_owner_purchases' => true,
        ]);

        $service = new StorePurchaseOrderService();
        $receivedOrder = $service->receive($order, $owner, [
            $item->id => [
                'quantity_received' => 3,
                'cost_price_at_receipt' => null,
                'unit_type' => 'unit',
            ],
        ]);

        $this->assertSame('received', $receivedOrder->status);
        $this->assertDatabaseHas('store_purchase_order_items', [
            'id' => $item->id,
            'quantity_received' => 3,
        ]);

        $approvedOrder = $service->approve($receivedOrder, $owner, '2026-08-01');

        $this->assertSame('approved', $approvedOrder->status);
        $this->assertDatabaseHas('purchases', [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'purchase_name' => 'منتج مشتريات مالك',
            'quantity' => 3,
            'business_date' => '2026-08-01',
        ]);
        $this->assertSame(0.0, (float) $product->fresh()->quantity);
    }

    public function test_receive_accepts_explicit_zero_for_an_item_that_did_not_arrive(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'المورد',
            'status' => 'sent',
            'workflow_status' => 'pending_receipt_confirmation',
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'custom_product_name' => 'بند لم يصل',
            'quantity_requested' => 2,
            'unit_type' => 'unit',
            'cost_price_at_order' => 20,
            'add_to_owner_purchases' => true,
        ]);

        $received = (new StorePurchaseOrderService())->receive($order, $owner, [
            $item->id => ['quantity_received' => 0, 'cost_price_at_receipt' => 0, 'unit_type' => 'unit'],
        ]);

        $this->assertSame('received', $received->status);
        $this->assertSame('pending_inventory_approval', $received->workflow_status);
        $this->assertSame(0.0, $item->fresh()->quantity_received);
    }

    public function test_receive_uses_requested_quantity_when_received_quantity_is_blank(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'المورد',
            'status' => 'sent',
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'custom_product_name' => 'بند',
            'quantity_requested' => 2,
            'unit_type' => 'unit',
            'cost_price_at_order' => 20,
            'add_to_owner_purchases' => true,
        ]);

        (new StorePurchaseOrderService())->receive($order, $owner, [
            $item->id => ['cost_price_at_receipt' => 20, 'unit_type' => 'unit'],
        ]);

        $this->assertSame(2.0, (float) $item->fresh()->quantity_received);
    }

    public function test_approve_saves_stock_and_cost_at_the_moment_owner_approves(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتج مخزني',
            'slug' => 'approval-snapshot-'.$store->id,
            'price' => 15,
            'cost_price' => 9,
            'quantity' => 17,
            'min_stock' => 1,
            'status' => 'active',
            'product_type' => 'standard',
            'usage_type' => Product::USAGE_TYPE_SALE,
        ]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'المورد',
            'status' => 'received',
            'workflow_status' => 'pending_inventory_approval',
            'received_at' => now(),
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity_requested' => 3,
            'quantity_received' => 3,
            'unit_type' => 'unit',
            'cost_price_at_order' => 27,
            'cost_price_at_receipt' => 27,
        ]);

        (new StorePurchaseOrderService())->approve($order, $owner, '2026-08-08');

        $item->refresh();
        $this->assertSame(17.0, (float) $item->stock_quantity_before);
        $this->assertSame(20.0, (float) $item->stock_quantity_after);
        $this->assertSame(9.0, (float) $item->cost_price_before);
        $this->assertSame(9.0, (float) $item->cost_price_after);
    }

    public function test_complete_supplier_receipt_review_and_inventory_approval_workflow(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتج دورة كاملة',
            'slug' => 'complete-workflow-'.$store->id,
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
            'supplier_name' => 'مورد الدورة الكاملة',
            'status' => 'draft',
            'workflow_status' => 'pending_owner_review',
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity_requested' => 3,
            'unit_type' => 'unit',
            'cost_price_at_order' => 30,
        ]);
        $service = new StorePurchaseOrderService();

        $sent = $service->markSent($order, $owner);
        $this->assertSame('pending_receipt_confirmation', $sent->workflow_status);
        $this->assertSame(5.0, (float) $product->fresh()->quantity);

        $confirmed = $service->receive($sent, $owner, [
            $item->id => ['quantity_received' => 3, 'unit_type' => 'unit', 'cost_price_at_receipt' => 30],
        ], 'accountant', 77);
        $this->assertSame('pending_owner_receipt_review', $confirmed->workflow_status);
        $this->assertSame(5.0, (float) $product->fresh()->quantity);

        $reviewed = $service->receive($confirmed, $owner, [
            $item->id => ['quantity_received' => 3, 'unit_type' => 'unit', 'cost_price_at_receipt' => 30],
        ], 'user', $owner->id);
        $this->assertSame('pending_inventory_approval', $reviewed->workflow_status);
        $this->assertSame(5.0, (float) $product->fresh()->quantity);

        $approved = $service->approve($reviewed, $owner, '2026-08-08');
        $this->assertSame('approved', $approved->status);
        $this->assertSame('approved_and_supplied', $approved->workflow_status);
        $this->assertSame(8.0, (float) $product->fresh()->quantity);
        $this->assertSame(1, $approved->events()->where('event', 'sent_to_supplier')->count());
        $this->assertSame(1, $approved->events()->where('event', 'receipt_confirmed')->count());
        $this->assertSame(1, $approved->events()->where('event', 'receipt_review_updated')->count());
        $this->assertSame(1, $approved->events()->where('event', 'inventory_approved')->count());
    }

}
