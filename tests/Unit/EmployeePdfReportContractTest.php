<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EmployeePdfReportContractTest extends TestCase
{
    public function test_employee_pdf_contains_profile_transfer_and_debt_details(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/Employees/EmployeeReports.php');
        $view = file_get_contents(__DIR__.'/../../resources/views/pdf/employee-pdf.blade.php');

        $this->assertStringContainsString("where('action_name', 'employee_transferred')", $controller);
        $this->assertStringContainsString("'allDebtOperations'", $controller);
        $this->assertStringContainsString("'pendingDebt'", $controller);
        $this->assertStringContainsString('سجل النقل بين المتاجر', $view);
        $this->assertStringContainsString('بيانات الوظيفة والحساب', $view);
        $this->assertStringContainsString('حالة حساب المحاسب', $view);
        $this->assertStringContainsString('سبب إيقاف الحساب', $view);
        $this->assertStringContainsString('رصيد المديونية الحالي', $view);
        $this->assertStringContainsString('إجمالي التحصيلات', $view);
        $this->assertStringContainsString("data_get(\$transfer->meta, 'old_store_name'", $view);
        $this->assertStringContainsString("\$collectionPayment['notes']", $view);
        $this->assertStringNotContainsString('style="', $view);
    }
}
