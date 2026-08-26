<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderItem;
use App\Modules\PurchaseOrders\Services\StorePurchaseOrderService;
use Carbon\Carbon;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderReceiptAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_owner_review_does_not_replace_accountant_confirmation_date_or_actor(): void
    {
        $owner = User::factory()->create();
        $store = $owner->stores()->firstOrFail();
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'status' => 'sent',
            'workflow_status' => 'pending_receipt_confirmation',
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'custom_product_name' => 'مشتريات اختبار',
            'quantity_requested' => 2,
            'unit_type' => 'piece',
            'cost_price_at_order' => 20,
            'add_to_owner_purchases' => true,
        ]);
        $service = new StorePurchaseOrderService();

        Carbon::setTestNow('2026-08-08 10:00:00');
        $confirmed = $service->receive($order, $owner, [
            $item->id => ['quantity_received' => 3, 'unit_type' => 'piece', 'receipt_notes' => 'زاد المحاسب الكمية'],
        ], 'accountant', 77);
        $this->assertSame('pending_owner_receipt_review', $confirmed->workflow_status);
        $receiptEvent = $confirmed->events()->where('event', 'receipt_confirmed')->firstOrFail();
        $this->assertSame(['quantity', 'note'], data_get($receiptEvent->data, "item_changes.{$item->id}.fields"));

        Carbon::setTestNow('2026-08-08 12:00:00');
        $reviewed = $service->receive($confirmed, $owner, [
            $item->id => ['quantity_received' => 1, 'unit_type' => 'piece'],
        ], 'user', $owner->id);

        $this->assertSame('2026-08-08 10:00:00', $reviewed->received_at?->format('Y-m-d H:i:s'));
        $this->assertSame('accountant', $reviewed->receipt_actor_type);
        $this->assertSame(77, (int) $reviewed->receipt_actor_id);
        $this->assertSame('pending_inventory_approval', $reviewed->workflow_status);
        $this->assertDatabaseHas('store_purchase_order_events', [
            'store_purchase_order_id' => $order->id,
            'event' => 'receipt_review_updated',
            'actor_type' => 'user',
        ]);
    }
}
