<?php

namespace App\Services\Shifts;

use App\Models\DailyBalance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ShiftWindowService
{
    public function forDate(int $storeId, Carbon $selectedDate): Collection
    {
        $balances = DailyBalance::where('store_id', $storeId)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->where(function ($query) use ($selectedDate) {
                $query->whereDate('business_date', $selectedDate->toDateString())
                    ->orWhere(function ($legacyQuery) use ($selectedDate) {
                        $legacyQuery->whereNull('business_date')
                            ->whereDate('created_at', $selectedDate->toDateString());
                    });
            })
            ->orderBy('start_time')
            ->get();

        $windows = $balances->values()->map(function (DailyBalance $balance, int $index): array {
            return [
                'key' => 'shift_' . $balance->id,
                'balance_id' => (int) $balance->id,
                'business_date' => $balance->business_date ? Carbon::parse($balance->business_date)->toDateString() : null,
                'label' => 'شفت #' . ($index + 1),
                'start' => Carbon::parse($balance->start_time),
                'end' => Carbon::parse($balance->end_time),
                'source' => 'balance',
                'hide_period' => $balance->business_date
                    && Carbon::parse($balance->business_date)->toDateString() !== Carbon::parse($balance->end_time)->toDateString(),
                'notes' => $this->displayableShiftNote($balance->notes),
            ];
        })->values();

        if ($selectedDate->isToday()) {
            $lastClosed = DailyBalance::where('store_id', $storeId)
                ->whereNotNull('end_time')
                ->latest('end_time')
                ->first();

            $openStart = $lastClosed ? Carbon::parse($lastClosed->end_time) : $selectedDate->copy()->startOfDay();
            $openEnd = now();

            if ($openStart->lt($openEnd)) {
                $windows->push([
                    'key' => 'current_open_shift',
                    'label' => 'الشفت الحالي (غير مغلق)',
                    'start' => $openStart,
                    'end' => $openEnd,
                    'source' => 'open_shift',
                    'business_date' => $selectedDate->toDateString(),
                    'notes' => null,
                ]);
            }
        }

        return $windows->sortBy('start')->values();
    }

    public function calendarFallback(Carbon $selectedDate, string $source = 'calendar'): Collection
    {
        return collect([[
            'key' => 'default_shift',
            'label' => 'الفترة اليومية',
            'start' => $selectedDate->copy()->startOfDay(),
            'end' => $selectedDate->copy()->endOfDay(),
            'source' => $source,
            'business_date' => $selectedDate->toDateString(),
        ]]);
    }

    public function applySalePeriodFilter($query, Collection $windows): void
    {
        $query->where(function ($outerQuery) use ($windows) {
            foreach ($windows as $window) {
                if (! empty($window['balance_id'])) {
                    $outerQuery->orWhere('sales.daily_balance_id', (int) $window['balance_id'])
                        ->orWhere(function ($legacyQuery) use ($window) {
                            $legacyQuery->whereNull('sales.daily_balance_id');
                            $this->applyWindowDateFilter($legacyQuery, $window, 'sales');
                        });
                } else {
                    $outerQuery->orWhere(function ($dateQuery) use ($window) {
                        $this->applyWindowDateFilter($dateQuery, $window, 'sales');
                    });
                }
            }
        });
    }

    public function applyOperationWindowFilter($query, array $window): void
    {
        $table = $query->getModel()->getTable();

        $query->where(function ($outerQuery) use ($window, $table) {
            if (! empty($window['balance_id'])) {
                $outerQuery->where($table . '.daily_balance_id', (int) $window['balance_id'])
                    ->orWhere(function ($legacyQuery) use ($window, $table) {
                        $legacyQuery->whereNull($table . '.daily_balance_id');
                        $this->applyWindowDateFilter($legacyQuery, $window, $table);
                    });
            } else {
                $outerQuery->where(function ($dateQuery) use ($window, $table) {
                    $this->applyWindowDateFilter($dateQuery, $window, $table);
                });
            }
        });
    }

    public function resolveShiftKey(object $operation, Collection $windows): string
    {
        $balanceId = (int) ($operation->daily_balance_id ?? 0);
        if ($balanceId > 0) {
            foreach ($windows as $window) {
                if ((int) ($window['balance_id'] ?? 0) === $balanceId) {
                    return $window['key'];
                }
            }
        }

        $createdAt = Carbon::parse($operation->created_at ?? $operation);

        foreach ($windows as $window) {
            if ($createdAt->betweenIncluded($window['start'], $window['end'])) {
                return $window['key'];
            }
        }

        return 'default_shift';
    }

    private function applyWindowDateFilter($query, array $window, string $table): void
    {
        $query->where(function ($dateQuery) use ($window, $table) {
            if (! empty($window['business_date'])) {
                $dateQuery->whereDate($table . '.business_date', $window['business_date'])
                    ->orWhere(function ($noBusinessDateQuery) use ($window, $table) {
                        $noBusinessDateQuery->whereNull($table . '.business_date')
                            ->whereBetween($table . '.created_at', [$window['start'], $window['end']]);
                    });
            } else {
                $dateQuery->whereBetween($table . '.created_at', [$window['start'], $window['end']]);
            }
        });
    }

    private function displayableShiftNote(?string $note): ?string
    {
        $note = trim((string) $note);

        if ($note === '' || str_contains($note, 'نقل تاريخ الشفت')) {
            return null;
        }

        return $note;
    }
}
