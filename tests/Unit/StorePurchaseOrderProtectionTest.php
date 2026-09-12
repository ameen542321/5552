<?php

namespace Tests\Unit;

use App\Models\Store;
use App\Models\User;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Services\StorePurchaseOrderService;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StorePurchaseOrderProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_cannot_create_more_than_four_non_cancelled_orders_from_saturday_to_friday(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);

        foreach (range(1, 4) as $index) {
            StorePurchaseOrder::create([
                'store_id' => $store->id,
                'user_id' => $owner->id,
                'status' => 'draft',
                'workflow_status' => 'pending_owner_review',
            ]);
        }

        $service = new StorePurchaseOrderService();
        $payload = [
            'custom_items' => [[
                'custom_product_name' => 'منتج اختبار',
                'quantity_requested' => 1,
                'unit_type' => 'unit',
            ]],
            'items' => [],
        ];

        try {
            $service->createOrder($store, $owner, $payload);
            $this->fail('Expected the weekly purchase-order limit to reject the fifth order.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('الحد الأسبوعي', $exception->errors()['order'][0]);
        }

        StorePurchaseOrder::where('store_id', $store->id)->firstOrFail()->update(['status' => 'cancelled']);
        $created = $service->createOrder($store, $owner, $payload);

        $this->assertSame('draft', $created->status);
    }

    public function test_fourth_return_for_edit_cancels_the_entire_order(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'status' => 'draft',
            'workflow_status' => 'returned_after_edit',
            'edit_return_count' => 3,
        ]);

        $cancelled = (new StorePurchaseOrderService())->returnForInventoryCount(
            $order,
            $owner,
            'ما زالت الطلبية بحاجة إلى تعديل.',
            'edit'
        );

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('cancelled', $cancelled->workflow_status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertDatabaseHas('store_purchase_order_events', [
            'store_purchase_order_id' => $order->id,
            'event' => 'cancelled_after_edit_limit',
        ]);
    }

    public function test_mark_sent_atomically_saves_supplier_transition_and_event(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'status' => 'draft',
            'workflow_status' => 'pending_owner_review',
        ]);

        $sent = (new StorePurchaseOrderService())->markSent($order, $owner, [
            'supplier_name' => 'مورد الاختبار',
            'notes' => 'ملاحظة',
        ]);

        $this->assertSame('sent', $sent->status);
        $this->assertSame('pending_receipt_confirmation', $sent->workflow_status);
        $this->assertSame('مورد الاختبار', $sent->supplier_name);
        $this->assertDatabaseHas('store_purchase_order_events', [
            'store_purchase_order_id' => $order->id,
            'event' => 'sent_to_supplier',
        ]);
    }

    public function test_mark_sent_rechecks_the_locked_database_state(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $staleOrder = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'مورد الاختبار',
            'status' => 'draft',
            'workflow_status' => 'pending_owner_review',
        ]);
        StorePurchaseOrder::whereKey($staleOrder->id)->update([
            'status' => 'sent',
            'workflow_status' => 'pending_receipt_confirmation',
        ]);

        $this->expectException(ValidationException::class);

        (new StorePurchaseOrderService())->markSent($staleOrder, $owner);
    }

    public function test_mark_sent_does_not_save_supplier_when_inventory_review_blocks_transition(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'status' => 'draft',
            'workflow_status' => 'pending_owner_review',
            'inventory_review_status' => 'pending_owner_after_count',
        ]);

        try {
            (new StorePurchaseOrderService())->markSent($order, $owner, [
                'supplier_name' => 'مورد لا يجب حفظه',
            ]);
            $this->fail('Expected inventory review to block sending the purchase order.');
        } catch (ValidationException) {
            $this->assertNull($order->fresh()->supplier_name);
            $this->assertSame('draft', $order->fresh()->status);
            $this->assertDatabaseMissing('store_purchase_order_events', [
                'store_purchase_order_id' => $order->id,
                'event' => 'sent_to_supplier',
            ]);
        }
    }

    public function test_cancel_rechecks_the_locked_database_state(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $staleOrder = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'status' => 'draft',
            'workflow_status' => 'pending_owner_review',
        ]);
        StorePurchaseOrder::whereKey($staleOrder->id)->update([
            'status' => 'received',
            'workflow_status' => 'pending_owner_receipt_review',
        ]);

        $this->expectException(ValidationException::class);
        (new StorePurchaseOrderService())->cancel($staleOrder, $owner);
    }

    public function test_reject_rechecks_the_locked_database_state(): void
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $staleOrder = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'status' => 'draft',
            'workflow_status' => 'pending_owner_review',
        ]);
        StorePurchaseOrder::whereKey($staleOrder->id)->update([
            'status' => 'sent',
            'workflow_status' => 'pending_receipt_confirmation',
        ]);

        $this->expectException(ValidationException::class);
        (new StorePurchaseOrderService())->reject($staleOrder, $owner, 'حالة قديمة');
    }
}
