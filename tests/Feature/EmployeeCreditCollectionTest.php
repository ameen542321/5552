<?php

namespace Tests\Feature;

use App\Domain\EmployeeOperations\Services\EmployeeOperationService;
use App\Models\CreditSale;
use App\Models\Employee;
use App\Models\EmployeeLog;
use App\Models\Store;
use App\Models\User;
use App\Services\ShiftLifecycleService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class EmployeeCreditCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_credit_collection_updates_remaining_amount_and_logs_cash_card_split(): void
    {
        $this->fakeShiftContext('2026-07-18');
        [$owner, $store, $employee] = $this->employeeFixture();

        $creditSale = CreditSale::create([
            'store_id' => $store->id,
            'person_id' => $employee->id,
            'person_type' => Employee::class,
            'amount' => 100,
            'remaining_amount' => 100,
            'description' => 'بيع آجل اختبار',
            'credit_note' => 'عملية تحصيل ميكس',
            'date' => '2026-07-17',
            'status' => CreditSale::STATUS_PENDING,
            'month' => '2026-07',
            'added_by' => $owner->id,
        ]);

        $updated = app(EmployeeOperationService::class)->collectCreditSale(
            $creditSale,
            30,
            ['id' => $owner->id, 'type' => 'accountant', 'name' => 'محاسب الاختبار'],
            [
                'date' => '2026-07-18',
                'payment_method' => 'mixed',
                'cash_amount' => 10,
                'card_amount' => 20,
            ]
        );

        $payment = $updated->fresh()->collection_payments[0];
        $this->assertSame(70.0, (float) $updated->remaining_amount);
        $this->assertSame(CreditSale::STATUS_PENDING, $updated->status);
        $this->assertSame('mixed', $payment['payment_method']);
        $this->assertSame(10.0, (float) $payment['cash_amount']);
        $this->assertSame(20.0, (float) $payment['card_amount']);
        $this->assertSame('2026-07-18', $payment['date']);

        $this->assertDatabaseHas('employee_credit_collections', [
            'credit_sale_id' => $creditSale->id,
            'store_id' => $store->id,
            'person_id' => $employee->id,
            'person_type' => Employee::class,
            'amount' => 30,
            'payment_method' => 'mixed',
            'cash_amount' => 10,
            'card_amount' => 20,
            'collection_date' => '2026-07-18',
            'collected_by' => $owner->id,
        ]);

        $log = EmployeeLog::where('person_id', $employee->id)->where('action_name', 'credit_sale_partial')->firstOrFail();
        $this->assertSame('mixed', $log->meta['payment_method']);
        $this->assertSame(10.0, (float) $log->meta['cash_amount']);
        $this->assertSame(20.0, (float) $log->meta['card_amount']);
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

    private function employeeFixture(): array
    {
        $owner = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $store = Store::factory()->create(['user_id' => $owner->id, 'status' => 'active']);
        $employee = Employee::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'أحمد',
            'phone' => '0500000002',
            'salary' => 1000,
            'status' => 'active',
        ]);

        return [$owner, $store, $employee];
    }
}
