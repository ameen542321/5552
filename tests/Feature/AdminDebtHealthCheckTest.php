<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DebtHealthCheckController;
use App\Models\Debt;
use App\Models\Employee;
use App\Models\Store;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Tests\TestCase;

class AdminDebtHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_debt_health_check_reports_debt_integrity_issues_without_deleting_data(): void
    {
        $owner = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $store = Store::factory()->create(['user_id' => $owner->id, 'status' => 'active']);
        $employee = Employee::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'موظف فحص المديونية',
            'phone' => '0500000199',
            'salary' => 1000,
            'status' => 'active',
        ]);
        DB::statement('PRAGMA defer_foreign_keys = ON');

        Debt::create($this->debtPayload($store->id, 999999, $owner->id, [
            'amount' => 100,
            'description' => 'مديونية بدون موظف',
        ]));

        $parentDebt = Debt::create($this->debtPayload($store->id, $employee->id, $owner->id, [
            'amount' => 100,
            'description' => 'مديونية أصلية للفحص',
        ]));

        Debt::create($this->debtPayload($store->id, $employee->id, $owner->id, [
            'amount' => -10,
            'description' => 'تحصيل قديم بدون ربط',
        ]));

        Debt::create($this->debtPayload($store->id, $employee->id, $owner->id, [
            'debt_parent_id' => 999999,
            'amount' => -15,
            'description' => 'تحصيل مرتبط بأصل مفقود',
        ]));

        Debt::create($this->debtPayload($store->id, $employee->id, $owner->id, [
            'debt_parent_id' => $parentDebt->id,
            'amount' => -25,
            'description' => 'تحصيل مكرر',
        ]));
        Debt::create($this->debtPayload($store->id, $employee->id, $owner->id, [
            'debt_parent_id' => $parentDebt->id,
            'amount' => -25,
            'description' => 'تحصيل مكرر',
        ]));

        $settledParentDebt = Debt::create($this->debtPayload($store->id, $employee->id, $owner->id, [
            'amount' => 0,
            'status' => Debt::STATUS_DEDUCTED,
            'description' => 'مديونية مسددة صحيحة',
        ]));
        Debt::create($this->debtPayload($store->id, $employee->id, $owner->id, [
            'debt_parent_id' => $settledParentDebt->id,
            'amount' => -100,
            'description' => 'تحصيل صحيح لمديونية مسددة',
        ]));

        Debt::create($this->debtPayload($store->id, $employee->id, $owner->id, [
            'amount' => 40,
            'status' => Debt::STATUS_DEDUCTED,
            'description' => 'مسدد مع رصيد موجب',
        ]));

        $response = app(DebtHealthCheckController::class)->index();

        $this->assertInstanceOf(View::class, $response);
        $issues = $response->getData()['issues'];

        $this->assertNotEmpty($issues['missing_employee']['rows']);
        $this->assertNotEmpty($issues['collection_without_parent']['rows']);
        $this->assertNotEmpty($issues['collection_parent_missing']['rows']);
        $this->assertNotEmpty($issues['duplicate_collection']['rows']);
        $this->assertNotEmpty($issues['deducted_positive_balance']['rows']);
        $this->assertNotContains(
            'تحصيل صحيح لمديونية مسددة',
            collect($issues['collection_parent_mismatch']['rows'])->pluck('description')->all(),
            'التحصيل الصحيح المرتبط بأصل مسدد رصيده صفر لا يجب أن يظهر كمشكلة عدم تطابق.'
        );

        // تأكيد مهم: فحص الأدمن قراءة فقط ولا يحذف بيانات الاختبار أو بيانات الإنتاج عند فتح الصفحة.
        $this->assertDatabaseHas('debts', ['description' => 'مديونية بدون موظف']);
        $this->assertDatabaseHas('debts', ['description' => 'تحصيل قديم بدون ربط']);
        $this->assertDatabaseHas('debts', ['description' => 'تحصيل مرتبط بأصل مفقود']);
    }

    private function debtPayload(int $storeId, int $personId, int $addedBy, array $overrides = []): array
    {
        return array_merge([
            'store_id' => $storeId,
            'person_id' => $personId,
            'person_type' => Employee::class,
            'amount' => 100,
            'description' => 'مديونية اختبار',
            'type' => 'normal',
            'status' => Debt::STATUS_PENDING,
            'month' => '2026-07',
            'added_by' => $addedBy,
            'date' => '2026-07-21',
        ], $overrides);
    }
}
