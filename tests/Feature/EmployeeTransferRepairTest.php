<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Store;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Employees\EmployeeTransferRepairService;
use App\Services\Employees\EmployeeTransferService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class EmployeeTransferRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_and_confirmed_service_repair_restore_historical_store_without_changing_amount(): void
    {
        $owner = User::factory()->create(['allowed_stores' => 3]);
        $oldStore = Store::factory()->create(['user_id' => $owner->id]);
        $newStore = Store::factory()->create(['user_id' => $owner->id]);
        $employee = Employee::create([
            'user_id' => $owner->id,
            'store_id' => $oldStore->id,
            'name' => 'موظف اختبار النقل',
            'salary' => 0,
            'status' => 'active',
            'added_by' => $owner->id,
        ]);
        $withdrawal = Withdrawal::create([
            'store_id' => $oldStore->id,
            'person_id' => $employee->id,
            'person_type' => Employee::class,
            'employee_id' => $employee->id,
            'amount' => 25,
            'date' => now()->subDay()->toDateString(),
            'status' => 'pending',
            'month' => now()->format('Y-m'),
            'added_by' => $owner->id,
        ]);

        $employee->update(['store_id' => $newStore->id]);
        app(EmployeeTransferService::class)->finalizeMovedEmployee($employee, $oldStore, now()->startOfDay());

        // يحاكي سجلًا قديمًا نقلته النسخة السابقة جماعيًا إلى المتجر الجديد.
        $withdrawal->update(['store_id' => $newStore->id]);

        $repair = app(EmployeeTransferRepairService::class);
        $preview = $repair->preview();
        $issue = collect($preview['suspectedMovedHistoricalRows'])
            ->firstWhere('row_id', $withdrawal->id);

        $this->assertNotNull($issue);
        $this->assertSame($oldStore->id, $issue['expected_store_id']);

        $result = $repair->apply($preview, 10);

        $this->assertSame(1, $result['repairedRows']);
        $this->assertDatabaseHas('employee_withdrawals', [
            'id' => $withdrawal->id,
            'store_id' => $oldStore->id,
            'amount' => 25,
        ]);
        $this->assertDatabaseHas('employee_logs', [
            'person_id' => $employee->id,
            'action_name' => 'employee_transfer_data_repaired',
        ]);
    }
}
