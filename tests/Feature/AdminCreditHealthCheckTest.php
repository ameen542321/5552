<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\CreditHealthCheckController;
use App\Models\CreditCollection;
use App\Models\CreditSale;
use App\Models\Employee;
use App\Models\Store;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Tests\TestCase;

class AdminCreditHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_health_check_reports_credit_data_integrity_issues_without_deleting_data(): void
    {
        $owner = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $store = Store::factory()->create(['user_id' => $owner->id, 'status' => 'active']);
        $employee = Employee::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'name' => 'موظف فحص الأجل',
            'phone' => '0500000099',
            'salary' => 1000,
            'status' => 'active',
        ]);
        DB::statement('PRAGMA defer_foreign_keys = ON');

        CreditSale::create([
            'store_id' => $store->id,
            'person_id' => 999999,
            'person_type' => Employee::class,
            'sale_id' => null,
            'amount' => 100,
            'remaining_amount' => 100,
            'description' => 'أجل بدون موظف',
            'date' => '2026-07-18',
            'status' => CreditSale::STATUS_PENDING,
            'month' => '2026-07',
            'added_by' => $owner->id,
        ]);

        $mismatchCredit = CreditSale::create([
            'store_id' => $store->id,
            'person_id' => $employee->id,
            'person_type' => Employee::class,
            'sale_id' => null,
            'amount' => 100,
            'remaining_amount' => 100,
            'description' => 'أجل متبقيه خاطئ',
            'date' => '2026-07-18',
            'status' => CreditSale::STATUS_PENDING,
            'month' => '2026-07',
            'added_by' => $owner->id,
        ]);
        $mismatchCredit->collections()->create([
            'store_id' => $store->id,
            'person_id' => $employee->id,
            'person_type' => Employee::class,
            'amount' => 30,
            'payment_method' => 'mixed',
            'payment_method_label' => 'ميكس',
            'cash_amount' => 10,
            'card_amount' => 10,
            'collection_date' => '2026-07-18',
            'collected_by' => null,
        ]);
        $this->assertInstanceOf(CreditCollection::class, $mismatchCredit->collections()->first());

        CreditSale::create([
            'store_id' => $store->id,
            'person_id' => $employee->id,
            'person_type' => Employee::class,
            'sale_id' => 999999,
            'amount' => 50,
            'remaining_amount' => 0,
            'description' => 'حالة لا تطابق المتبقي',
            'date' => '2026-07-18',
            'status' => CreditSale::STATUS_PENDING,
            'month' => '2026-07',
            'added_by' => $owner->id,
        ]);

        $response = app(CreditHealthCheckController::class)->index();

        $this->assertInstanceOf(View::class, $response);
        $issues = $response->getData()['issues'];

        $this->assertNotEmpty($issues['missing_employee']['rows']);
        $this->assertNotEmpty($issues['missing_sale_id']['rows']);
        $this->assertNotEmpty($issues['remaining_mismatch']['rows']);
        $this->assertNotEmpty($issues['bad_mixed_payments']['rows']);
        $this->assertNotEmpty($issues['bad_status']['rows']);
        $this->assertNotEmpty($issues['missing_collector']['rows']);
        $this->assertNotEmpty($issues['deleted_sale_link']['rows']);

        $this->assertDatabaseHas('credit_sales', ['description' => 'أجل بدون موظف']);
        $this->assertDatabaseHas('employee_credit_collections', ['credit_sale_id' => $mismatchCredit->id]);
    }
}
