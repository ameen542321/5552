<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\InventoryLog;
use App\Models\Store;
use App\Models\StockMovement;
use App\Services\ShiftLifecycleService;
use App\Services\SupportSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductStockController extends Controller
{
    /**
     * صفحة إدارة المخزون
     */
    public function index(Store $store, Product $product)
    {
        $this->ensureProductBelongsToStore($store, $product);

        $this->ensureProductAllowsInventory($product);

        $movements = $product->stockMovements()
            ->orderByRaw('COALESCE(business_date, DATE(created_at)) DESC')
            ->orderByDesc('created_at')
            ->paginate(25);

        $inventoryAuditStatus = $product->inventoryAuditStatus($store);
        $latestInventoryAudit = $product->inventoryLogs()
            ->where('type', Product::INVENTORY_AUDIT_CONFIRMED_TYPE)
            ->with('inventoryCountSessionItem.session')
            ->latest()
            ->first();
        $inventoryCountHistory = $product->inventoryLogs()
            ->where('type', Product::INVENTORY_AUDIT_CONFIRMED_TYPE)
            ->with('inventoryCountSessionItem.session')
            ->latest()
            ->limit(10)
            ->get();
        $currentBusinessDate = app(ShiftLifecycleService::class)->currentShiftContext($store->id)['business_date'];
        $isTechnicalSupport = app(SupportSessionService::class)->active() !== null;
        $monthStart = \Carbon\Carbon::parse($currentBusinessDate)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $monthlyConfirmation = $product->inventoryLogs()
            ->where('type', Product::INVENTORY_AUDIT_CONFIRMED_TYPE)
            ->whereBetween('business_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->latest('business_date')
            ->first();
        $supportCancelableConfirmation = $isTechnicalSupport
            ? $product->inventoryLogs()
                ->where('type', Product::INVENTORY_AUDIT_CONFIRMED_TYPE)
                ->whereDate('business_date', '>=', $monthStart->copy()->subMonth()->toDateString())
                ->whereDate('business_date', '<=', $monthEnd->toDateString())
                ->latest('business_date')
                ->first()
            : null;
        $canConfirmAudit = $inventoryAuditStatus['can_confirm'] && ($isTechnicalSupport || ! $monthlyConfirmation);
        $canCancelAudit = (bool) ($isTechnicalSupport ? $supportCancelableConfirmation : $monthlyConfirmation);
        $stockReturnUrl = route('user.stores.products.index', $store->id);
        if (request('return_to') === 'audit') {
            $stockReturnUrl = route('user.stores.products.audit', $store->id);
        } elseif (request('return_to') === 'inventory-count' && request()->filled('inventory_count')) {
            $stockReturnUrl = route('user.stores.inventory-counts.show', [$store->id, request('inventory_count')]);
        }

        return view('user.stores.products.stock.index', compact('store', 'product', 'movements', 'inventoryAuditStatus', 'latestInventoryAudit', 'inventoryCountHistory', 'currentBusinessDate', 'isTechnicalSupport', 'canConfirmAudit', 'canCancelAudit', 'stockReturnUrl'));
    }

    public function confirmAudit(Request $request, Store $store, Product $product)
    {
        $this->ensureProductBelongsToStore($store, $product);

        $this->ensureProductAllowsInventory($product);

        $status = $product->inventoryAuditStatus($store);

        if (! $status['can_confirm']) {
            return back()->with($status['color'] === 'green' ? 'success' : 'error', $status['message']);
        }

        $validated = $request->validate([
            'audit_note' => 'nullable|string|max:255',
            'business_date' => 'nullable|date_format:Y-m-d',
        ]);
        $isTechnicalSupport = app(SupportSessionService::class)->active($request) !== null;
        $currentBusinessDate = app(ShiftLifecycleService::class)->currentShiftContext($store->id)['business_date'];
        $businessDate = $validated['business_date'] ?? $currentBusinessDate;
        $monthStart = \Carbon\Carbon::parse($businessDate)->startOfMonth();
        if (! $isTechnicalSupport && $product->inventoryLogs()
            ->where('type', Product::INVENTORY_AUDIT_CONFIRMED_TYPE)
            ->whereBetween('business_date', [$monthStart->toDateString(), $monthStart->copy()->endOfMonth()->toDateString()])
            ->exists()) {
            return back()->with('error', 'تم تأكيد جرد هذا المنتج مرة خلال الشهر الحالي بالفعل.');
        }
        $auditNote = trim((string) ($validated['audit_note'] ?? ''));
        $movementNote = 'تأكيد جرد المنتج' . ($auditNote !== '' ? ' — ' . $auditNote : '');

        InventoryLog::create([
            'store_id' => $store->id,
            'user_id' => auth()->id() ?? $store->user_id,
            'product_id' => $product->id,
            'quantity_change' => 0,
            'type' => Product::INVENTORY_AUDIT_CONFIRMED_TYPE,
            'business_date' => $businessDate,
        ]);

        $balance = (float) $product->getRawOriginal('quantity');
        StockMovement::recordForProduct(
            $product,
            'increase',
            0,
            $balance,
            $balance,
            auth()->id() ?? $store->user_id,
            $movementNote,
            0,
            'normalized',
            $businessDate
        );

        return back()->with('success', 'تم تأكيد جرد المنتج لهذه الدورة بنجاح.');
    }

    public function cancelAuditConfirmation(Store $store, Product $product)
    {
        $this->ensureProductBelongsToStore($store, $product);
        $this->ensureProductAllowsInventory($product);

        $businessDate = app(ShiftLifecycleService::class)->currentShiftContext($store->id)['business_date'];
        $monthStart = \Carbon\Carbon::parse($businessDate)->startOfMonth();
        $isTechnicalSupport = app(SupportSessionService::class)->active() !== null;
        $confirmation = $product->inventoryLogs()
            ->where('type', Product::INVENTORY_AUDIT_CONFIRMED_TYPE)
            ->whereDate('business_date', '>=', ($isTechnicalSupport ? $monthStart->copy()->subMonth() : $monthStart)->toDateString())
            ->whereDate('business_date', '<=', $monthStart->copy()->endOfMonth()->toDateString())
            ->latest('business_date')
            ->first();
        if (! $confirmation) {
            return back()->with('error', 'لا يوجد تأكيد جرد لإلغائه.');
        }

        DB::transaction(function () use ($product, $confirmation): void {
            $this->deleteAuditMovement($product, $confirmation);
            $confirmation->delete();
        });

        return back()->with('success', 'تم إلغاء تأكيد الجرد.');
    }

    private function currentAuditConfirmations(Store $store, Product $product)
    {
        $cycleStart = Product::inventoryAuditCycleStart($store);

        return $product->inventoryLogs()
            ->where('type', Product::INVENTORY_AUDIT_CONFIRMED_TYPE)
            ->where(function ($query) use ($cycleStart): void {
                $query->whereDate('business_date', '>=', $cycleStart->toDateString())
                    ->orWhere(fn ($legacy) => $legacy->whereNull('business_date')->where('created_at', '>=', $cycleStart));
            });
    }

    private function deleteAuditMovement(Product $product, InventoryLog $confirmation): void
    {
        $product->stockMovements()
            ->where('quantity', 0)
            ->where('note', 'like', 'تأكيد جرد المنتج%')
            ->when($confirmation->business_date, fn ($query, $date) => $query->whereDate('business_date', $date->toDateString()))
            ->latest()
            ->first()?->delete();
    }

    /**
     * زيادة المخزون
     */
    public function increase(Request $request, Store $store, Product $product)
    {
        $this->ensureProductBelongsToStore($store, $product);

        $this->ensureProductAllowsInventory($product);

        $request->validate([
            'quantity'  => 'required|numeric|min:0.001',
            'unit_type' => 'nullable|in:unit,piece,roll,meter,meters',
            'note'      => 'nullable|string|max:255',
            'business_date' => 'nullable|date_format:Y-m-d',
        ], $this->stockValidationMessages());

        $unitType = $this->resolveStockUnitType($request, $product);
        $businessDate = $request->input('business_date')
            ?: app(ShiftLifecycleService::class)->currentShiftContext($store->id)['business_date'];

        DB::transaction(function () use ($request, $store, $product, $unitType, $businessDate) {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $this->ensureProductBelongsToStore($store, $lockedProduct);

            // نجعل الموديل هو المرجع الوحيد لتحويل الوحدة أثناء الزيادة
            $lockedProduct->increaseStock(
                (float) $request->quantity,
                $request->note,
                auth()->id(),
                $unitType,
                $businessDate
            );
        });

        return back()->with('success', 'تمت زيادة المخزون بنجاح');
    }

    /**
     * خصم المخزون
     */
    public function decrease(Request $request, Store $store, Product $product)
    {
        $this->ensureProductBelongsToStore($store, $product);

        $this->ensureProductAllowsInventory($product);

        $request->validate([
            'quantity'  => 'required|numeric|min:0.01',
            'unit_type' => 'nullable|in:unit,piece,roll,meter,meters',
            'note'      => 'nullable|string|max:255',
            'business_date' => 'nullable|date_format:Y-m-d',
        ], $this->stockValidationMessages());

        if ((float) $product->getRawOriginal('quantity') <= 0) {
            return back()->withErrors(['quantity' => 'رصيد المنتج الحالي صفر، ولا توجد كمية متاحة للسحب.'])->withInput();
        }

        $rawQuantity = (float) $request->quantity;
        $unitType = $this->resolveStockUnitType($request, $product);
        $businessDate = $request->input('business_date')
            ?: app(ShiftLifecycleService::class)->currentShiftContext($store->id)['business_date'];

        $deducted = DB::transaction(function () use ($request, $store, $product, $rawQuantity, $unitType, $businessDate) {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $this->ensureProductBelongsToStore($store, $lockedProduct);

            // نستخدم نفس دالة التحويل المركزية الموجودة في الموديل
            // حتى لا يختلف التحقق المسبق عن الخصم الفعلي لاحقاً.
            $actualAmountToDeduct = $lockedProduct->normalizeQuantityByUnit($rawQuantity, $unitType);

            if ($actualAmountToDeduct > (float) $lockedProduct->getRawOriginal('quantity')) {
                return false;
            }

            $lockedProduct->decreaseStock($rawQuantity, $request->note, auth()->id(), $unitType, $businessDate);

            return true;
        });

        if (!$deducted) {
            return back()->withErrors(['quantity' => 'الكمية المتوفرة لا تكفي'])->withInput();
        }

        return back()->with('success', 'تم خصم الكمية من المخزن بنجاح');
    }


    private function stockValidationMessages(): array
    {
        return [
            'quantity.required' => 'يرجى إدخال الكمية.',
            'quantity.numeric' => 'الكمية يجب أن تكون رقمًا صحيحًا.',
            'quantity.min' => 'الكمية يجب أن تكون أكبر من صفر.',
            'unit_type.in' => 'وحدة المخزون المختارة غير صحيحة.',
            'note.max' => 'الملاحظة يجب ألا تتجاوز 255 حرفًا.',
            'business_date.date_format' => 'يجب إدخال التاريخ بصيغة صحيحة.',
        ];
    }

    private function resolveStockUnitType(Request $request, Product $product): string
    {
        if ($request->filled('unit_type')) {
            return (string) $request->unit_type;
        }

        return $product->product_type === 'fractional' ? 'roll' : 'unit';
    }

    private function ensureProductAllowsInventory(Product $product): void
    {
        if ($product->isOwnerPurchaseOnly()) {
            abort(404, 'منتجات مشتريات المالك لا تدخل في الجرد أو إدارة المخزون.');
        }
    }

    private function ensureProductBelongsToStore(Store $store, Product $product): void
    {
        if ((int) $product->store_id !== (int) $store->id) {
            abort(404);
        }
    }

}
