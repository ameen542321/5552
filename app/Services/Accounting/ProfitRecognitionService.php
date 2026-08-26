<?php

namespace App\Services\Accounting;

use App\Models\Sale;

class ProfitRecognitionService
{
    public function forStorePeriod(int $storeId, $startTime, $endTime, array $includedSaleTypes = ['cash', 'card', 'credit', 'mixed']): array
    {
        $query = Sale::where('store_id', $storeId)
            ->whereIn('sale_type', $includedSaleTypes)
            ->whereBetween('created_at', [$startTime, $endTime])
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', '!=', 'manual_invoice_entry');
            });

        return $this->fromSales($query->get([
            'products_total',
            'labor_total',
            'profit',
            'final_total',
            'paid_amount',
            'cash_amount',
            'card_amount',
            'remaining_amount',
        ]));
    }


    public function forStoreAccountingPeriod(int $storeId, $periodStart, $periodEnd, array $includedSaleTypes = ['cash', 'card', 'credit', 'mixed']): array
    {
        $sales = Sale::where('store_id', $storeId)
            ->collectedDashboardSales()
            ->whereIn('sale_type', $includedSaleTypes)
            ->betweenAccountingDates($periodStart, $periodEnd)
            ->get([
                'products_total',
                'labor_total',
                'profit',
                'final_total',
                'paid_amount',
                'cash_amount',
                'card_amount',
                'remaining_amount',
            ]);

        return $this->fromSales($sales);
    }

    public function fromSales($sales): array
    {
        $stats = [
            'recognized_cost' => 0.0,
            'uncovered_cost' => 0.0,
            'recognized_profit' => 0.0,
            'deferred_profit' => 0.0,
        ];

        foreach ($sales as $sale) {
            $totalCost = max(0, (float) (($sale->products_total ?? 0) + ($sale->labor_total ?? 0)) - (float) ($sale->profit ?? 0));
            $operationTotal = max(
                (float) ($sale->final_total ?? 0),
                (float) (($sale->paid_amount ?? 0) + ($sale->remaining_amount ?? 0))
            );
            $collectedAmount = max(
                (float) ($sale->paid_amount ?? 0),
                (float) ($sale->cash_amount ?? 0) + (float) ($sale->card_amount ?? 0)
            );
            $operationProfit = max(0, $operationTotal - $totalCost);
            $recognizedCost = min($totalCost, max(0, $collectedAmount));
            $recognizedProfit = min($operationProfit, max(0, $collectedAmount - $totalCost));

            $stats['recognized_cost'] += $recognizedCost;
            $stats['uncovered_cost'] += max(0, $totalCost - $recognizedCost);
            $stats['recognized_profit'] += $recognizedProfit;
            $stats['deferred_profit'] += max(0, $operationProfit - $recognizedProfit);
        }

        return $stats;
    }
}
