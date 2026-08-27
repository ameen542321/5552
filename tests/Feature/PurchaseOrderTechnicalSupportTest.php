<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\SupportSession;
use App\Models\User;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderItem;
use App\Services\SupportSessionService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTechnicalSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_can_correct_order_status_and_the_change_is_audited(): void
    {
        [$owner, $store, $order] = $this->orderFixture();
        $this->fakeOwnerSupportSession($owner);

        $response = $this->actingAs($owner)->patch(
            route('user.stores.purchase-orders.support-status', [$store, $order]),
            ['workflow_status' => 'pending_owner_receipt_review', 'support_note' => 'تصحيح حالة عالقة']
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('store_purchase_orders', [
            'id' => $order->id,
            'status' => 'received',
            'workflow_status' => 'pending_owner_receipt_review',
        ]);
        $this->assertDatabaseHas('store_purchase_order_events', [
            'store_purchase_order_id' => $order->id,
            'event' => 'support_status_corrected',
            'actor_type' => 'support',
            'actor_id' => 9001,
        ]);
    }

    public function test_support_purge_removes_order_files_but_preserves_supplied_product(): void
    {
        [$owner, $store, $order] = $this->orderFixture();
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'منتج مورد',
            'slug' => 'supplied-product-'.$store->id,
            'price' => 20,
            'cost_price' => 10,
            'quantity' => 8,
            'min_stock' => 1,
            'status' => 'active',
            'product_type' => 'standard',
            'usage_type' => Product::USAGE_TYPE_SALE,
        ]);
        $item = StorePurchaseOrderItem::create([
            'store_purchase_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity_requested' => 2,
            'quantity_received' => 2,
            'unit_type' => 'unit',
            'cost_price_at_order' => 20,
            'cost_price_at_receipt' => 20,
        ]);
        $order->events()->create([
            'event' => 'created',
            'to_status' => 'pending_owner_review',
            'actor_type' => 'user',
            'actor_id' => $owner->id,
        ]);
        $this->fakeOwnerSupportSession($owner);

        $response = $this->actingAs($owner)->delete(
            route('user.stores.purchase-orders.support-purge', [$store, $order]),
            ['confirmation' => $order->referenceCode(), 'support_note' => 'حذف سجل تجريبي']
        );

        $response->assertRedirect(route('user.stores.purchase-orders.index', $store));
        $this->assertDatabaseMissing('store_purchase_orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('store_purchase_order_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('store_purchase_order_events', ['store_purchase_order_id' => $order->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 8]);
    }

    public function test_support_sees_permanent_delete_for_an_order_in_any_active_stage(): void
    {
        [$owner, $store] = $this->orderFixture();
        $this->fakeOwnerSupportSession($owner);

        $this->actingAs($owner)
            ->get(route('user.stores.purchase-orders.index', $store))
            ->assertOk()
            ->assertSee('حذف نهائي');
    }

    public function test_owner_without_support_session_cannot_use_support_tools(): void
    {
        [$owner, $store, $order] = $this->orderFixture();

        $this->actingAs($owner)->patch(
            route('user.stores.purchase-orders.support-status', [$store, $order]),
            ['workflow_status' => 'cancelled', 'support_note' => 'محاولة غير مصرحة']
        )->assertForbidden();
    }

    public function test_status_correction_requires_an_explicit_administrative_reason(): void
    {
        [$owner, $store, $order] = $this->orderFixture();
        $this->fakeOwnerSupportSession($owner);

        $this->actingAs($owner)->from(route('user.stores.purchase-orders.show', [$store, $order]))->patch(
            route('user.stores.purchase-orders.support-status', [$store, $order]),
            ['workflow_status' => 'returned_for_edit', 'support_note' => '']
        )->assertSessionHasErrors('support_note');

        $this->assertDatabaseMissing('store_purchase_order_events', ['store_purchase_order_id' => $order->id, 'event' => 'support_status_corrected']);
    }

    public function test_received_and_approved_orders_cannot_be_permanently_deleted(): void
    {
        [$owner, $store, $order] = $this->orderFixture();
        $this->fakeOwnerSupportSession($owner);

        foreach (['received', 'approved'] as $status) {
            $order->update(['status' => $status, 'workflow_status' => $status === 'approved' ? 'approved_and_supplied' : 'pending_inventory_approval']);
            $this->actingAs($owner)->delete(
                route('user.stores.purchase-orders.support-purge', [$store, $order]),
                ['confirmation' => $order->referenceCode(), 'support_note' => 'منع حذف السجل المستلم أو المعتمد']
            )->assertSessionHasErrors('order');
            $this->assertDatabaseHas('store_purchase_orders', ['id' => $order->id]);
        }
    }

    public function test_support_can_fix_an_incorrect_approved_label_when_inventory_approval_never_ran(): void
    {
        [$owner, $store, $order] = $this->orderFixture();
        $order->update(['status' => 'approved', 'workflow_status' => 'approved_and_supplied']);
        $this->fakeOwnerSupportSession($owner);

        $this->actingAs($owner)->patch(
            route('user.stores.purchase-orders.support-status', [$store, $order]),
            ['workflow_status' => 'pending_owner_receipt_review', 'support_note' => 'تصحيح تسمية خاطئة']
        )->assertRedirect();

        $this->assertDatabaseHas('store_purchase_orders', [
            'id' => $order->id,
            'status' => 'received',
            'workflow_status' => 'pending_owner_receipt_review',
        ]);
    }

    public function test_restore_button_endpoint_is_limited_to_support_and_restores_deleted_order(): void
    {
        [$owner, $store, $order] = $this->orderFixture();
        $order->delete();
        $this->fakeOwnerSupportSession($owner);

        $this->actingAs($owner)
            ->get(route('user.stores.purchase-orders.index', $store))
            ->assertOk()
            ->assertSee('استعادة الطلبية');

        $this->actingAs($owner)->patch(
            route('user.stores.purchase-orders.support-restore', [$store, $order->id]),
            ['support_note' => 'استعادة الطلبية بعد مراجعة سبب الحذف']
        )->assertRedirect();

        $this->assertDatabaseHas('store_purchase_orders', ['id' => $order->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('store_purchase_order_events', [
            'store_purchase_order_id' => $order->id,
            'event' => 'support_restored',
            'actor_type' => 'support',
        ]);
    }

    private function fakeOwnerSupportSession(User $owner): void
    {
        $session = new SupportSession([
            'admin_id' => 9001,
            'target_type' => User::class,
            'target_id' => $owner->id,
            'target_role' => 'owner',
            'ticket_reference' => 'SUP-TEST-1',
        ]);
        $session->id = 7001;
        $this->mock(SupportSessionService::class)
            ->shouldReceive('active')
            ->andReturn($session);
    }

    private function orderFixture(): array
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);
        $store = Store::factory()->create(['user_id' => $owner->id, 'status' => 'active']);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'المورد',
            'status' => 'draft',
            'workflow_status' => 'pending_owner_review',
        ]);

        return [$owner, $store, $order];
    }
}
