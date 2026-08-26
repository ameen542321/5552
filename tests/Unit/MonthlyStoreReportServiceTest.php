<?php

namespace Tests\Unit;

use App\Services\Reports\MonthlyStoreReportService;
use PHPUnit\Framework\TestCase;

class MonthlyStoreReportServiceTest extends TestCase
{
    public function test_sales_reconciliation_does_not_add_mixed_sales_twice(): void
    {
        $service = new MonthlyStoreReportService();

        $this->assertSame(10.0, $service->unallocatedCollectedSales(31170, 9245, 21915));
    }

    public function test_net_recognized_profit_deducts_expenses_and_operating_consumption(): void
    {
        $result = (new MonthlyStoreReportService())->netRecognizedProfit(
            recognizedSalesProfit: 25_142,
            internalUse: 500,
            ownerPurchases: 1_000,
            expenses: 2_142
        );

        $this->assertSame(21_500.0, $result);
    }
}
