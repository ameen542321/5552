<?php

namespace Tests\Feature;

use App\Models\ArchivedItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class AdminSupportArchiveIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_review_and_filter_expired_archive_items(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $store = Store::factory()->create(['user_id' => $owner->id, 'name' => 'متجر الأرشيف']);
        $expired = ArchivedItem::create([
            'owner_id' => $owner->id,
            'store_id' => $store->id,
            'archivable_type' => Product::class,
            'archivable_id' => 901,
            'original_name' => 'منتج منتهي المهلة',
            'reference' => 'ARC-EXPIRED-901',
            'status' => 'archived',
            'archived_at' => now()->subDays(40),
            'owner_restore_deadline' => now()->subDays(10),
        ]);
        ArchivedItem::create([
            'owner_id' => $owner->id,
            'store_id' => $store->id,
            'archivable_type' => Product::class,
            'archivable_id' => 902,
            'original_name' => 'منتج داخل المهلة',
            'reference' => 'ARC-ACTIVE-902',
            'status' => 'archived',
            'archived_at' => now(),
            'owner_restore_deadline' => now()->addDays(20),
        ]);

        $response = $this->actingAs($admin, 'web')->get(route('admin.support.archive.index', [
            'deadline' => 'expired',
        ]));

        $response->assertOk();
        $response->assertSee($expired->reference);
        $response->assertSee('منتج منتهي المهلة');
        $response->assertSee($owner->name);
        $response->assertSee($store->name);
        $response->assertDontSee('ARC-ACTIVE-902');
    }

    public function test_owner_cannot_open_central_administrative_archive(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($owner, 'web')
            ->get(route('admin.support.archive.index'))
            ->assertRedirect(route('no.access'));
    }

    public function test_support_can_start_central_review_with_generated_ticket_and_open_correct_trash(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $product = Product::factory()->create(['store_id' => $store->id, 'user_id' => $owner->id]);
        $product->delete();
        $item = ArchivedItem::create([
            'owner_id' => $owner->id,
            'store_id' => $store->id,
            'archivable_type' => Product::class,
            'archivable_id' => $product->id,
            'original_name' => $product->name,
            'reference' => 'DEL-REVIEW-901',
            'status' => 'archived',
            'archived_at' => now(),
            'owner_restore_deadline' => now()->addDays(30),
        ]);

        $this->actingAs($admin, 'web')
            ->post(route('admin.support.archive.review', $item))
            ->assertRedirect(route('user.stores.products.trash', $store));

        $this->assertAuthenticatedAs($owner, 'web');
        $this->assertDatabaseHas('support_tickets', [
            'owner_id' => $owner->id,
            'created_by_support' => true,
            'status' => 'in_progress',
        ]);
        $this->assertDatabaseHas('support_sessions', [
            'target_type' => User::class,
            'target_id' => $owner->id,
            'ended_at' => null,
        ]);
    }

    public function test_central_review_rejects_completed_deleted_item_record(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $owner = User::factory()->create(['role' => User::ROLE_USER]);
        $item = ArchivedItem::create([
            'owner_id' => $owner->id,
            'archivable_type' => Product::class,
            'archivable_id' => 999,
            'original_name' => 'عملية مكتملة',
            'reference' => 'DEL-COMPLETE-999',
            'status' => 'restored',
            'archived_at' => now()->subDay(),
        ]);

        $this->actingAs($admin, 'web')
            ->post(route('admin.support.archive.review', $item))
            ->assertStatus(422);

        $this->assertDatabaseCount('support_sessions', 0);
    }
}
