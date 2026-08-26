<?php

namespace Tests\Unit;

use App\Data\Finance\FinancialSummaryResult;
use App\Data\Finance\StoreFinancialSummary;
use PHPUnit\Framework\TestCase;

class FinancialSummaryResultTest extends TestCase
{
    public function test_totals_sum_store_summaries_and_profit_formula(): void
    {
        $result = new FinancialSummaryResult(collect([
            1 => new StoreFinancialSummary(
                storeId: 1,
                sales: 1000.0,
                productsCost: 300.0,
                expenses: 100.0,
                ownerPurchases: 50.0,
                internalUse: 25.0,
                employeeDebtBalance: 200.0,
                employeeCreditOutstanding: 80.0,
                employeeCreditCollections: 30.0,
            ),
            2 => new StoreFinancialSummary(
                storeId: 2,
                sales: 500.0,
                productsCost: 125.0,
                expenses: 40.0,
                ownerPurchases: 10.0,
                internalUse: 5.0,
                employeeDebtBalance: 25.0,
                employeeCreditOutstanding: 20.0,
                employeeCreditCollections: 5.0,
            ),
        ]));

        $totals = $result->totals();

        $this->assertSame(0, $totals->storeId);
        $this->assertSame(1500.0, $totals->sales);
        $this->assertSame(425.0, $totals->productsCost);
        $this->assertSame(140.0, $totals->expenses);
        $this->assertSame(60.0, $totals->ownerPurchases);
        $this->assertSame(30.0, $totals->internalUse);
        $this->assertSame(225.0, $totals->employeeDebtBalance);
        $this->assertSame(100.0, $totals->employeeCreditOutstanding);
        $this->assertSame(35.0, $totals->employeeCreditCollections);
        $this->assertSame(845.0, $totals->profit());
    }

    public function test_employee_receivables_do_not_change_store_profit(): void
    {
        $summary = new StoreFinancialSummary(
            storeId: 5,
            sales: 300.0,
            productsCost: 100.0,
            expenses: 40.0,
            ownerPurchases: 30.0,
            internalUse: 10.0,
            employeeDebtBalance: 999.0,
            employeeCreditOutstanding: 888.0,
            employeeCreditCollections: 777.0,
        );

        $this->assertSame(120.0, $summary->profit());
        $this->assertSame(999.0, $summary->toMetricArray()['employee_debt_balance']);
        $this->assertSame(888.0, $summary->toMetricArray()['employee_credit_outstanding']);
        $this->assertSame(777.0, $summary->toMetricArray()['employee_credit_collections']);
    }

}
