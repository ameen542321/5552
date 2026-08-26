<?php

namespace Tests\Feature;

use App\Http\Controllers\Accountant\DashboardController;
use App\Models\Employee;
use App\Models\EmployeeLog;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Tests\Concerns\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class AccountantCreditDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_finance_movements_group_credit_collections_and_debt_rows_by_operation_date(): void
    {
        $owner = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $store = Store::factory()->create(['user_id' => $owner->id, 'status' => 'active']);
        $employee = Employee::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'أحمد التحصيل',
            'phone' => '0500000004',
            'salary' => 1000,
            'status' => 'active',
        ]);

        EmployeeLog::create([
            'store_id' => $store->id,
            'person_id' => $employee->id,
            'person_type' => Employee::class,
            'action_name' => 'credit_sale_partial',
            'amount' => 30,
            'description' => 'تحصيل جزئي من عملية آجل',
            'meta' => [
                'actor_type' => 'accountant',
                'actor_name' => 'محاسب الفترة',
                'operation_date' => '2026-07-18',
                'payment_method' => 'mixed',
                'payment_method_label' => 'ميكس',
                'cash_amount' => 10,
                'card_amount' => 20,
            ],
            'created_at' => Carbon::parse('2026-07-19 01:00:00'),
        ]);

        EmployeeLog::create([
            'store_id' => $store->id,
            'person_id' => $employee->id,
            'person_type' => Employee::class,
            'action_name' => 'debt',
            'amount' => 50,
            'description' => 'مديونية اختبار',
            'meta' => [
                'actor_type' => 'accountant',
                'actor_name' => 'محاسب الفترة',
                'operation_date' => '2026-07-18',
            ],
            'created_at' => Carbon::parse('2026-07-19 01:05:00'),
        ]);

        $method = new ReflectionMethod(DashboardController::class, 'getAccountantFinanceMovements');
        $method->setAccessible(true);

        $result = $method->invoke(
            app(DashboardController::class),
            $store->id,
            Carbon::parse('2026-07-18 00:00:00'),
            Carbon::parse('2026-07-19 02:00:00'),
            '2026-07-18'
        );

        $this->assertSame(30.0, (float) $result['collections_total']);
        $this->assertSame(10.0, (float) $result['collections_cash_total']);
        $this->assertSame(20.0, (float) $result['collections_card_total']);
        $this->assertCount(1, $result['collection_rows']);
        $this->assertCount(1, $result['debt_rows']);
        $this->assertSame('أحمد التحصيل', $result['collection_rows'][0]['employee_name']);
    }
}
