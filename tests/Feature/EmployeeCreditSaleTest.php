<?php

namespace Tests\Feature;

use App\Domain\EmployeeOperations\Services\EmployeeOperationService;
use App\Domain\EmployeeOperations\Exceptions\EmployeeOperationException;
use App\Models\CreditSale;
use App\Models\Employee;
use App\Models\Store;
use App\Models\User;
use App\Services\ShiftLifecycleService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class EmployeeCreditSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_credit_sale_with_operation_name_and_business_date(): void
    {
        $this->fakeShiftContext('2026-07-17');
        [$owner, $store, $employee] = $this->employeeFixture();

        $creditSale = app(EmployeeOperationService::class)->recordCreditSale(
            $employee,
            [
                'amount' => 30,
                'date' => '2026-07-17',
                'description' => 'منتج 20 وشغل يد 10',
                'operation_name' => 'بيع سريع أحمد',
            ],
            ['id' => $owner->id, 'type' => 'user', 'name' => 'مالك الاختبار']
        );

        $this->assertInstanceOf(CreditSale::class, $creditSale);
        $this->assertSame('credit_sales', $creditSale->getTable());
        $this->assertSame('بيع سريع أحمد', $creditSale->credit_note);
        $this->assertSame('بيع سريع أحمد', $creditSale->operation_name);
        $this->assertSame(30.0, (float) $creditSale->remaining_amount);
        $this->assertSame('2026-07-17', $creditSale->date->toDateString());

        $this->assertDatabaseHas('credit_sales', [
            'store_id' => $store->id,
            'person_id' => $employee->id,
            'person_type' => Employee::class,
            'amount' => 30,
            'remaining_amount' => 30,
            'credit_note' => 'بيع سريع أحمد',
            'status' => CreditSale::STATUS_PENDING,
        ]);
    }

    public function test_suspended_employee_cannot_receive_new_credit_sale(): void
    {
        $this->fakeShiftContext('2026-07-17');
        [$owner, , $employee] = $this->employeeFixture(['status' => 'suspended']);

        $this->expectException(EmployeeOperationException::class);
        $this->expectExceptionMessage('لا يمكن إضافة أجل لموظف موقوف.');

        app(EmployeeOperationService::class)->recordCreditSale(
            $employee,
            [
                'amount' => 30,
                'date' => '2026-07-17',
                'operation_name' => 'بيع سريع لموظف موقوف',
            ],
            ['id' => $owner->id, 'type' => 'user', 'name' => 'مالك الاختبار']
        );
    }

    public function test_suspended_employee_cannot_receive_new_absence(): void
    {
        $this->fakeShiftContext('2026-07-17');
        [$owner, , $employee] = $this->employeeFixture(['status' => 'suspended']);

        $this->expectException(EmployeeOperationException::class);
        $this->expectExceptionMessage('لا يمكن تسجيل غياب لموظف موقوف.');

        app(EmployeeOperationService::class)->recordAbsence(
            $employee,
            [
                'date' => '2026-07-17',
                'description' => 'غياب لموظف موقوف',
            ],
            ['id' => $owner->id, 'type' => 'user', 'name' => 'مالك الاختبار']
        );
    }

    private function fakeShiftContext(string $businessDate): void
    {
        $this->app->instance(ShiftLifecycleService::class, new class($businessDate) {
            public function __construct(private readonly string $businessDate) {}

            public function currentShiftContext(int $storeId, $now = null): array
            {
                return [
                    'business_date' => $this->businessDate,
                    'daily_balance_id' => null,
                    'is_shift_gap_processing' => false,
                ];
            }
        });
    }

    private function employeeFixture(array $employeeOverrides = []): array
    {
        $owner = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $store = Store::factory()->create(['user_id' => $owner->id, 'status' => 'active']);
        $employee = Employee::create(array_merge([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'أحمد',
            'phone' => '0500000001',
            'salary' => 1000,
            'status' => 'active',
        ], $employeeOverrides));

        return [$owner, $store, $employee];
    }
}
