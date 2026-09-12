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
        $transferService = file_get_contents(__DIR__.'/../../app/Services/Employees/EmployeeTransferService.php');
        $payrollService = file_get_contents(__DIR__.'/../../app/Services/Employees/EmployeePayrollService.php');

        $this->assertStringContainsString('transferred_personal_debt_balance', $transferService);
        $this->assertStringNotContainsString("'employee_withdrawals',\n            'employee_absences'", $employeeService);
        $this->assertStringContainsString('employeeStoreIdAtPeriodEnd', $payrollService);
        $this->assertStringContainsString("where('status', Debt::STATUS_PENDING)", $payrollService);
        $this->assertStringContainsString("where('amount', '>', 0)", $payrollService);
    }

    public function test_transfer_and_accountant_activation_are_centralized_and_auditable(): void
    {
        $employeeController = file_get_contents(__DIR__.'/../../app/Http/Controllers/Employees/EmployeeService.php');
        $accountantController = file_get_contents(__DIR__.'/../../app/Http/Controllers/Accoun5555tantController.php');
        $employeeActions = file_get_contents(__DIR__.'/../../app/Http/Controllers/Employees/EmployeeActions.php');
        $auditCommand = file_get_contents(__DIR__.'/../../app/Console/Commands/AuditEmployeeTransfers.php');
        $repairCommand = file_get_contents(__DIR__.'/../../app/Console/Commands/RepairEmployeeTransfers.php');
        $repairService = file_get_contents(__DIR__.'/../../app/Services/Employees/EmployeeTransferRepairService.php');

        $this->assertStringContainsString('EmployeeTransferService::class', $employeeController);
        $this->assertStringContainsString('EmployeeTransferService::class', $accountantController);
        $this->assertStringContainsString('EmployeeAccountantLifecycleService::class', $accountantController);
        $this->assertStringContainsString('EmployeeAccountantLifecycleService::class', $employeeActions);
        $this->assertStringContainsString('employees:audit-transfers', $auditCommand);
        $this->assertStringContainsString('لا ينفذ أي تصحيح تلقائي', $auditCommand);
        $this->assertStringContainsString('employees:repair-transfers', $repairCommand);
        $this->assertStringContainsString('--backup-confirmed', $repairCommand);
        $this->assertStringContainsString('REPAIR_EMPLOYEE_TRANSFERS', $repairCommand);
        $this->assertStringContainsString('employee_transfer_data_repaired', $repairService);
        $this->assertStringContainsString('$totalsBefore !== $totalsAfter', $repairService);
    }
}
