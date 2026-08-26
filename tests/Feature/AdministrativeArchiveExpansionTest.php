<?php

namespace Tests\Feature;

use App\Models\ArchivedItem;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Store;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class AdministrativeArchiveExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_force_delete_archives_category_employee_and_store_without_physical_deletion(): void
    {
        $owner = $this->owner();
        $store = $owner->stores()->firstOrFail();

        $category = Category::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'قسم تجريبي',
            'description' => 'قسم لاختبار الأرشفة',
            'status' => 'active',
        ]);
        $category->delete();

        $employee = Employee::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'موظف تجريبي',
            'phone' => '0500000998',
            'salary' => 1000,
            'status' => 'active',
        ]);
        $employee->delete();

        $emptyStore = Store::create([
            'user_id' => $owner->id,
            'name' => 'متجر تجريبي',
            'slug' => 'temporary-store-archive-test',
            'status' => 'active',
        ]);
        $emptyStore->delete();

        $this->actingAs($owner, 'web')
            ->delete(route('user.stores.categories.force-delete', [$store, $category->id]))
            ->assertSessionHas('success');
        $this->delete(route('user.employees.forceDelete', $employee->id))->assertSessionHas('success');
        $this->delete(route('user.stores.forceDelete', $emptyStore->id))->assertSessionHas('success');

        $this->assertDatabaseCount('archived_items', 3);
        $this->assertNotNull(Category::withTrashed()->find($category->id));
        $this->assertNotNull(Employee::withTrashed()->find($employee->id));
        $this->assertNotNull(Store::withTrashed()->find($emptyStore->id));
        $this->assertStringContainsString('--archived-', Category::withTrashed()->findOrFail($category->id)->slug);
        $this->assertStringContainsString('--archived-', Store::withTrashed()->findOrFail($emptyStore->id)->slug);
        $this->assertTrue(ArchivedItem::get()->every(
            fn (ArchivedItem $item) => $item->owner_restore_deadline?->isSameDay(now()->addDays(30))
        ));
    }

    private function owner(): User
    {
        $plan = Plan::create([
            'name' => 'خطة اختبار التوسع',
            'allowed_stores' => 5,
            'allowed_accountants' => 5,
            'price' => 0,
        ]);

        return User::create([
            'name' => 'مالك اختبار التوسع',
            'email' => 'archive-expansion@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'role' => User::ROLE_USER,
            'plan_id' => $plan->id,
            'welcome_shown' => true,
            'subscription_end_at' => now()->addYear(),
            'allowed_stores' => 5,
            'allowed_accountants' => 5,
        ]);
    }
}
