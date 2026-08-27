<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Modules\PurchaseOrders\Models\PurchaseOrderLimitSetting;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class AdminPurchaseOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_all_store_orders_and_open_integrity_check(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'user']);
        $store = Store::factory()->create(['user_id' => $owner->id, 'name' => 'متجر المتابعة']);
        $order = StorePurchaseOrder::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'supplier_name' => 'مورد المتابعة',
            'status' => 'approved',
            'workflow_status' => 'pending_receipt_confirmation',
        ]);
        $order->forceFill(['created_at' => now()->subDays(4), 'sent_at' => now()->subDays(4)])->saveQuietly();

        $this->actingAs($admin)->get(route('admin.purchase-orders.index', ['store_id' => $store->id]))
            ->assertOk()
            ->assertSee($order->referenceCode())
            ->assertSee('متجر المتابعة')
            ->assertSee('مشكلة')
            ->assertSee('متأخرة');
    }

    public function test_admin_can_set_default_and_temporary_store_limits_with_required_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'user']);
        $store = Store::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($admin)->patch(route('admin.purchase-orders.limits.global'), [
            'weekly_limit' => 6,
            'counted_statuses' => ['draft', 'sent', 'received'],
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.purchase-orders.limits.store'), [
            'store_id' => $store->id,
            'weekly_limit' => 8,
            'counted_statuses' => ['draft', 'sent'],
            'exception_weekly_limit' => 12,
            'exception_expires_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'exception_reason' => 'استثناء مؤقت لموسم مرتفع الطلبات',
        ])->assertRedirect();

        $setting = PurchaseOrderLimitSetting::where('store_id', $store->id)->firstOrFail();
        $this->assertSame(12, $setting->effectiveWeeklyLimit());
        $this->assertSame(['draft', 'sent'], $setting->effectiveCountedStatuses());
        $this->assertSame($admin->id, $setting->exception_admin_id);
    }

    public function test_store_exception_is_rejected_without_an_administrative_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'user']);
        $store = Store::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($admin)->from(route('admin.purchase-orders.index'))->patch(route('admin.purchase-orders.limits.store'), [
            'store_id' => $store->id,
            'weekly_limit' => 8,
            'counted_statuses' => ['draft'],
            'exception_weekly_limit' => 12,
            'exception_expires_at' => now()->addWeek()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('exception_reason');
    }
}
