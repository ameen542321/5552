<?php

namespace Tests\Feature;

use App\Models\Accountant;
use App\Models\Employee;
use App\Models\Debt;
use App\Models\Log;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Models\Withdrawal;
use App\Http\Controllers\Employees\EmployeeService;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StoreDeletionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_deletion_keeps_references_consistent_and_cleans_all_dependents(): void
    {
        $owner = User::factory()->create([
            'plan_id' => null,
            // ينشئ موديل المستخدم متجرًا رئيسيًا تلقائيًا، لذا نسمح باستعادة المتجرين الإضافيين في هذا السيناريو.
            'allowed_stores' => 3,
        ]);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $replacementStore = Store::factory()->create(['user_id' => $owner->id]);
        $owner->update(['current_store_id' => $store->id]);

        $employee = $this->createEmployee($owner, $store, 'المحاسب المرتبط');
        $trashedEmployee = $this->createEmployee($owner, $store, 'موظف محذوف مؤقتًا');
        $trashedEmployee->delete();

        $accountant = Accountant::create([
            'employee_id' => $employee->id,
            'user_id' => $owner->id,
            'store_id' => $store->id,
            'name' => 'محاسب المتجر',
            'email' => 'store-accountant@example.com',
            'phone' => '0500000000',
            'password' => 'password',
            'status' => 'active',
        ]);

        $sale = Sale::create([
            'store_id' => $store->id,
            'accountant_id' => $accountant->id,
            'total' => 10,
            'paid_amount' => 10,
            'remaining_amount' => 0,
            'sale_type' => 'cash',
            'has_invoice' => false,
        ]);

        $auditLog = Log::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'action' => 'test',
            'description' => 'سجل تابع للمتجر المحذوف',
            'details' => ['source' => 'test'],
        ]);
        DB::table('store_settings')->insert([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'key' => 'test-setting',
            'value' => 'value',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($owner)
            ->delete(route('user.stores.destroy', $store))
            ->assertRedirect(route('user.stores.index'));

        $this->assertSoftDeleted('stores', ['id' => $store->id]);
        $this->assertSame($replacementStore->id, $owner->fresh()->current_store_id);
        $this->assertSame('suspended', $accountant->fresh()->status);
        $this->assertSame(Store::DELETED_STORE_ACCOUNTANT_SUSPENSION_REASON, $accountant->fresh()->suspension_reason);

        $this->actingAs($owner)
            ->post(route('user.stores.restore', $store->id))
            ->assertRedirect(route('user.stores.trash'));

        $this->assertNotSoftDeleted('stores', ['id' => $store->id]);
        $this->assertSame('active', $accountant->fresh()->status);
        $this->assertNull($accountant->fresh()->suspension_reason);

        $this->actingAs($owner)->delete(route('user.stores.destroy', $store));
        $this->actingAs($owner)
            ->delete(route('user.stores.forceDelete', $store->id))
            ->assertRedirect(route('user.stores.trash'));

        $this->assertDatabaseMissing('stores', ['id' => $store->id]);
        $this->assertDatabaseMissing('accountants', ['id' => $accountant->id]);
        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
        $this->assertDatabaseMissing('employees', ['id' => $trashedEmployee->id]);
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertDatabaseMissing('store_settings', ['store_id' => $store->id]);

        $this->assertDatabaseMissing('logs', ['id' => $auditLog->id]);
    }

    public function test_transferred_employee_and_financial_records_survive_old_store_deletion(): void
    {
        $owner = User::factory()->create(['plan_id' => null]);
        $oldStore = Store::factory()->create(['user_id' => $owner->id]);
        $newStore = Store::factory()->create(['user_id' => $owner->id]);
        $employee = $this->createEmployee($owner, $oldStore, 'موظف منقول');

        $debt = Debt::create([
            'store_id' => $oldStore->id,
            'person_id' => $employee->id,
            'person_type' => Employee::class,
            'employee_id' => $employee->id,
            'amount' => 100,
            'type' => 'normal',
            'status' => 'pending',
            'month' => now()->format('Y-m'),
            'added_by' => $owner->id,
        ]);
        $withdrawal = Withdrawal::create([
            'store_id' => $oldStore->id,
            'person_id' => $employee->id,
            'person_type' => Employee::class,
            'employee_id' => $employee->id,
            'amount' => 25,
            'date' => now()->toDateString(),
            'status' => 'pending',
            'month' => now()->format('Y-m'),
            'added_by' => $owner->id,
        ]);

        $employee->update(['store_id' => $newStore->id]);
        EmployeeService::transferEmployeeFinancialRecordsToStore($employee, $newStore->id);

        DB::transaction(static fn () => $oldStore->forceDelete());

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'store_id' => $newStore->id]);
        $this->assertDatabaseHas('debts', ['id' => $debt->id, 'store_id' => $newStore->id]);
        $this->assertDatabaseHas('employee_withdrawals', ['id' => $withdrawal->id, 'store_id' => $newStore->id]);
    }

    private function createEmployee(User $owner, Store $store, string $name): Employee
    {
        return Employee::create([
            'user_id' => $owner->id,
            'store_id' => $store->id,
            'name' => $name,
            'salary' => 0,
            'status' => 'active',
            'added_by' => $owner->id,
        ]);
    }
}
