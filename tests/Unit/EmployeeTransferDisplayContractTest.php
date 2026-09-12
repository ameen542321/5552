<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EmployeeTransferDisplayContractTest extends TestCase
{
    public function test_current_store_summaries_exclude_historical_store_operations(): void
    {
        $viewModel = file_get_contents(__DIR__.'/../../app/Domain/EmployeeOperations/ViewModels/EmployeeOperationPageViewModel.php');

        $this->assertStringContainsString("where('store_id', (int) \$person->store_id)", $viewModel);
        $this->assertStringContainsString('monthlyRowsForStore((int) $person->store_id', $viewModel);
        $this->assertStringContainsString('is_historical_store_operation', $viewModel);
        $this->assertStringContainsString('operation_store_name', $viewModel);
        $this->assertStringContainsString('openCreditCollectionDatesByStore', $viewModel);
    }

    public function test_historical_rows_are_clearly_marked_as_not_counted_in_the_current_store(): void
    {
        $modal = file_get_contents(__DIR__.'/../../resources/views/components/employee/operation-details-modal.blade.php');

        $this->assertStringContainsString('بيان تاريخي غير محتسب في المتجر الحالي', $modal);
        $this->assertStringContainsString('بيان تاريخي غير محتسب هنا', $modal);
    }

    public function test_personal_debt_balance_follows_period_end_assignment_without_moving_original_rows(): void
    {
        $employeeService = file_get_contents(__DIR__.'/../../app/Http/Controllers/Employees/EmployeeService.php');
        $payrollService = file_get_contents(__DIR__.'/../../app/Services/Employees/EmployeePayrollService.php');

        $this->assertStringContainsString('transferred_personal_debt_balance', $employeeService);
        $this->assertStringNotContainsString("'employee_withdrawals',\n            'employee_absences'", $employeeService);
        $this->assertStringContainsString('employeeStoreIdAtPeriodEnd', $payrollService);
        $this->assertStringContainsString("where('status', Debt::STATUS_PENDING)", $payrollService);
        $this->assertStringContainsString("where('amount', '>', 0)", $payrollService);
    }
}
