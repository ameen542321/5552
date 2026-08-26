<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\LogService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StockMovementDateCorrectionController extends Controller
{
    public function index(Request $request)
    {
        $stores = $this->availableStores();
        $filters = $request->validate([
            'store_id' => ['nullable', 'integer', Rule::in($stores->pluck('id')->all())],
            'note' => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
            'time_mode' => ['nullable', Rule::in(['preserve', 'midnight'])],
            'audit_month' => ['nullable', 'date_format:Y-m'],
        ]);

        $movements = collect();
        $matchingCount = 0;
        $readyToPreview = filled($filters['store_id'] ?? null)
            && filled($filters['note'] ?? null)
            && filled($filters['from_date'] ?? null)
            && filled($filters['to_date'] ?? null);

        if ($readyToPreview) {
            $matchingCount = $this->matchingMovements($filters)->count();
            $movements = $this->matchingMovements($filters)
                ->with('product:id,name')
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(100)
                ->get();
        }

        $auditSales = collect();
        $creditCollections = collect();
        $salesWithMissingProducts = collect();
        $auditReady = filled($filters['store_id'] ?? null) && filled($filters['audit_month'] ?? null);

        if ($auditReady) {
            $monthStart = Carbon::createFromFormat('Y-m', $filters['audit_month'])->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $auditSales = Sale::with('accountant:id,name')
                ->where('store_id', (int) $filters['store_id'])
                ->whereIn('sale_type', ['cash', 'card', 'credit', 'mixed'])
                ->excludeManualInvoiceEntries()
                ->betweenAccountingDates($monthStart, $monthEnd)
                ->orderByRaw('COALESCE(business_date, DATE(created_at)) DESC')
                ->orderByDesc('id')
                ->limit(300)
                ->get(['id', 'accountant_id', 'sale_type', 'final_total', 'total', 'paid_amount', 'cash_amount', 'card_amount', 'remaining_amount', 'has_partial_credit', 'business_date', 'created_at']);

            if (Schema::hasTable('employee_credit_collections')) {
                $creditCollections = DB::table('employee_credit_collections')->where('store_id', (int) $filters['store_id'])
                    ->whereBetween('collection_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->orderByDesc('collection_date')->orderByDesc('id')->limit(200)->get();
            }

            $salesWithMissingProducts = SaleItem::query()
                ->with([
                    'sale:id,store_id,accountant_id,business_date,created_at,final_total,sale_type,description',
                    'sale.accountant:id,name',
                ])
                ->whereHas('sale', fn (Builder $query) => $query
                    ->where('store_id', (int) $filters['store_id'])
                    ->excludeManualInvoiceEntries()
                    ->betweenAccountingDates($monthStart, $monthEnd))
                ->whereDoesntHave('product')
                ->orderByDesc('sale_id')
                ->orderByDesc('id')
                ->limit(300)
                ->get(['id', 'sale_id', 'product_id', 'custom_name', 'quantity', 'unit_type', 'price', 'total', 'created_at']);
        }

        return view('user.tools.stock-movement-date-correction', [
            'stores' => $stores,
            'filters' => $filters,
            'movements' => $movements,
            'matchingCount' => $matchingCount,
            'readyToPreview' => $readyToPreview,
            'auditReady' => $auditReady,
            'auditSales' => $auditSales,
            'creditCollections' => $creditCollections,
            'salesWithMissingProducts' => $salesWithMissingProducts,
        ]);
    }

    public function update(Request $request)
    {
        $stores = $this->availableStores();
        $validated = $request->validate([
            'store_id' => ['required', 'integer', Rule::in($stores->pluck('id')->all())],
            'note' => ['required', 'string', 'max:255'],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'different:from_date'],
            'time_mode' => ['required', Rule::in(['preserve', 'midnight'])],
        ]);

        $updatedCount = DB::transaction(function () use ($validated): int {
            $movements = $this->matchingMovements($validated)
                ->lockForUpdate()
                ->get(['id', 'created_at', 'updated_at']);

            foreach ($movements as $movement) {
                $createdAt = $this->correctedTimestamp($movement->created_at, $validated['to_date'], $validated['time_mode']);
                $updatedAt = $this->correctedTimestamp($movement->updated_at, $validated['to_date'], $validated['time_mode']);

                DB::table('stock_movements')
                    ->where('id', $movement->id)
                    ->update([
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                    ]);
            }

            if ($movements->isNotEmpty()) {
                $store = $this->storeQuery()->findOrFail((int) $validated['store_id']);
                app(LogService::class)->add(
                    'stock_movement_dates_corrected',
                    'تم تصحيح تاريخ حركات مخزون من أداة مؤقتة',
                    $store,
                    [
                        'note' => $validated['note'],
                        'from_date' => $validated['from_date'],
                        'to_date' => $validated['to_date'],
                        'time_mode' => $validated['time_mode'],
                        'updated_count' => $movements->count(),
                    ]
                );
            }

            return $movements->count();
        });

        return redirect()
            ->route('user.tools.stock-movement-dates.index', $validated)
            ->with('success', "تم تعديل تاريخ {$updatedCount} حركة مخزون بنجاح.");
    }

    private function matchingMovements(array $filters): Builder
    {
        return \App\Models\StockMovement::query()
            ->where('store_id', (int) $filters['store_id'])
            ->where('note', $filters['note'])
            ->whereDate('created_at', $filters['from_date'])
            ->whereDate('updated_at', $filters['from_date']);
    }

    private function correctedTimestamp($timestamp, string $targetDate, string $timeMode): Carbon
    {
        $corrected = Carbon::parse($timestamp);
        $target = Carbon::createFromFormat('Y-m-d', $targetDate);
        $corrected->setDate($target->year, $target->month, $target->day);

        return $timeMode === 'midnight' ? $corrected->startOfDay() : $corrected;
    }

    private function availableStores()
    {
        return $this->storeQuery()->orderBy('name')->get(['id', 'name']);
    }

    private function storeQuery()
    {
        $query = Store::query();

        if (auth()->user()->role !== 'admin') {
            $query->where('user_id', auth()->id());
        }

        return $query;
    }
}
