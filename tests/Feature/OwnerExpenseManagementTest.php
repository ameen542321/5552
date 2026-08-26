<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Store;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class OwnerExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_expenses_from_the_store_expense_page(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);
        $store = $owner->stores()->firstOrFail();
        $store->update(['status' => 'active']);

        $this->actingAs($owner)
            ->get(route('user.stores.expenses.index', $store))
            ->assertOk()
            ->assertSee('المصروفات')
            ->assertSee('إضافة مصروف');

        $this->actingAs($owner)
            ->post(route('user.stores.expenses.store', $store), [
                'type' => 'صيانة',
                'amount' => 125.50,
                'description' => 'صيانة جهاز',
                'business_date' => now()->subDay()->toDateString(),
            ])
            ->assertRedirect();

        $expense = Expense::where('store_id', $store->id)->where('type', 'صيانة')->firstOrFail();
        $this->assertSame('owner_expense', $expense->actor_type);
        $this->assertSame(now()->subDay()->toDateString(), optional($expense->business_date)->toDateString());

        $this->actingAs($owner)
            ->get(route('user.stores.expenses.index', ['store' => $store, 'date' => $expense->business_date->toDateString()]))
            ->assertOk()
            ->assertSee('صيانة')
            ->assertSee($owner->name);

        $this->actingAs($owner)
            ->put(route('user.stores.expenses.update', [$store, $expense]), [
                'type' => 'صيانة دورية',
                'amount' => 150,
                'description' => 'تم التحديث',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'type' => 'صيانة دورية',
            'amount' => 150,
        ]);

        $this->actingAs($owner)
            ->delete(route('user.stores.expenses.destroy', [$store, $expense]))
            ->assertRedirect();

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    public function test_store_expense_route_rejects_an_expense_from_another_store(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'welcome_shown' => true,
            'subscription_end_at' => now()->addDays(30),
        ]);
        $firstStore = $owner->stores()->firstOrFail();
        $secondStore = Store::factory()->create(['user_id' => $owner->id]);
        $expense = Expense::create([
            'store_id' => $secondStore->id,
            'user_id' => $owner->id,
            'type' => 'اختبار',
            'description' => 'اختبار',
            'amount' => 10,
            'actor_type' => 'owner_expense',
            'business_date' => now()->toDateString(),
        ]);

        $this->actingAs($owner)
            ->delete(route('user.stores.expenses.destroy', [$firstStore, $expense]))
            ->assertNotFound();

        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'deleted_at' => null]);
    }
}
