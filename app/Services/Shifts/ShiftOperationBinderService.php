<?php

namespace App\Services\Shifts;

use App\Models\DailyBalance;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\Withdrawal;
use Carbon\Carbon;

class ShiftOperationBinderService
{
    /**
     * يربط عمليات يوم محاسبي غير مرتبطة بعد بشفت مغلق.
     *
     * يستخدم created_at فقط كـ fallback اختياري للبيانات القديمة التي لا تملك business_date.
     */
    public function attachByBusinessDate(
        DailyBalance $balance,
        string $businessDate,
        bool $includeLegacyCreatedAtDate = false,
        bool $excludeManualInvoiceEntries = false
    ): array {
        $operationsAccountingDate = Carbon::parse($businessDate)->toDateString();
        $linkOperationsToClosedBalancePayload = [
            'business_date' => $operationsAccountingDate,
            'daily_balance_id' => $balance->id,
        ];

        $unlinkedSalesForBusinessDate = Sale::query()
            ->where('store_id', $balance->store_id)
            ->whereNull('daily_balance_id')
            ->forAccountingDate($operationsAccountingDate, $includeLegacyCreatedAtDate)
            ->when($excludeManualInvoiceEntries, fn ($query) => $query->excludeManualInvoiceEntries());

        $unlinkedExpensesForBusinessDate = Expense::query()
            ->where('store_id', $balance->store_id)
            ->whereNull('daily_balance_id')
            ->forAccountingDate($operationsAccountingDate, $includeLegacyCreatedAtDate);

        $unlinkedWithdrawalsForBusinessDate = Withdrawal::query()
            ->where('store_id', $balance->store_id)
            ->whereNull('daily_balance_id')
            ->forAccountingDate($operationsAccountingDate, $includeLegacyCreatedAtDate);

        return [
            'sales' => $unlinkedSalesForBusinessDate->update($linkOperationsToClosedBalancePayload),
            'expenses' => $unlinkedExpensesForBusinessDate->update($linkOperationsToClosedBalancePayload),
            'withdrawals' => $unlinkedWithdrawalsForBusinessDate->update($linkOperationsToClosedBalancePayload),
        ];
    }

    /**
     * يربط عمليات نافذة زمنية محددة بشفت مغلق، مع تثبيت business_date للعمليات الجديدة.
     */
    public function attachByWindow(DailyBalance $balance, Carbon $start, Carbon $end, string $businessDate): array
    {
        $operationsAccountingDate = Carbon::parse($businessDate)->toDateString();
        $linkOperationsToClosedBalancePayload = [
            'business_date' => $operationsAccountingDate,
            'daily_balance_id' => $balance->id,
        ];

        return [
            'sales' => Sale::where('store_id', $balance->store_id)
                ->whereBetween('created_at', [$start, $end])
                ->update($linkOperationsToClosedBalancePayload),
            'expenses' => Expense::where('store_id', $balance->store_id)
                ->whereBetween('created_at', [$start, $end])
                ->update($linkOperationsToClosedBalancePayload),
            'withdrawals' => Withdrawal::where('store_id', $balance->store_id)
                ->whereBetween('created_at', [$start, $end])
                ->update($linkOperationsToClosedBalancePayload),
        ];
    }

    /**
     * ينقل التاريخ لكل العمليات المرتبطة بالشفت، ثم يلحق العمليات القديمة غير المرتبطة من نافذته.
     */
    public function moveBalanceOperations(DailyBalance $balance, string $targetBusinessDate, ?string $sourceBusinessDate = null): array
    {
        $targetOperationsAccountingDate = Carbon::parse($targetBusinessDate)->toDateString();
        $targetWithdrawalMonth = Carbon::parse($targetOperationsAccountingDate)->format('Y-m');

        $updatedOperationsCounts = [
            'sales' => Sale::where('daily_balance_id', $balance->id)->update(['business_date' => $targetOperationsAccountingDate]),
            'expenses' => Expense::where('daily_balance_id', $balance->id)->update(['business_date' => $targetOperationsAccountingDate]),
            'withdrawals' => Withdrawal::where('daily_balance_id', $balance->id)->update([
                'business_date' => $targetOperationsAccountingDate,
                'date' => $targetOperationsAccountingDate,
                'month' => $targetWithdrawalMonth,
            ]),
        ];

        $shiftStartedAt = $balance->start_time ? Carbon::parse($balance->start_time) : null;
        $shiftClosedAt = $balance->end_time ? Carbon::parse($balance->end_time) : null;

        if (! $shiftStartedAt || ! $shiftClosedAt) {
            return $updatedOperationsCounts;
        }

        $linkLegacyOperationsToMovedBalancePayload = [
            'business_date' => $targetOperationsAccountingDate,
            'daily_balance_id' => $balance->id,
        ];

        $updatedOperationsCounts['legacy_sales'] = Sale::where('store_id', $balance->store_id)
            ->whereNull('daily_balance_id')
            ->whereBetween('created_at', [$shiftStartedAt, $shiftClosedAt])
            ->update($linkLegacyOperationsToMovedBalancePayload);

        $updatedOperationsCounts['legacy_expenses'] = Expense::where('store_id', $balance->store_id)
            ->whereNull('daily_balance_id')
            ->whereBetween('created_at', [$shiftStartedAt, $shiftClosedAt])
            ->update($linkLegacyOperationsToMovedBalancePayload);

        // السحوبات القديمة قد تكون مفلترة بتاريخ السحب date بدل created_at، لذلك نحافظ على المسارين.
        $updatedOperationsCounts['legacy_withdrawals'] = Withdrawal::where('store_id', $balance->store_id)
            ->whereNull('daily_balance_id')
            ->where(function ($query) use ($shiftStartedAt, $shiftClosedAt, $sourceBusinessDate) {
                $query->whereBetween('created_at', [$shiftStartedAt, $shiftClosedAt]);

                if ($sourceBusinessDate) {
                    $query->orWhereDate('date', $sourceBusinessDate);
                }
            })
            ->update([
                'business_date' => $targetOperationsAccountingDate,
                'daily_balance_id' => $balance->id,
                'date' => $targetOperationsAccountingDate,
                'month' => $targetWithdrawalMonth,
            ]);

        return $updatedOperationsCounts;
    }
}
