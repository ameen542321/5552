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
    }

    public function test_historical_rows_are_clearly_marked_as_not_counted_in_the_current_store(): void
    {
        $modal = file_get_contents(__DIR__.'/../../resources/views/components/employee/operation-details-modal.blade.php');

        $this->assertStringContainsString('بيان تاريخي غير محتسب في المتجر الحالي', $modal);
        $this->assertStringContainsString('بيان تاريخي غير محتسب هنا', $modal);
    }
}
