<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AccountantWhatsAppCreditReportContractTest extends TestCase
{
    public function test_whatsapp_report_uses_credit_sale_and_collection_details(): void
    {
        $source = file_get_contents(__DIR__.'/../../app/Http/Controllers/Accountant/DashboardController.php');

        $this->assertStringContainsString('*تفاصيل بيع آجل جديد:*', $source);
        $this->assertStringContainsString('العملية: {$operationName} | المبلغ:', $source);
        $this->assertStringContainsString('| الموظف:', $source);
        $this->assertStringContainsString('*تفاصيل تحصيلات الآجل:*', $source);
        $this->assertStringContainsString("'mixed' => 'ميكس'", $source);
        $this->assertStringContainsString('هذه العمليات توضيحية ولا تدخل في مطابقة الكاش قبل التحصيل', $source);
        $this->assertStringNotContainsString('⏳ ربح مؤجل:', $source);
    }
}
