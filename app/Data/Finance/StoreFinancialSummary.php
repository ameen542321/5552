<?php

namespace App\Data\Finance;

/**
 * ملخص مالي لمتجر واحد داخل فترة محاسبية واحدة.
 *
 * وجود DTO باسماء حقول ثابتة يمنع اختلاف مفاتيح المصفوفات بين التقارير والواجهات.
 */
final readonly class StoreFinancialSummary
{
    public function __construct(
        public int $storeId,
        public float $sales,
        public float $productsCost,
        public float $expenses,
        public float $ownerPurchases,
        public float $internalUse,
        public float $employeeDebtBalance = 0.0,
        public float $employeeCreditOutstanding = 0.0,
        public float $employeeCreditCollections = 0.0,
    ) {}

    public function purchasesAndInternalUse(): float
    {
        return $this->ownerPurchases + $this->internalUse;
    }

    public function profit(): float
    {
        return $this->sales - $this->productsCost - $this->expenses - $this->ownerPurchases - $this->internalUse;
    }

    public function toMetricArray(): array
    {
        return [
            'sales' => $this->sales,
            'products_cost' => $this->productsCost,
            'expenses' => $this->expenses,
            'owner_purchases' => $this->ownerPurchases,
            'internal_use' => $this->internalUse,
            'employee_debt_balance' => $this->employeeDebtBalance,
            'employee_credit_outstanding' => $this->employeeCreditOutstanding,
            'employee_credit_collections' => $this->employeeCreditCollections,
            'purchases_and_internal_use' => $this->purchasesAndInternalUse(),
            'profit' => $this->profit(),
        ];
    }
}
