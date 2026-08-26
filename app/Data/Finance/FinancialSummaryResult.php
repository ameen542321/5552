<?php

namespace App\Data\Finance;

use Illuminate\Support\Collection;

/**
 * نتيجة مالية مجمعة لفترة محاسبية.
 *
 * تحتفظ بالـ DTOs كمصدر حديث موحد بدل مفاتيح مصفوفات متفرقة بين التقارير والواجهات.
 */
final readonly class FinancialSummaryResult
{
    /**
     * @param Collection<int, StoreFinancialSummary> $summariesByStore
     */
    public function __construct(
        public Collection $summariesByStore,
    ) {}

    public function totals(): StoreFinancialSummary
    {
        return new StoreFinancialSummary(
            storeId: 0,
            sales: (float) $this->summariesByStore->sum(fn (StoreFinancialSummary $summary) => $summary->sales),
            productsCost: (float) $this->summariesByStore->sum(fn (StoreFinancialSummary $summary) => $summary->productsCost),
            expenses: (float) $this->summariesByStore->sum(fn (StoreFinancialSummary $summary) => $summary->expenses),
            ownerPurchases: (float) $this->summariesByStore->sum(fn (StoreFinancialSummary $summary) => $summary->ownerPurchases),
            internalUse: (float) $this->summariesByStore->sum(fn (StoreFinancialSummary $summary) => $summary->internalUse),
            employeeDebtBalance: (float) $this->summariesByStore->sum(fn (StoreFinancialSummary $summary) => $summary->employeeDebtBalance),
            employeeCreditOutstanding: (float) $this->summariesByStore->sum(fn (StoreFinancialSummary $summary) => $summary->employeeCreditOutstanding),
            employeeCreditCollections: (float) $this->summariesByStore->sum(fn (StoreFinancialSummary $summary) => $summary->employeeCreditCollections),
        );
    }

}
