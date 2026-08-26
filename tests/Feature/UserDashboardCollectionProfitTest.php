<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class UserDashboardCollectionProfitTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_profit_uses_collected_amount_as_sale_basis(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'subscription_end_at' => now()->addDays(30),
        ]);

        $store = Store::factory()->create([
            'user_id' => $owner->id,
            'status' => 'active',
        ]);

        Sale::create([
            'store_id' => $store->id,
            'employee_id' => null,
            'accountant_id' => null,
            'sale_type' => 'cash',
            'products_total' => 20,
            'tax_rate' => 0,
            'labor_total' => 0,
            'final_total' => 20,
            'paid_amount' => 40,
            'cash_amount' => 40,
            'card_amount' => 0,
            'remaining_amount' => 0,
            'has_partial_credit' => false,
            'profit' => 8,
            'total' => 20,
            'has_invoice' => false,
            'description' => 'Collected amount is sale basis',
            'created_at' => now()->startOfDay()->addHours(10),
            'updated_at' => now()->startOfDay()->addHours(10),
        ]);

        $response = $this
            ->actingAs($owner, 'web')
            ->get(route('user.dashboard'));

        $response->assertOk();
        $response->assertSee('28', false);
    }

    public function test_owner_can_filter_dashboard_by_accounting_date_and_sees_missing_balance_warning(): void
    {
        $this->travelTo(Carbon::parse('2026-07-28 12:00:00'));

        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'subscription_end_at' => now()->addDays(30),
        ]);
        $store = Store::factory()->create([
            'user_id' => $owner->id,
            'name' => 'متجر الاختبار',
            'status' => 'active',
        ]);

        Sale::create([
            'store_id' => $store->id,
            'sale_type' => 'cash',
            'products_total' => 0,
            'tax_rate' => 0,
            'labor_total' => 40,
            'final_total' => 40,
            'paid_amount' => 40,
            'cash_amount' => 40,
            'card_amount' => 0,
            'remaining_amount' => 0,
            'has_partial_credit' => false,
            'profit' => 40,
            'total' => 40,
            'has_invoice' => false,
            'description' => 'عملية بتاريخ محاسبي سابق',
            'business_date' => '2026-07-27',
        ]);

        $response = $this
            ->actingAs($owner, 'web')
            ->get(route('user.dashboard', ['date' => '2026-07-27']));

        $response->assertOk();
        $response->assertSee('ملخص يوم 2026-07-27');
        $response->assertSee('بيانات غير مكتملة');
        $response->assertSee('موازنة غير صادرة');
        $response->assertSee('قد لا تكون الإحصائيات الظاهرة صحيحة للأسباب التالية');
        $response->assertSee('متجر الاختبار');
        $response->assertSee('متجري');

        $this->actingAs($owner, 'web')
            ->getJson(route('user.dashboard.daily-snapshot', ['date' => '2026-07-27']))
            ->assertOk()
            ->assertJsonPath('sales_today', 40)
            ->assertJsonPath('operations_count', 1)
            ->assertJsonPath('latest_operation.description', 'عملية بتاريخ محاسبي سابق');
    }

    public function test_owner_navbar_uses_plural_store_label_for_multiple_stores(): void
    {
        $owner = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
            'subscription_end_at' => now()->addDays(30),
        ]);

        Store::factory()->count(2)->create([
            'user_id' => $owner->id,
            'status' => 'active',
        ]);

        $this->actingAs($owner, 'web')
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('متاجري');
    }
}
