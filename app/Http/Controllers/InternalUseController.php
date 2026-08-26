<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Products\ProductSearchService;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\ShiftLifecycleService;
use App\Services\Accounting\FinancialSummaryService;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use App\Models\ArchivedItem;
use App\Services\AdministrativeArchiveService;
use App\Services\SupportSessionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\ArabicPdf;

class InternalUseController extends Controller
{
    /**
     * عرض صفحة إنشاء استهلاك داخلي (للمحاسب فقط)
     */
    public function create()
    {
        $accountant = Auth::guard('accountant')->user();

        if (!$accountant) {
            abort(403, 'هذه الصفحة مخصصة للمحاسبين فقط');
        }

        $storeId = $accountant->store_id;

        $products = Product::where('store_id', $storeId)
            ->where('status', 'active')
            ->sellable()
            ->withoutTintCategory()
            ->select(
                'id',
                'name',
                'description',
                'cost_price',
                'quantity',
                'barcode',
                'product_type',
                'is_splittable',
                'items_per_unit',
                'roll_length',
                'waste_percentage',
                'piece_price'
            )
            ->get();

        $fractions = DB::table('product_fractions')
            ->whereIn('product_id', $products->pluck('id'))
            ->get();

        return view('cashier.internal-use.create', compact('products', 'fractions'));
    }

    /**
     * تخزين عملية استهلاك داخلي (للمحاسب فقط)
     */
    public function store(Request $request)
{
    $accountant = Auth::guard('accountant')->user();

    if (!$accountant) {
        abort(403, 'هذه الصفحة مخصصة للمحاسبين فقط');
    }

    $request->validate([
        'product_id' => 'required|exists:products,id',
        'quantity' => 'required|numeric|min:0.01',
        'unit_type' => 'nullable|in:default,meters,roll,piece,kit',
        'reason' => 'nullable|string|max:255',
        'internal_notes' => 'nullable|string|max:500',
    ]);

    $storeId = $accountant->store_id;

    // جلب الـ user_id الخاص بصاحب المتجر
    $store = DB::table('stores')->where('id', $storeId)->first();
    $userId = $store->user_id;  // هذا هو ID المالك في جدول users

    $product = Product::where('id', $request->product_id)
        ->where('store_id', $storeId)
        ->where('status', 'active')
        ->sellable()
        ->withoutTintCategory()
        ->first();

    if (!$product) {
        return redirect()->back()->with('error', 'المنتج غير موجود');
    }

    $unitType = $request->unit_type ?? 'default';

    DB::beginTransaction();
    try {
        $product = Product::where('id', $request->product_id)
            ->where('store_id', $storeId)
            ->where('status', 'active')
            ->sellable()
            ->withoutTintCategory()
            ->lockForUpdate()
            ->firstOrFail();

        $actualDeduction = $this->calculateActualDeduction($product, $request->quantity, $unitType);
        if ((float) $product->quantity < $actualDeduction) {
            DB::rollBack();
            return redirect()->back()->with('error', 'الكمية غير كافية في المخزون');
        }

        // حساب تكلفة الاستهلاك الداخلية من تكلفة المنتج وليس سعر البيع.
        $unitPrice = $this->internalUseUnitCost($product, $unitType);
        $totalCost = round($unitPrice * (float) $request->quantity, 2);

        // ✅ تجهيز الملاحظات مع إضافة اسم المحاسب
        $reason = $request->reason ?? 'استهلاك داخلي';
        $internalNotes = $request->internal_notes;

        // إضافة اسم المحاسب إلى الملاحظات الداخلية
        $accountantName = $accountant->name ?? 'محاسب';
        $internalNotesWithAccountant = "($accountantName) " . ($internalNotes ?? '');

        // ✅ دمج الملاحظات للعرض في سجل المخزون
        $finalNotes = $reason;
        if ($internalNotesWithAccountant) {
            $finalNotes .= ' | ' . $internalNotesWithAccountant;
        }

        $shiftContext = app(ShiftLifecycleService::class)->currentShiftContext($storeId);

        // إنشاء سجل البيع
        $sale = Sale::create([
            'store_id'        => $storeId,
            'accountant_id'   => $accountant->id,
            'user_id'         => $userId,  // ✅ إضافة user_id الخاص بالمالك
            'total'           => $totalCost,
            'paid_amount'     => $totalCost,
            'sale_type'       => 'internal_use',
            'internal_notes'  => $internalNotesWithAccountant,  // ✅ مع اسم المحاسب
            'business_date'   => $shiftContext['business_date'],
            'daily_balance_id' => $shiftContext['daily_balance_id'],
        ]);

        // إنشاء عنصر البيع
        $saleItemPayload = [
            'sale_id'     => $sale->id,
            'product_id'  => $product->id,
            'quantity'    => $request->quantity,
            'price'       => $unitPrice,
            'total'       => $totalCost,
        ];

        if ($this->supportsSaleItemUnitType()) {
            $saleItemPayload['unit_type'] = $unitType;
        }

        SaleItem::create($saleItemPayload);

        // ✅ خصم المخزون باستخدام user_id الخاص بالمالك
        $product->decreaseStock(
            $actualDeduction,
            'استهلاك داخلي: ' . $finalNotes,
            $userId,  // ✅ user_id صحيح من جدول users
            'normalized'
        );

        DB::commit();

        $unitName = $this->getUnitName($unitType);
        $message = "✅ تم خصم {$request->quantity} {$unitName} من المخزون بواسطة {$accountantName}";

        return redirect()->back()->with('success', $message);

    } catch (\Exception $e) {
        DB::rollBack();
        return redirect()->back()->with('error', '❌ حدث خطأ: ' . $e->getMessage());
    }
}


    /**
     * حساب تكلفة وحدة الاستهلاك الداخلي حسب وحدة الإدخال بدون استخدام سعر البيع.
     */
    private function internalUseUnitCost(Product $product, string $unitType): float
    {
        $costPrice = (float) $product->cost_price;

        if ($product->product_type === 'fractional' && in_array($unitType, ['meter', 'meters'], true)) {
            $rollLength = (float) $product->roll_length;

            return $rollLength > 0 ? round($costPrice / $rollLength, 4) : $costPrice;
        }

        if ($product->is_splittable && $unitType === 'piece') {
            $itemsPerUnit = (int) $product->items_per_unit;

            return $itemsPerUnit > 0 ? round($costPrice / $itemsPerUnit, 4) : $costPrice;
        }

        return $costPrice;
    }

    /**
     * عرض صفحة مشتريات المالك واستهلاك المحاسب (للمالك - تعرض HTML)
     */
    public function reportView(Request $request, $storeId)
    {
        $user = Auth::user();

        if (!$user) {
            abort(401);
        }

        $store = DB::table('stores')
            ->where('id', $storeId)
            ->where('user_id', $user->id)
            ->first();

        if (!$store) {
            abort(403, 'غير مصرح لك بالوصول لهذا المتجر');
        }

        $month = max(1, min(12, (int) $request->get('month', now()->month)));
        $year = max(2020, min(2100, (int) $request->get('year', now()->year)));

        $search = trim((string) $request->get('search', ''));
        $reportData = $this->buildMonthlyConsumptionData((int) $storeId, $month, $year);
        $allRecords = collect($reportData['records']);

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $allRecords = $allRecords->filter(function ($row) use ($needle) {
                return str_contains(mb_strtolower((string) ($row['type'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($row['description'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($row['source'] ?? '')), $needle);
            })->values();
        }

        $ownerPurchaseGroups = $this->buildOwnerPurchaseGroupsFromRecords($allRecords);
        $perPage = max(10, min(50, (int) $request->get('per_page', 20)));
        $page = LengthAwarePaginator::resolveCurrentPage();
        $reportData['records'] = new LengthAwarePaginator(
            $allRecords->forPage($page, $perPage)->values(),
            $allRecords->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        $ownerPurchaseTypeOptions = array_values($this->ownerPurchaseGroupingMap());
        $defaultBusinessDate = now()->toDateString();

        return view('cashier.internal-use.report', compact(
            'storeId',
            'store',
            'month',
            'year',
            'reportData',
            'ownerPurchaseGroups',
            'ownerPurchaseTypeOptions',
            'search',
            'defaultBusinessDate'
        ));
    }

    public function storeByOwner(Request $request, $storeId)
    {
        $owner = Auth::user();
        if (!$owner) {
            abort(401);
        }

        $store = DB::table('stores')
            ->where('id', $storeId)
            ->where('user_id', $owner->id)
            ->first();

        if (!$store) {
            abort(403, 'غير مصرح لك بالوصول لهذا المتجر');
        }

        $validated = $request->validate([
            'type' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'business_date' => 'required|date|before_or_equal:today',
        ]);

        $description = trim((string) ($validated['description'] ?? ''));

        Purchase::create([
            'store_id' => $storeId,
            'user_id' => $owner->id,
            'product_id' => null,
            'purchase_name' => trim($validated['type']),
            'quantity' => 1,
            'cost' => (float) $validated['amount'],
            'description' => $description !== '' ? $description : null,
            'business_date' => $validated['business_date'],
        ]);

        return redirect()->back()->with('success', 'تم تسجيل مشتريات استهلاك المالك بنجاح');
    }

    public function updateOwnerPurchase(Request $request, $storeId, $purchaseId)
    {
        $owner = Auth::user();
        if (!$owner) {
            abort(401);
        }

        $store = DB::table('stores')
            ->where('id', $storeId)
            ->where('user_id', $owner->id)
            ->first();

        if (!$store) {
            abort(403, 'غير مصرح لك بالوصول لهذا المتجر');
        }

        $purchase = Purchase::where('id', $purchaseId)
            ->where('store_id', $storeId)
            ->firstOrFail();

        $validated = $request->validate([
            'type' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'business_date' => 'required|date|before_or_equal:today',
        ]);

        $description = trim((string) ($validated['description'] ?? ''));

        $purchase->update([
            'purchase_name' => trim($validated['type']),
            'cost' => (float) $validated['amount'],
            'description' => $description !== '' ? $description : null,
            'business_date' => $validated['business_date'],
        ]);

        return redirect()->back()->with('success', 'تم تعديل مشتريات المالك بنجاح');
    }

    public function destroyOwnerPurchase($storeId, $purchaseId)
    {
        $owner = Auth::user();
        if (!$owner) {
            abort(401);
        }

        $store = DB::table('stores')
            ->where('id', $storeId)
            ->where('user_id', $owner->id)
            ->first();

        if (!$store) {
            abort(403, 'غير مصرح لك بالوصول لهذا المتجر');
        }

        $purchase = Purchase::withTrashed()
            ->where('id', $purchaseId)
            ->where('store_id', $storeId)
            ->first();

        if (! $purchase) {
            return redirect()->back()->with('error', 'تعذر حذف العملية: السجل غير موجود.');
        }

        if ($purchase->trashed()) {
            return redirect()->back()->with('success', 'العملية محذوفة مسبقاً وتم تحديث التقرير.');
        }

        $purchase->delete();

        return redirect()->back()->with('success', 'تم حذف مشتريات المالك بنجاح');
    }



    public function trashOwnerPurchases(Request $request, $storeId)
    {
        $owner = Auth::user();
        if (!$owner) {
            abort(401);
        }

        $store = DB::table('stores')
            ->where('id', $storeId)
            ->where('user_id', $owner->id)
            ->first();

        if (!$store) {
            abort(403, 'غير مصرح لك بالوصول لهذا المتجر');
        }

        $query = Purchase::onlyTrashed()->where('store_id', $storeId);
        if (! app(SupportSessionService::class)->active()) {
            $query->whereNotIn('id', app(AdministrativeArchiveService::class)->archivedIds(Purchase::class));
        }
        $deletedPurchases = $query->with('archivedItem')
            ->latest('deleted_at')
            ->paginate(20)
            ->withQueryString();
        $technicalSupportSession = app(SupportSessionService::class)->active($request);

        return view('cashier.internal-use.trash', compact('storeId', 'store', 'deletedPurchases', 'technicalSupportSession'));
    }

    public function restoreOwnerPurchase($storeId, $purchaseId)
    {
        $owner = Auth::user();
        if (!$owner) {
            abort(401);
        }

        $store = DB::table('stores')
            ->where('id', $storeId)
            ->where('user_id', $owner->id)
            ->first();

        if (!$store) {
            abort(403, 'غير مصرح لك بالوصول لهذا المتجر');
        }

        $purchase = Purchase::onlyTrashed()
            ->where('id', $purchaseId)
            ->where('store_id', $storeId)
            ->firstOrFail();

        $archive = app(AdministrativeArchiveService::class)->activeFor($purchase, $purchase->id);
        if ($archive) {
            abort_unless(app(SupportSessionService::class)->active(), 404);
            DB::transaction(function () use ($purchase, $archive) {
                Purchase::withTrashed()->lockForUpdate()->findOrFail($purchase->id)->restore();
                ArchivedItem::lockForUpdate()->findOrFail($archive->id)->update([
                    'status' => 'restored', 'restored_at' => now(),
                    'restored_by' => app(SupportSessionService::class)->active()?->admin_id,
                    'admin_message' => 'تمت استعادة مشتريات المالك بواسطة الدعم التقني.',
                ]);
            });

            return redirect()->route('user.stores.internal-use.trash', $storeId)
                ->with('success', 'تم استعادة مشتريات المالك بواسطة الدعم التقني.');
        }

        $purchase->restore();

        return redirect()
            ->route('user.stores.internal-use.trash', $storeId)
            ->with('success', 'تم استعادة مشتريات المالك بنجاح.');
    }

    public function forceDeleteOwnerPurchase($storeId, $purchaseId)
    {
        $owner = Auth::user();
        if (!$owner) {
            abort(401);
        }

        $store = DB::table('stores')
            ->where('id', $storeId)
            ->where('user_id', $owner->id)
            ->first();

        if (!$store) {
            abort(403, 'غير مصرح لك بالوصول لهذا المتجر');
        }

        $purchase = Purchase::onlyTrashed()
            ->where('id', $purchaseId)
            ->where('store_id', $storeId)
            ->firstOrFail();

        $archiveService = app(AdministrativeArchiveService::class);
        $archive = $archiveService->activeFor($purchase, $purchase->id);
        $support = app(SupportSessionService::class)->active();
        if (! $archive) {
            $archive = $archiveService->archive(
                $purchase,
                $owner->id,
                (int) $storeId,
                $purchase->purchase_name ?: 'مشتريات المالك #' . $purchase->id
            );
        }

        if (! $support) {
            return redirect()->route('user.stores.internal-use.trash', $storeId)
                ->with('success', 'تم حذف العملية نهائيًا من حسابك. يمكن طلب استعادتها من الدعم التقني خلال 30 يومًا.');
        }

        abort_unless($support?->target_role === 'owner', 403);
        DB::transaction(function () use ($purchase, $archive, $support) {
            Purchase::onlyTrashed()->whereKey($purchase->id)->lockForUpdate()->firstOrFail()->forceDelete();
            ArchivedItem::whereKey($archive->id)->lockForUpdate()->firstOrFail()->update([
                'status' => 'purged',
                'restored_by' => $support->admin_id,
                'admin_message' => 'حُذفت عملية مشتريات المالك نهائيًا بواسطة الدعم التقني.',
            ]);
        });

        return redirect()
            ->route('user.stores.internal-use.trash', $storeId)
            ->with('success', 'حذف الدعم التقني عملية مشتريات المالك نهائيًا.');
    }

    public function updateAccountantConsumption(Request $request, $storeId, $saleId)
    {
        $owner = Auth::user();
        if (!$owner) {
            abort(401);
        }

        $store = DB::table('stores')
            ->where('id', $storeId)
            ->where('user_id', $owner->id)
            ->first();

        if (!$store) {
            abort(403, 'غير مصرح لك بالوصول لهذا المتجر');
        }

        $sale = Sale::where('id', $saleId)
            ->where('store_id', $storeId)
            ->where('sale_type', 'internal_use')
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', '!=', 'manual_invoice_entry');
            })
            ->firstOrFail();

        $saleItem = SaleItem::where('sale_id', $sale->id)->firstOrFail();

        $product = Product::where('id', $saleItem->product_id)
            ->where('store_id', $storeId)
            ->sellable()
            ->withoutTintCategory()
            ->firstOrFail();

        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'unit_type' => 'nullable|in:default,meters,roll,piece,kit',
            'internal_notes' => 'nullable|string|max:500',
        ]);

        $currentUnitType = $this->supportsSaleItemUnitType() ? ($saleItem->unit_type ?: 'default') : 'default';
        $unitType = $validated['unit_type'] ?? $currentUnitType;
        $newQuantity = (float) $validated['quantity'];

        DB::beginTransaction();
        try {
            $product = Product::where('id', $saleItem->product_id)
                ->where('store_id', $storeId)
                ->sellable()
                ->withoutTintCategory()
                ->lockForUpdate()
                ->firstOrFail();

            $oldDeduction = $this->calculateActualDeduction($product, (float) $saleItem->quantity, $currentUnitType);
            $newDeduction = $this->calculateActualDeduction($product, $newQuantity, $unitType);

            $requiredExtra = $newDeduction - $oldDeduction;
            if ($requiredExtra > 0 && (float) $product->quantity < $requiredExtra) {
                DB::rollBack();
                return redirect()->back()->with('error', 'الكمية غير كافية في المخزون لتعديل استهلاك المحاسب');
            }

            if ($requiredExtra > 0) {
                $product->decreaseStock(
                    $requiredExtra,
                    'تعديل استهلاك محاسب (زيادة الخصم) - عملية #' . $sale->id,
                    $owner->id,
                    'normalized'
                );
            } elseif ($requiredExtra < 0) {
                $product->increaseStock(
                    abs($requiredExtra),
                    'تعديل استهلاك محاسب (استرجاع مخزون) - عملية #' . $sale->id,
                    $owner->id
                );
            }

            $unitPrice = $this->internalUseUnitCost($product, $unitType);
            $newTotal = round($unitPrice * $newQuantity, 2);

            $saleItemPayload = [
                'quantity' => $newQuantity,
                'price' => $unitPrice,
                'total' => $newTotal,
            ];
            if ($this->supportsSaleItemUnitType()) {
                $saleItemPayload['unit_type'] = $unitType;
            }

            $saleItem->update($saleItemPayload);

            $notes = trim((string) ($validated['internal_notes'] ?? ''));
            $sale->update([
                'total' => $newTotal,
                'paid_amount' => $newTotal,
                'remaining_amount' => 0,
                'internal_notes' => $notes !== '' ? $notes : null,
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'تم تعديل استهلاك المحاسب بنجاح');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'تعذر تعديل استهلاك المحاسب: ' . $e->getMessage());
        }
    }

    public function destroyAccountantConsumption($storeId, $saleId)
    {
        $owner = Auth::user();
        if (!$owner) {
            abort(401);
        }

        $store = DB::table('stores')
            ->where('id', $storeId)
            ->where('user_id', $owner->id)
            ->first();

        if (!$store) {
            abort(403, 'غير مصرح لك بالوصول لهذا المتجر');
        }

        $sale = Sale::where('id', $saleId)
            ->where('store_id', $storeId)
            ->where('sale_type', 'internal_use')
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', '!=', 'manual_invoice_entry');
            })
            ->first();

        if (! $sale) {
            return redirect()->back()->with('error', 'تعذر حذف العملية: السجل غير موجود أو تم حذفه مسبقاً.');
        }

        $saleItems = SaleItem::where('sale_id', $sale->id)->get();

        DB::beginTransaction();
        try {
            foreach ($saleItems as $item) {
                $product = Product::where('id', $item->product_id)
                    ->where('store_id', $storeId)
                    ->sellable()
                    ->withoutTintCategory()
                    ->lockForUpdate()
                    ->first();

                if ($product) {
                    $itemUnitType = $this->supportsSaleItemUnitType() ? ($item->unit_type ?: 'default') : 'default';
                    $deduction = $this->calculateActualDeduction($product, (float) $item->quantity, $itemUnitType);
                    $product->increaseStock(
                        $deduction,
                        'حذف استهلاك محاسب (استرجاع مخزون) - عملية #' . $sale->id,
                        $owner->id,
                        'normalized'
                    );
                }
            }

            SaleItem::where('sale_id', $sale->id)->delete();
            $sale->delete();

            DB::commit();
            return redirect()->back()->with('success', 'تم حذف استهلاك المحاسب واسترجاع المخزون بنجاح');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'تعذر حذف استهلاك المحاسب: ' . $e->getMessage());
        }
    }

    /**
     * تقرير مشتريات المالك واستهلاك المحاسب (للمالك - AJAX)
     */
    public function report(Request $request, $storeId)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 401);
        }

        $store = DB::table('stores')
            ->where('id', $storeId)
            ->where('user_id', $user->id)
            ->first();

        if (!$store) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        try {
            $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));
            $orderBy = $request->get('order_by', 'total_cost');
            $perPage = max(1, min(50, (int) $request->get('per_page', 8)));
            $page = max(1, (int) $request->get('page', 1));
            $financialSummaryService = app(FinancialSummaryService::class);

            $totalStatsQuery = DB::table('sales')
                ->select(
                    DB::raw('COUNT(DISTINCT sales.id) as total_operations'),
                    DB::raw('IFNULL(SUM(si.total), 0) as total_cost'),
                    DB::raw('IFNULL(SUM(si.total) / NULLIF(COUNT(DISTINCT sales.id), 0), 0) as avg_per_operation'),
                    DB::raw('COUNT(DISTINCT si.product_id) as total_products')
                )
                ->leftJoin('sale_items as si', 'sales.id', '=', 'si.sale_id')
                ->leftJoin('products as p', 'si.product_id', '=', 'p.id')
                ->where('sales.store_id', $storeId)
                ->where('sales.sale_type', 'internal_use')
                ->where(function ($query) {
                    $query->where('si.product_usage_snapshot', Product::USAGE_TYPE_SALE)
                        ->orWhere(function ($legacy) {
                            $legacy->whereNull('si.product_usage_snapshot')
                                ->where(function ($usage) {
                                    $usage->where('p.usage_type', Product::USAGE_TYPE_SALE)
                                        ->orWhereNull('p.usage_type');
                                });
                        });
                })
                ->where(function ($categoryFilter) use ($storeId) {
                    $categoryFilter->whereNull('p.id')
                        ->orWhereNotIn('p.category_id', function ($categoryQuery) use ($storeId) {
                            $categoryQuery->select('id')
                                ->from('categories')
                                ->where('store_id', $storeId)
                                ->whereIn('name', ['تضليل', 'تظليل']);
                        });
                })
                ->where(function ($query) {
                    $query->whereNull('sales.description')
                        ->orWhere('sales.description', '!=', 'manual_invoice_entry');
                });

            $financialSummaryService->applyAccountingPeriodToTable($totalStatsQuery, 'sales', $startDate, $endDate);
            $totalStatsRaw = $totalStatsQuery->first();

            $ownerPurchasesStatsQuery = DB::table('purchases')
                ->where('store_id', $storeId)
                ->whereNull('deleted_at')
                ->select(
                    DB::raw('COUNT(id) as total_owner_purchases'),
                    DB::raw('IFNULL(SUM(cost), 0) as owner_purchases_total')
                );

            $financialSummaryService->applyAccountingPeriodToTable($ownerPurchasesStatsQuery, 'purchases', $startDate, $endDate);
            $ownerPurchasesStatsRaw = $ownerPurchasesStatsQuery->first();

            $ownerPurchasesTotal = (float) ($ownerPurchasesStatsRaw->owner_purchases_total ?? 0);
            $ownerPurchasesCount = (int) ($ownerPurchasesStatsRaw->total_owner_purchases ?? 0);

            $totalStats = [
                'total_operations' => (int)($totalStatsRaw->total_operations ?? 0),
                'total_cost' => (float)($totalStatsRaw->total_cost ?? 0),
                'avg_per_operation' => (float)($totalStatsRaw->avg_per_operation ?? 0),
                'total_products' => (int)($totalStatsRaw->total_products ?? 0),
                'owner_purchases_total' => $ownerPurchasesTotal,
                'owner_purchases_count' => $ownerPurchasesCount,
                'grand_total_with_owner' => (float) (($totalStatsRaw->total_cost ?? 0) + $ownerPurchasesTotal),
            ];

            $query = DB::table('sales as s')
                ->join('sale_items as si', 's.id', '=', 'si.sale_id')
                ->leftJoin('products as p', 'si.product_id', '=', 'p.id')
                ->select(
                    'si.product_id',
                    DB::raw("COALESCE(si.product_name_snapshot, p.name, 'منتج محذوف') as name"),
                    'p.description',
                    DB::raw('COALESCE(si.product_type_snapshot, p.product_type) as product_type'),
                    DB::raw('COALESCE(si.is_splittable_snapshot, p.is_splittable, 0) as is_splittable'),
                    DB::raw('COALESCE(si.items_per_unit_snapshot, p.items_per_unit, 1) as items_per_unit'),
                    DB::raw('COALESCE(si.roll_length_snapshot, p.roll_length, 0) as roll_length'),
                    DB::raw('SUM(COALESCE(si.quantity_snapshot, si.quantity)) as total_quantity'),
                    DB::raw('SUM(si.total) as total_cost'),
                    DB::raw('COUNT(DISTINCT s.id) as consumption_count')
                )
                ->where('s.store_id', $storeId)
                ->where('s.sale_type', 'internal_use')
                ->where(function ($query) {
                    $query->where('si.product_usage_snapshot', Product::USAGE_TYPE_SALE)
                        ->orWhere(function ($legacy) {
                            $legacy->whereNull('si.product_usage_snapshot')
                                ->where(function ($usage) {
                                    $usage->where('p.usage_type', Product::USAGE_TYPE_SALE)
                                        ->orWhereNull('p.usage_type');
                                });
                        });
                })
                ->where(function ($categoryFilter) use ($storeId) {
                    $categoryFilter->whereNull('p.id')
                        ->orWhereNotIn('p.category_id', function ($categoryQuery) use ($storeId) {
                            $categoryQuery->select('id')
                                ->from('categories')
                                ->where('store_id', $storeId)
                                ->whereIn('name', ['تضليل', 'تظليل']);
                        });
                })
                ->where(function ($query) {
                    $query->whereNull('s.description')
                        ->orWhere('s.description', '!=', 'manual_invoice_entry');
                })
                ->groupBy(
                    'si.product_id', 'si.product_name_snapshot', 'p.name', 'p.description',
                    'si.product_type_snapshot', 'p.product_type', 'si.is_splittable_snapshot',
                    'p.is_splittable', 'si.items_per_unit_snapshot', 'p.items_per_unit',
                    'si.roll_length_snapshot', 'p.roll_length'
                );

            $financialSummaryService->applyAccountingPeriodToTable($query, 's', $startDate, $endDate, 'sales');

            if ($orderBy === 'total_quantity') {
                $query->orderByDesc('total_quantity');
            } elseif ($orderBy === 'name') {
                $query->orderBy('name');
            } else {
                $query->orderByDesc('total_cost');
            }

            $totalProducts = (clone $query)->count();
            $totalPages = max(1, (int) ceil($totalProducts / $perPage));

            $topProducts = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get()
                ->map(function($item) use ($totalStats) {
                    $item->total_quantity = (float)$item->total_quantity;
                    $item->total_cost = (float)$item->total_cost;
                    $item->consumption_count = (int)$item->consumption_count;
                    $item->percentage = $totalStats['total_cost'] > 0
                        ? round(($item->total_cost / $totalStats['total_cost']) * 100, 1)
                        : 0;

                    if ($item->product_type === 'fractional') {
                        $item->unit = 'متر';
                        $item->unit_display = 'متر';
                        $item->display_text = number_format($item->total_quantity, 2) . ' متر';
                        if ($item->roll_length && $item->roll_length > 0) {
                            $totalRolls = $item->total_quantity / $item->roll_length;
                            $item->display_subtext = '≈ ' . number_format($totalRolls, 2) . ' رول';
                        }
                    } elseif ($item->is_splittable) {
                        $item->unit = 'طقم';
                        $item->unit_display = 'طقم/حبة';
                        $item->display_text = number_format($item->total_quantity, 2) . ' طقم';
                    } else {
                        $item->unit = 'قطعة';
                        $item->unit_display = 'قطعة';
                        $item->display_text = number_format($item->total_quantity, 0) . ' قطعة';
                    }

                    return $item;
                });

            return response()->json([
                'success' => true,
                'total_stats' => $totalStats,
                'top_products' => $topProducts,
                'total_pages' => $totalPages,
                'current_page' => $page,
            ]);
        } catch (\Exception $e) {
            \Log::error('Internal use report error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب بيانات التقرير',
            ], 500);
        }
    }

    /**
     * جلب تفاصيل منتج معين (للمالك - AJAX)
     */
    public function productDetails(Request $request, $storeId)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['success' => false, 'message' => 'غير مصرح'], 401);

        $store = DB::table('stores')->where('id', $storeId)->where('user_id', $user->id)->first();
        if (!$store) return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $financialSummaryService = app(FinancialSummaryService::class);

        $historyQuery = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->leftJoin('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.store_id', $storeId)
            ->where('sales.sale_type', 'internal_use')
            ->where(function ($query) {
                $query->where('products.usage_type', Product::USAGE_TYPE_SALE)
                    ->orWhereNull('products.usage_type');
            })
            ->whereNotIn('products.category_id', function ($categoryQuery) use ($storeId) {
                $categoryQuery->select('id')
                    ->from('categories')
                    ->where('store_id', $storeId)
                    ->whereIn('name', ['تضليل', 'تظليل']);
            })
            ->where(function ($query) {
                $query->whereNull('sales.description')
                    ->orWhere('sales.description', '!=', 'manual_invoice_entry');
            })
            ->where('sale_items.product_id', $request->product_id);

        $financialSummaryService->applyAccountingPeriodToTable($historyQuery, 'sales', $request->start_date, $request->end_date);

        $history = $historyQuery
            ->select(
                'sale_items.id',
                'sale_items.quantity',
                'sale_items.total',
                'sale_items.unit_type',
                'sales.business_date',
                'sales.created_at',
                'sales.internal_notes',
                DB::raw('COALESCE(sale_items.product_type_snapshot, products.product_type) as product_type'),
                DB::raw('COALESCE(sale_items.is_splittable_snapshot, products.is_splittable, 0) as is_splittable'),
                DB::raw('COALESCE(sale_items.items_per_unit_snapshot, products.items_per_unit, 1) as items_per_unit'),
                DB::raw('COALESCE(sale_items.roll_length_snapshot, products.roll_length, 0) as roll_length')
            )
            // التاريخ المسجل للشفت هو الأساس، وتاريخ الإدخال بديل للسجلات القديمة فقط.
            ->orderByRaw('COALESCE(sales.business_date, DATE(sales.created_at)) DESC')
            ->orderByDesc('sales.created_at')
            ->get()
            ->map(function($item) {
                $item->date = $item->business_date
                    ? \Carbon\Carbon::parse($item->business_date)->format('Y-m-d')
                    : date('Y-m-d H:i', strtotime($item->created_at));
                $item->notes = $item->internal_notes ?? '-';

                $unitType = $item->unit_type ?? null;

                if ($item->product_type === 'fractional') {
                    if ($unitType === 'roll') {
                        $item->unit_display = 'رول';
                        $item->details = number_format($item->quantity, 3) . ' رول';
                    } else {
                        $item->unit_display = 'متر';
                        $item->details = number_format($item->quantity, 2) . ' متر';
                    }
                } elseif ($item->is_splittable) {
                    if ($unitType === 'piece') {
                        $item->unit_display = 'حبة';
                        $item->details = number_format($item->quantity, 0) . ' حبة';
                    } else {
                        $item->unit_display = 'طقم';
                        $item->details = number_format($item->quantity, 0) . ' طقم';
                    }
                } else {
                    $item->unit_display = 'قطعة';
                    $item->details = number_format($item->quantity, 0) . ' قطعة';
                }

                return $item;
            });

        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }

    /**
     * تصدير تقرير مشتريات المالك واستهلاك المحاسب بصيغة PDF (للمالك)
     */
    public function exportPDF(Request $request, $storeId)
    {
        $user = Auth::user();
        if (!$user) abort(401);

        $store = DB::table('stores')
            ->where('id', $storeId)
            ->where('user_id', $user->id)
            ->first();

        if (!$store) {
            abort(403, 'غير مصرح لك بالوصول لهذا المتجر');
        }

        $month = max(1, min(12, (int) $request->get('month', now()->month)));
        $year = max(2020, min(2100, (int) $request->get('year', now()->year)));

        $reportData = $this->buildMonthlyConsumptionData($storeId, $month, $year);

        $data = [
            'store' => $store,
            'month' => $month,
            'year' => $year,
            'reportData' => $reportData,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ];

        $pdf = ArabicPdf::loadView('pdf.internal_use_pdf', $data);

        return $pdf->setOption('encoding', 'utf-8')
            ->setOption('margin-bottom', 10)
            ->download("Consumption_Report_{$year}_{$month}.pdf");
    }

    private function buildMonthlyConsumptionData(int $storeId, int $month, int $year): array
    {
        $period = now()->setYear($year)->setMonth($month);
        $startDate = $period->copy()->startOfMonth()->format('Y-m-d');
        $endDate = $period->copy()->endOfMonth()->format('Y-m-d');

        $financialSummaryService = app(FinancialSummaryService::class);

        $internalUseSalesQuery = DB::table('sales as s')
            ->leftJoin('sale_items as si', 's.id', '=', 'si.sale_id')
            ->leftJoin('products as p', 'si.product_id', '=', 'p.id')
            ->where('s.store_id', $storeId)
            ->where('s.sale_type', 'internal_use')
            ->where(function ($query) {
                $query->where('si.product_usage_snapshot', Product::USAGE_TYPE_SALE)
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('si.product_usage_snapshot')
                            ->where(function ($usage) {
                                $usage->where('p.usage_type', Product::USAGE_TYPE_SALE)
                                    ->orWhereNull('p.usage_type');
                            });
                    });
            })
            ->where(function ($categoryFilter) use ($storeId) {
                $categoryFilter->whereNull('p.id')
                    ->orWhereNotIn('p.category_id', function ($categoryQuery) use ($storeId) {
                        $categoryQuery->select('id')
                            ->from('categories')
                            ->where('store_id', $storeId)
                            ->whereIn('name', ['تضليل', 'تظليل']);
                    });
            })
            ->where(function ($query) {
                $query->whereNull('s.description')
                    ->orWhere('s.description', '!=', 'manual_invoice_entry');
            });

        $financialSummaryService->applyAccountingPeriodToTable($internalUseSalesQuery, 's', $startDate, $endDate, 'sales');

        $internalUseSales = $internalUseSalesQuery
            ->select(
                's.id',
                DB::raw('SUM(si.total) as amount'),
                's.internal_notes as description',
                's.business_date',
                's.created_at',
                's.accountant_id',
                DB::raw("MIN(COALESCE(si.product_name_snapshot, p.name, 'منتج محذوف')) as product_name"),
                DB::raw('MIN(COALESCE(si.quantity_snapshot, si.quantity)) as raw_quantity'),
                $this->supportsSaleItemUnitType() ? DB::raw('MIN(si.unit_type) as raw_unit_type') : DB::raw("'default' as raw_unit_type")
            )
            ->groupBy('s.id', 's.internal_notes', 's.business_date', 's.created_at', 's.accountant_id')
            ->get()
            ->map(function ($item) {
                return [
                    'source' => $item->accountant_id
                        ? 'استهلاك المحاسب'
                        : 'استهلاك المحاسب (سجل قديم)',
                    'type' => $item->product_name ?: 'استهلاك المحاسب',
                    'description' => $item->description ?: '-',
                    'amount' => (float) $item->amount,
                    'created_at' => $item->created_at,
                    'operation_date' => $item->business_date ?: $item->created_at,
                    'entry_type' => 'accountant_internal_use',
                    'entry_id' => (int) $item->id,
                    'raw_quantity' => (float) ($item->raw_quantity ?? 0),
                    'raw_unit_type' => $item->raw_unit_type ?: 'default',
                ];
            });

        $ownerPurchasesQuery = DB::table('purchases')
            ->where('store_id', $storeId)
            ->whereNull('deleted_at');

        $financialSummaryService->applyAccountingPeriodToTable($ownerPurchasesQuery, 'purchases', $startDate, $endDate);

        $ownerPurchases = $ownerPurchasesQuery
            ->select('id', 'product_name_snapshot', 'purchase_name', 'description', 'cost', 'business_date', 'created_at')
            ->get()
            ->map(function ($item) {
                return [
                    'source' => 'مشتريات المالك',
                    'type' => $item->product_name_snapshot ?: ($item->purchase_name ?: 'مشتريات المالك'),
                    'description' => $item->description ?: '-',
                    'amount' => (float) $item->cost,
                    'created_at' => $item->created_at,
                    'operation_date' => $item->business_date ?: $item->created_at,
                    'entry_type' => 'owner_purchase',
                    'entry_id' => (int) $item->id,
                    'business_date' => $item->business_date ?: \Carbon\Carbon::parse($item->created_at)->toDateString(),
                ];
            });

        $records = $internalUseSales
            ->concat($ownerPurchases)
            // يوحد ترتيب المصدرين على التاريخ الفعلي للعملية مع رجوع آمن لتاريخ الإدخال.
            ->sortByDesc(fn ($row) => \Carbon\Carbon::parse($row['operation_date'])->timestamp)
            ->values();

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'records' => $records,
            'summary' => [
                'accountant_total' => (float) $internalUseSales->filter(fn ($item) => $item['source'] === 'استهلاك المحاسب')->sum('amount'),
                'owner_total' => (float) $ownerPurchases->sum('amount'),
                'grand_total' => (float) $records->sum('amount'),
                'count' => (int) $records->count(),
            ],
        ];
    }


    private function supportsSaleItemUnitType(): bool
    {
        static $hasUnitType = null;

        if ($hasUnitType === null) {
            $hasUnitType = Schema::hasColumn('sale_items', 'unit_type');
        }

        return $hasUnitType;
    }

    /**
     * حساب الكمية الفعلية للخصم من المخزون
     */
    private function calculateActualDeduction($product, $quantity, $unitType)
    {
        // تحويل موحد عبر الموديل نفسه حتى يبقى منطق:
        // متر/رول/حبة/طقم متطابقاً مع مسارات التوريد وإدارة المخزون.
        return $product->normalizeQuantityByUnit($quantity, $unitType);
    }

    /**
     * الحصول على اسم الوحدة للعرض
     */
    private function getUnitName($unitType)
    {
        return match($unitType) {
            'meters' => 'متر',
            'roll' => 'رول',
            'piece' => 'حبة',
            'kit' => 'طقم',
            default => 'وحدة',
        };
    }

    private function buildOwnerPurchaseGroupsFromRecords($records)
    {
        return collect($records)
            ->filter(fn ($row) => ($row['source'] ?? '') === 'مشتريات المالك')
            ->groupBy(function ($row) {
                return $this->normalizeOwnerPurchaseType((string) ($row['type'] ?? ''));
            })
            ->map(function ($group, $name) {
                return [
                    'name' => $name,
                    'count' => $group->count(),
                    'total' => (float) $group->sum('amount'),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    private function normalizeOwnerPurchaseType(string $type): string
    {
        $value = trim(mb_strtolower($type));

        if ($value === '') {
            return 'مشتريات أخرى';
        }

        foreach ($this->ownerPurchaseGroupingMap() as $keywords => $label) {
            foreach (explode('|', $keywords) as $keyword) {
                if ($keyword !== '' && mb_strpos($value, $keyword) !== false) {
                    return $label;
                }
            }
        }

        return $type;
    }

    private function ownerPurchaseGroupingMap(): array
    {
        return [
            'امواس|مواس' => 'أمواس',
            'ربل|روبل|رابر' => 'ربل',
            'تضليل|تظليل' => 'تظليل',
            'تجليد|تغليف' => 'تجليد',
            'مرشات|مرش' => 'مرشات',
            'حماية انوار|حمايه انوار|حماية أنوار|انوار' => 'حماية أنوار',
            'شطرطون|تيب|لاصق' => 'شطرطون / لواصق',
            'فيوز|فيوزات' => 'فيوز',
        ];
    }

    /**
     * البحث عن المنتجات (للمحاسب فقط)
     */
    public function searchProducts(Request $request, ProductSearchService $productSearch)
    {
        $query = $request->get('q');
        $accountant = Auth::guard('accountant')->user();
        $webUser = Auth::guard('web')->user();

        $storeId = null;

        if ($accountant) {
            $storeId = $accountant->store_id;
        } elseif ($webUser) {
            $routeStore = $request->route('store');
            $storeId = is_object($routeStore) ? $routeStore->id : (int) $routeStore;

            if (!$storeId || !$webUser->stores()->whereKey($storeId)->exists()) {
                return response()->json([]);
            }
        } else {
            return response()->json([]);
        }

        return response()->json($productSearch->pickerResults((int) $storeId, $query, 30));
    }
}
