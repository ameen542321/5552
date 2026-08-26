<?php

namespace Tests\Feature;

use App\Models\ArchivedItem;
use App\Models\Category;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProductAdministrativeArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_permanent_delete_archives_product_and_releases_its_slug(): void
    {
        $owner = $this->owner();
        $store = $owner->stores()->firstOrFail();
        $category = Category::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'قسم الاختبار',
            'slug' => 'archive-test-category',
            'status' => 'active',
        ]);
        $product = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'name' => 'لمبة سكن 5 وات',
            'slug' => 'lamp-5w-s' . $store->id,
            'price' => 5,
            'cost_price' => 3,
            'quantity' => 0,
            'product_type' => 'normal',
            'status' => 'active',
        ]);
        $originalSlug = $product->slug;
        $product->delete();

        $this->actingAs($owner, 'web')->delete(route('user.stores.products.force-delete', [$store, $product->id]))
            ->assertRedirect(route('user.stores.products.trash', $store->id));

        $archive = ArchivedItem::firstOrFail();
        $this->assertSame($originalSlug, $archive->original_slug);
        $this->assertSame('archived', $archive->status);
        $this->assertTrue($archive->owner_restore_deadline->isSameDay(now()->addDays(30)));
        $this->assertStringContainsString('--archived-' . $product->id, Product::withTrashed()->findOrFail($product->id)->slug);

        $replacement = Product::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'name' => 'لمبة سكن 5 وات',
            'slug' => $originalSlug,
            'price' => 7,
            'cost_price' => 4,
            'quantity' => 0,
            'product_type' => 'normal',
            'status' => 'active',
        ]);

        $this->assertSame($originalSlug, $replacement->slug);
    }

    private function owner(): User
    {
        $plan = Plan::create([
            'name' => 'خطة الأرشيف',
            'allowed_stores' => 2,
            'allowed_accountants' => 2,
            'price' => 0,
        ]);

        return User::create([
            'name' => 'مالك الأرشيف',
            'email' => 'archive-owner@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'role' => User::ROLE_USER,
            'plan_id' => $plan->id,
            'welcome_shown' => true,
            'subscription_end_at' => now()->addYear(),
            'allowed_stores' => 2,
            'allowed_accountants' => 2,
        ]);
    }
}
