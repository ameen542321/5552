<?php

namespace Tests\Feature;

use App\Models\ArchivedItem;
use App\Models\Plan;
use App\Models\Purchase;
use App\Models\User;
use App\Models\SupportSession;
use App\Services\SupportSessionService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class OwnerPurchaseAdministrativeArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_purchase_force_delete_archives_financial_record_without_removing_it(): void
    {
        $plan = Plan::create([
            'name' => 'خطة اختبار مشتريات المالك',
            'allowed_stores' => 2,
            'allowed_accountants' => 2,
            'price' => 0,
        ]);
        $owner = User::create([
            'name' => 'مالك المشتريات',
            'email' => 'owner-purchase-archive@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'role' => User::ROLE_USER,
            'plan_id' => $plan->id,
            'welcome_shown' => true,
            'subscription_end_at' => now()->addYear(),
            'allowed_stores' => 2,
            'allowed_accountants' => 2,
        ]);
        $store = $owner->stores()->firstOrFail();
        $purchase = Purchase::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'purchase_name' => 'شراء أدوات للمالك',
            'quantity' => 1,
            'cost' => 125,
            'description' => 'عملية مالية يجب ألا تمحى',
            'business_date' => now()->toDateString(),
        ]);
        $purchase->delete();

        $this->actingAs($owner, 'web')->delete(route('user.stores.internal-use.trash.force-delete', [
            'store' => $store->id,
            'purchase' => $purchase->id,
        ]))->assertSessionHas('success');

        $this->assertNotNull(Purchase::withTrashed()->find($purchase->id));
        $this->assertDatabaseHas('archived_items', [
            'archivable_type' => Purchase::class,
            'archivable_id' => $purchase->id,
            'status' => 'archived',
        ]);
        $this->assertTrue(ArchivedItem::firstOrFail()->owner_restore_deadline->isSameDay(now()->addDays(30)));
    }

    public function test_technical_support_can_permanently_delete_archived_owner_purchase_from_trash(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $store = $owner->stores()->firstOrFail();
        $purchase = Purchase::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'purchase_name' => 'سجل يحذفه الدعم',
            'quantity' => 1,
            'cost' => 50,
            'business_date' => now()->toDateString(),
        ]);
        $purchase->delete();
        $archive = app(\App\Services\AdministrativeArchiveService::class)->archive(
            $purchase,
            $owner->id,
            $store->id,
            $purchase->purchase_name
        );
        $session = new SupportSession([
            'admin_id' => $admin->id,
            'target_type' => User::class,
            'target_id' => $owner->id,
            'target_role' => 'owner',
            'ticket_reference' => 'SUP-OWNER-PURCHASE',
        ]);
        $session->id = 7001;
        $this->mock(SupportSessionService::class)->shouldReceive('active')->andReturn($session);

        $this->actingAs($owner)->delete(route('user.stores.internal-use.trash.force-delete', [
            'store' => $store,
            'purchase' => $purchase->id,
        ]))->assertSessionHas('success');

        $this->assertNull(Purchase::withTrashed()->find($purchase->id));
        $this->assertSame('purged', $archive->fresh()->status);
    }
}
