<?php

namespace App\Console\Commands;

use App\Models\DailyBalance;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillShiftAccountingDates extends Command
{
    protected $signature = 'shifts:backfill-accounting-dates
        {--store=* : Store ID. Repeat for multiple stores}
        {--from= : Start business date YYYY-MM-DD}
        {--to= : End business date YYYY-MM-DD}
        {--apply : Apply database changes. Without it, the command is dry-run}
        {--created-at-only : Match legacy operations only by created_at windows}
        {--include-business-date-matches : Also attach unclosed operations whose business_date already equals the balance business_date}
        {--report= : Optional CSV report path under storage/app}';

    protected $description = 'One-time tool to backfill old daily_balances business_date and attach legacy operations to closed shifts.';

    public function handle(): int
    {
        $storeIds = collect($this->option('store'))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($storeIds->isEmpty()) {
            $this->error('At least one --store option is required.');
            return self::FAILURE;
        }

        if (! $this->option('from') || ! $this->option('to')) {
            $this->error('Both --from and --to are required.');
            return self::FAILURE;
        }

        $from = Carbon::parse($this->option('from'))->startOfDay();
        $to = Carbon::parse($this->option('to'))->endOfDay();
        $apply = (bool) $this->option('apply');
        $createdAtOnly = (bool) $this->option('created-at-only');
        $includeBusinessDateMatches = (bool) $this->option('include-business-date-matches');

        if ($from->greaterThan($to)) {
            $this->error('--from must be before or equal to --to.');
            return self::FAILURE;
        }

        $metrics = [
            'balances_scanned' => 0,
            'balances_business_date_updated' => 0,
            'sales_attached' => 0,
            'expenses_attached' => 0,
            'withdrawals_attached' => 0,
            'balances_without_window' => 0,
        ];
        $rows = [];

        DailyBalance::query()
            ->whereIn('store_id', $storeIds)
            ->whereNotNull('end_time')
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('business_date', [$from->toDateString(), $to->toDateString()])
                    ->orWhere(function ($legacyQuery) use ($from, $to) {
                        $legacyQuery->whereNull('business_date')
                            ->whereBetween('start_time', [$from, $to]);
                    })
                    ->orWhereBetween('created_at', [$from, $to]);
            })
            ->orderBy('store_id')
            ->orderBy('start_time')
            ->chunk(100, function ($balances) use (&$metrics, &$rows, $apply, $createdAtOnly, $includeBusinessDateMatches) {
                foreach ($balances as $balance) {
                    $metrics['balances_scanned']++;
                    $businessDate = $this->resolveBusinessDate($balance);
                    $start = $balance->start_time ? Carbon::parse($balance->start_time) : null;
                    $end = $balance->end_time ? Carbon::parse($balance->end_time) : null;

                    if (! $businessDate || ! $start || ! $end) {
                        $metrics['balances_without_window']++;
                        $rows[] = $this->reportRow(
                            $balance,
                            $businessDate,
                            0,
                            0,
                            0,
                            0.0,
                            0.0,
                            0.0,
                            'skipped_missing_window'
                        );
                        continue;
                    }

                    $saleIds = $this->unclosedSalesForBalance($balance, $businessDate, $start, $end, $createdAtOnly, $includeBusinessDateMatches)
                        ->pluck('id');
                    $expenseIds = $this->unclosedExpensesForBalance($balance, $businessDate, $start, $end, $createdAtOnly, $includeBusinessDateMatches)
                        ->pluck('id');
                    $withdrawalIds = $this->unclosedWithdrawalsForBalance($balance, $businessDate, $start, $end, $createdAtOnly, $includeBusinessDateMatches)
                        ->pluck('id');

                    $saleTotal = (float) Sale::whereIn('id', $saleIds)->sum('paid_amount');
                    $expenseTotal = (float) Expense::whereIn('id', $expenseIds)->sum('amount');
                    $withdrawalTotal = (float) Withdrawal::whereIn('id', $withdrawalIds)->sum('amount');

                    if ($apply) {
                        if (! $balance->business_date || Carbon::parse($balance->business_date)->toDateString() !== $businessDate) {
                            $balance->business_date = $businessDate;
                            $balance->save();
                            $metrics['balances_business_date_updated']++;
                        }

                        Sale::whereIn('id', $saleIds)->update(['business_date' => $businessDate, 'daily_balance_id' => $balance->id]);
                        Expense::whereIn('id', $expenseIds)->update(['business_date' => $businessDate, 'daily_balance_id' => $balance->id]);
                        Withdrawal::whereIn('id', $withdrawalIds)->update([
                            'business_date' => $businessDate,
                            'daily_balance_id' => $balance->id,
                            'date' => $businessDate,
                            'month' => Carbon::parse($businessDate)->format('Y-m'),
                        ]);
                    } elseif (! $balance->business_date || Carbon::parse($balance->business_date)->toDateString() !== $businessDate) {
                        $metrics['balances_business_date_updated']++;
                    }

                    $metrics['sales_attached'] += $saleIds->count();
                    $metrics['expenses_attached'] += $expenseIds->count();
                    $metrics['withdrawals_attached'] += $withdrawalIds->count();
                    $rows[] = $this->reportRow(
                        $balance,
                        $businessDate,
                        $saleIds->count(),
                        $expenseIds->count(),
                        $withdrawalIds->count(),
                        $saleTotal,
                        $expenseTotal,
                        $withdrawalTotal,
                        $apply ? 'applied' : 'dry_run'
                    );
                }
            });

        $this->writeReport($rows, $storeIds->all(), $from, $to, $apply);
        $this->table(['Metric', 'Value'], collect($metrics)->map(fn ($value, $key) => [$key, $value])->values()->all());
        $this->info($apply ? 'Apply completed.' : 'Dry-run completed. No database rows were changed.');

        return self::SUCCESS;
    }

    private function unclosedSalesForBalance(DailyBalance $balance, string $businessDate, Carbon $start, Carbon $end, bool $createdAtOnly, bool $includeBusinessDateMatches)
    {
        return Sale::where('store_id', $balance->store_id)
            ->whereNull('daily_balance_id')
            ->where(function ($query) use ($start, $end, $businessDate, $createdAtOnly, $includeBusinessDateMatches) {
                $query->whereBetween('created_at', [$start, $end]);

                if (! $createdAtOnly && $includeBusinessDateMatches) {
                    $query->orWhereDate('business_date', $businessDate);
                }
            });
    }

    private function unclosedExpensesForBalance(DailyBalance $balance, string $businessDate, Carbon $start, Carbon $end, bool $createdAtOnly, bool $includeBusinessDateMatches)
    {
        return Expense::where('store_id', $balance->store_id)
            ->whereNull('daily_balance_id')
            ->where(function ($query) use ($start, $end, $businessDate, $createdAtOnly, $includeBusinessDateMatches) {
                $query->whereBetween('created_at', [$start, $end]);

                if (! $createdAtOnly && $includeBusinessDateMatches) {
                    $query->orWhereDate('business_date', $businessDate);
                }
            });
    }

    private function unclosedWithdrawalsForBalance(DailyBalance $balance, string $businessDate, Carbon $start, Carbon $end, bool $createdAtOnly, bool $includeBusinessDateMatches)
    {
        return Withdrawal::where('store_id', $balance->store_id)
            ->whereNull('daily_balance_id')
            ->where(function ($query) use ($start, $end, $businessDate, $createdAtOnly, $includeBusinessDateMatches) {
                $query->whereBetween('created_at', [$start, $end]);

                if (! $createdAtOnly) {
                    // السحوبات القديمة كان لها تاريخ تشغيلي مستقل يختاره المستخدم.
                    // لذلك نطابق employee_withdrawals.date أيضًا، لأن created_at قد يكون يوم الإدخال فقط.
                    $query->orWhereDate('date', $businessDate);

                    if ($includeBusinessDateMatches) {
                        $query->orWhereDate('business_date', $businessDate);
                    }
                }
            });
    }

    private function resolveBusinessDate(DailyBalance $balance): ?string
    {
        if ($balance->business_date) {
            return Carbon::parse($balance->business_date)->toDateString();
        }

        return $balance->start_time ? Carbon::parse($balance->start_time)->toDateString() : null;
    }

    private function reportRow(
        DailyBalance $balance,
        ?string $businessDate,
        int $sales,
        int $expenses,
        int $withdrawals,
        float $saleTotal,
        float $expenseTotal,
        float $withdrawalTotal,
        string $status
    ): array
    {
        return [
            'status' => $status,
            'store_id' => $balance->store_id,
            'daily_balance_id' => $balance->id,
            'current_business_date' => $balance->business_date
                ? Carbon::parse($balance->business_date)->toDateString()
                : '',
            'target_business_date' => $businessDate,
            'start_time' => $balance->start_time
                ? Carbon::parse($balance->start_time)->toDateTimeString()
                : '',
            'end_time' => $balance->end_time
                ? Carbon::parse($balance->end_time)->toDateTimeString()
                : '',
            'system_sales_total' => number_format((float) $balance->system_sales_total, 2, '.', ''),
            'sales_to_attach' => $sales,
            'sales_paid_total_to_attach' => number_format($saleTotal, 2, '.', ''),
            'expenses_to_attach' => $expenses,
            'expenses_total_to_attach' => number_format($expenseTotal, 2, '.', ''),
            'withdrawals_to_attach' => $withdrawals,
            'withdrawals_total_to_attach' => number_format($withdrawalTotal, 2, '.', ''),
        ];
    }

    private function writeReport(array $rows, array $storeIds, Carbon $from, Carbon $to, bool $apply): void
    {
        $path = $this->option('report') ?: sprintf(
            'reports/shift-accounting-backfill-%s-stores-%s-%s-to-%s.csv',
            $apply ? 'apply' : 'dry-run',
            implode('-', $storeIds),
            $from->toDateString(),
            $to->toDateString()
        );

        $handle = fopen('php://temp', 'r+');
        $headers = [
            'status',
            'store_id',
            'daily_balance_id',
            'current_business_date',
            'target_business_date',
            'start_time',
            'end_time',
            'system_sales_total',
            'sales_to_attach',
            'sales_paid_total_to_attach',
            'expenses_to_attach',
            'expenses_total_to_attach',
            'withdrawals_to_attach',
            'withdrawals_total_to_attach',
        ];
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($header) => $row[$header] ?? '', $headers));
        }
        rewind($handle);
        Storage::put($path, stream_get_contents($handle));
        fclose($handle);

        $this->info('Report: storage/app/' . $path);
    }
}
