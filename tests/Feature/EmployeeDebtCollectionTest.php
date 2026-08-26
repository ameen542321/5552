<?php

namespace Tests\Feature;

use App\Domain\EmployeeOperations\Exceptions\EmployeeOperationException;
use App\Domain\EmployeeOperations\Services\EmployeeOperationService;
use App\Models\Debt;
use App\Models\Employee;
use App\Models\Store;
use App\Models\User;
use App\Services\ShiftLifecycleService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class EmployeeDebtCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_debt_collections_are_linked_to_original_debt_and_allow_same_day_same_amount_on_different_debts(): void
    {
        $this->fakeShiftContext('2026-07-21');
        [$owner, $store, $employee] = $this->employeeFixture();
        $service = app(EmployeeOperationService::class);
        $actor = ['id' => $owner->id, 'type' => 'user', 'name' => 'مالك الاختبار'];

        $firstDebt = $service->recordDebt($employee, [
            'amount' => 100,
            'date' => '2026-07-21',
            'description' => 'مديونية أولى',
        ], $actor);
        $secondDebt = $service->recordDebt($employee, [
            'amount' => 150,
            'date' => '2026-07-21',
            'description' => 'مديونية ثانية',
        ], $actor);

        $firstCollection = $service->collectDebt($firstDebt, 25, $actor, ['date' => '2026-07-21']);
        $secondCollection = $service->collectDebt($secondDebt, 25, $actor, ['date' => '2026-07-21']);

        // المثال العملي: التحصيلان لهما نفس اليوم ونفس المبلغ ونفس الموظف،
        // لكن كل تحصيل مرتبط بمديونية أصلية مختلفة، لذلك يجب قبولهما وعدم اعتبارهما تكرارًا.
        $this->assertSame($firstDebt->id, (int) $firstCollection->debt_parent_id);
        $this->assertSame($secondDebt->id, (int) $secondCollection->debt_parent_id);
        $this->assertSame(75.0, (float) $firstDebt->fresh()->amount);
        $this->assertSame(125.0, (float) $secondDebt->fresh()->amount);

        $this->assertDatabaseHas('debts', [
            'id' => $firstCollection->id,
            'debt_parent_id' => $firstDebt->id,
            'amount' => -25,
        ]);
        $this->assertDatabaseHas('debts', [
            'id' => $secondCollection->id,
            'debt_parent_id' => $secondDebt->id,
            'amount' => -25,
        ]);
    }

    public function test_collected_debt_rows_cannot_be_collected_again(): void
    {
        $this->fakeShiftContext('2026-07-21');
        [$owner, , $employee] = $this->employeeFixture();
        $service = app(EmployeeOperationService::class);
        $actor = ['id' => $owner->id, 'type' => 'user', 'name' => 'مالك الاختبار'];

        $debt = $service->recordDebt($employee, [
            'amount' => 100,
            'date' => '2026-07-21',
            'description' => 'مديونية أصلية',
        ], $actor);
        $collection = $service->collectDebt($debt, 10, $actor, ['date' => '2026-07-21']);

        // هذا السجل يمثل قبض 10 ريالات من الدين الأصلي وليس دينًا جديدًا،
        // لذلك محاولة تحصيله مرة أخرى يجب أن تفشل لحماية الرصيد من التلاعب أو الخطأ.
        $this->expectException(EmployeeOperationException::class);
        $service->collectDebt($collection, 1, $actor, ['date' => '2026-07-21']);
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
            'name' => 'أحمد الديون',
            'phone' => '0500000010',
            'salary' => 1000,
            'status' => 'active',
        ]);

        return [$owner, $store, $employee];
    }
}
