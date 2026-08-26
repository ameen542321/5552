<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Product;
use App\Models\Category;
use App\Models\Accountant;
use App\Models\Log;
use App\Models\ArchivedItem;
use App\Services\LogService;
use App\Services\SupportSessionService;
use App\Services\Products\ProductSearchService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Store $store, Request $request, ProductSearchService $productSearch)
    {
        $showOwnerPurchase = $request->boolean('show_owner_purchase');

        $query = $store->products()->with([
            'category:id,name',
            'latestInventoryAuditMovement',
        ]);

        if (! $showOwnerPurchase) {
            $query->sellable();
        }

        // بحث موحد بالاسم والوصف والباركود عبر خدمة مشتركة.
        $productSearch->applyOwnerCatalogSearch($query, $request->get('search'));

        // فلترة حسب القسم
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // عند فتح بطاقة من نتائج البحث السريع نضع المنتج المقصود أولاً حتى تظهر بطاقته في نفس الصفحة.
        if ($request->filled('highlight_product')) {
            $query->orderByRaw('CASE WHEN products.id = ? THEN 0 ELSE 1 END', [(int) $request->highlight_product]);
        }

        // ترتيب المنتجات بحيث تظهر المنتجات منخفضة المخزون أولاً
        $query->orderByRaw("CASE WHEN product_type = 'fractional' AND roll_length > 0 THEN ((quantity / roll_length) <= min_stock) ELSE (quantity <= min_stock) END DESC")
              ->orderBy('quantity', 'asc');

        // Pagination
        $products = $query->paginate(20)->withQueryString();

        // إحصائيات سريعة (بعد نفس الفلاتر المطبقة)
        $statsQuery = Product::where('store_id', $store->id)
            ->where('status', 'active')
            ->sellable();

        $productSearch->applyOwnerCatalogSearch($statsQuery, $request->get('search'));

        if ($request->filled('category_id')) {
            $statsQuery->where('category_id', $request->category_id);
        }

        if ($request->filled('status')) {
            $statsQuery->where('status', $request->status);
        }


        $stats = $statsQuery->selectRaw('
            COUNT(*) as total_count,
            SUM(
                CASE
                    WHEN product_type = "fractional" AND roll_length > 0 THEN (quantity / roll_length) * COALESCE(cost_price, 0)
                    ELSE quantity * COALESCE(cost_price, 0)
                END
            ) as total_cost,
            SUM(
                CASE
                    WHEN product_type = "fractional" AND roll_length > 0 THEN (quantity / roll_length) * price
                    ELSE quantity * price
                END
            ) as total_value
        ')->first();

        $stats->low_stock_count = (clone $statsQuery)
            ->lowStock()
            ->count();

        $inventoryAuditProducts = $store->products()->where('status', 'active')->sellable()->get();
        $inventoryAuditCounts = ['total' => $inventoryAuditProducts->count(), 'red' => 0, 'yellow' => 0, 'green' => 0];
        foreach ($inventoryAuditProducts as $inventoryAuditProduct) {
            $statusColor = $inventoryAuditProduct->inventoryAuditStatus($store)['color'] ?? 'red';
            $inventoryAuditCounts[$statusColor] = ($inventoryAuditCounts[$statusColor] ?? 0) + 1;
        }
        $inventoryAuditCycleStart = Product::inventoryAuditCycleStart($store);
        // نهاية الدورة تتبع إعداد المتجر (6 أو 12 شهرًا) بدل مدة ثابتة لكل المتاجر.
        $inventoryAuditCycleEnd = $inventoryAuditCycleStart->copy()->addMonthsNoOverflow($store->inventoryAuditCycleMonths());

        // عدد المحذوفات
        $trashedCount = Product::onlyTrashed()
            ->where('store_id', $store->id)
            ->count();

        // الأقسام
        $categories = Category::where('store_id', $store->id)->get();

        $recentSoldProductRows = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.store_id', $store->id)
            ->where('products.store_id', $store->id)
            ->whereNull('products.deleted_at')
            ->where(function ($recentSoldQuery) {
                $recentSoldQuery->where('products.usage_type', Product::USAGE_TYPE_SALE)
                    ->orWhereNull('products.usage_type');
            })
            ->select('products.id')
            ->selectRaw('MAX(sales.created_at) as latest_sold_at')
            ->groupBy('products.id')
            ->orderByDesc('latest_sold_at')
            ->limit(12)
            ->get();

        $recentSoldProductIds = $recentSoldProductRows
            ->pluck('id')
            ->map(fn ($productId) => (int) $productId)
            ->values();
        // مصدر البطاقات السريعة تحت البحث: عند عدم الكتابة تعرض الأحدث بيعاً أولاً،
        // وعند كتابة اسم/باركود تبحث في كل منتجات البيع حتى لو لم تكن موجودة في صفحة الترقيم الحالية.
        $quickSearchProducts = Product::where('store_id', $store->id)
            ->where('status', 'active')
            ->sellable()
            ->with('category:id,name')
            ->orderBy('name')
            ->get()
            ->sortBy(function (Product $product) use ($recentSoldProductIds) {
                $recentIndex = $recentSoldProductIds->search((int) $product->id);

                return $recentIndex === false
                    ? '999999-' . mb_strtolower((string) ($product->name ?? ''))
                    : sprintf('%06d-', (int) $recentIndex);
            })
            ->values();

        return view('user.stores.products.index', compact(
            'store',
            'products',
            'quickSearchProducts',
            'categories',
            'trashedCount',
            'stats',
            'inventoryAuditCounts',
            'inventoryAuditCycleStart',
            'inventoryAuditCycleEnd',
            'showOwnerPurchase',
        ));
    }
    public function auditIndex(Store $store, Request $request)
    {
        $auditStatus = $request->input('audit_status');
        $searchTerm = $request->input('search');
        $auditProducts = $store->products()->where('status', 'active')->sellable()->with('category:id,name')->get();
        $inventoryAuditCounts = ['total' => $auditProducts->count(), 'red' => 0, 'yellow' => 0, 'green' => 0];

        $filteredProducts = $auditProducts->filter(function (Product $product) use ($store, $auditStatus, $searchTerm, &$inventoryAuditCounts) {
            $audit = $product->inventoryAuditStatus($store);
            $color = $audit['color'] ?? 'red';
            $inventoryAuditCounts[$color] = ($inventoryAuditCounts[$color] ?? 0) + 1;

            if (in_array($auditStatus, ['red', 'yellow', 'green'], true) && $color !== $auditStatus) {
                return false;
            }

            if ($searchTerm) {
                $needle = mb_strtolower($searchTerm);
                $haystack = mb_strtolower(($product->name ?? '') . ' ' . ($product->description ?? '') . ' ' . ($product->barcode ?? ''));
                return str_contains($haystack, $needle);
            }

            return true;
        })->values();

        $perPage = 10;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $products = new LengthAwarePaginator(
            $filteredProducts->forPage($currentPage, $perPage)->values(),
            $filteredProducts->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $inventoryAuditCycleStart = Product::inventoryAuditCycleStart($store);
        // addMonthsNoOverflow يحافظ على يوم صالح عند بدء الدورة في نهاية الشهر.
        $inventoryAuditCycleEnd = $inventoryAuditCycleStart->copy()->addMonthsNoOverflow($store->inventoryAuditCycleMonths());

        return view('user.stores.products.audit', compact(
            'store',
            'products',
            'inventoryAuditCounts',
            'inventoryAuditCycleStart',
            'inventoryAuditCycleEnd',
            'auditStatus',
            'searchTerm'
        ));
    }

// ]دالة البحث في صفحة المنتجتات للمحاسب
    public function indexPos(Request $request, ProductSearchService $productSearch)
{
    // الحصول على المحاسب المسجل دخوله
    $accountant = Auth::guard('accountant')->user();

    // جلب المتجر المرتبط بالمحاسب
    $store = $accountant->store;

    // التحقق من وجود متجر مرتبط
    if (!$store) {
        abort(404, 'لم يتم تعيين متجر لهذا المحاسب');
    }

    // ✅ جلب المنتجات مع جميع الحقول المهمة والعلاقات
    $query = $store->products()
        ->with([
            'category' => function($q) {
                $q->select('id', 'name', 'is_main_category');
            },
            'fractions' => function($q) {
                $q->select('id', 'product_id', 'option_label', 'price', 'deduction_value');
            }
        ])
        ->select([
            'id', 'name', 'description', 'barcode', 'price', 'cost_price', 'piece_price',
            'quantity', 'min_stock', 'status', 'category_id', 'image', 'created_at',
            'product_type', 'is_splittable', 'items_per_unit', 'roll_length', 'waste_percentage'
        ])
        ->where('status', 'active')
        // حماية صفحة بحث منتجات المحاسب: استبعاد منتجات مشتريات المالك من أي بحث أو عرض بيعي.
        ->sellable()
        ->where('product_type', '!=', 'fractional')
        ->whereNotNull('price')
        ->where('price', '>', 0)
        ->whereNotNull('quantity')
        ->whereNotNull('cost_price')
        ->withoutTintCategory();

    // بحث موحد بالاسم والوصف والباركود عبر خدمة المنتجات.
    $productSearch->applyAccountantCatalogSearch($query, $request->get('search'));

    // فلترة حسب القسم
    if ($request->filled('category_id')) {
        $query->where('category_id', $request->category_id);
    }

    if ($request->filled('low_stock') && $request->low_stock == '1') {
        $query->lowStock();
    }

    // فلترة حسب توفر الصورة
    if ($request->filled('has_image') && $request->has_image == '1') {
        $query->whereNotNull('image');
    }

    // الترتيب المطلوب للمحاسب: المتوفر أولاً، ثم منخفض المخزون، ثم المنتهي.
    $query->orderByRaw('CASE
            WHEN quantity <= 0 THEN 2
            WHEN min_stock IS NOT NULL AND ROUND(quantity, 4) <= ROUND(min_stock, 4) THEN 1
            ELSE 0
        END ASC')
          ->orderBy('name', 'asc');

    // الترقيم: ثابت 10 منتجات في الصفحة لتخفيف عرض المحاسب.
    $products = $query->paginate(10)->withQueryString();

    // ✅✅✅ معالجة البيانات بعد الجلب - بشكل صحيح وكامل
    foreach ($products as $product) {
        // تحويل الأسعار إلى أرقام عشرية
        $product->price = (float) $product->price;
        $product->cost_price = (float) ($product->cost_price ?? 0);
        $product->piece_price = (float) ($product->piece_price ?? 0);

        // ✅✅✅ تحديد نوع المنتج بشكل صحيح جداً
        $product->is_fractional = ($product->product_type === 'fractional');
        $product->is_set = ($product->is_splittable == 1 && $product->items_per_unit > 0);
        $product->is_normal = (!$product->is_fractional && !$product->is_set);

        // ✅ حساب الكمية المعروضة حسب نوع المنتج
        if ($product->is_fractional) {
            // منتج رول
            if ($product->roll_length > 0) {
                $product->display_rolls = $product->quantity / $product->roll_length;
                $product->display_quantity = number_format($product->display_rolls, 2);
                $product->display_unit = 'رول';
                $product->display_min_stock = number_format($product->min_stock, 2);
                $product->total_meters = number_format($product->quantity, 2);
                $product->low_stock = $product->display_rolls <= $product->min_stock;
                $product->meter_price = number_format($product->price / $product->roll_length, 2);
            } else {
                $product->display_quantity = number_format($product->quantity, 2);
                $product->display_unit = 'متر';
                $product->display_min_stock = number_format($product->min_stock, 2);
                $product->total_meters = $product->display_quantity;
                $product->low_stock = false;
                $product->meter_price = number_format($product->price, 2);
            }
        } elseif ($product->is_set) {
            // منتج طقم ✅✅✅ هذا هو المهم لموضوعك
            $itemsPerUnit = $product->items_per_unit ?: 1;
            $product->total_sets = $product->quantity;
            $product->total_pieces = $product->total_sets * $itemsPerUnit;
            $product->display_quantity = number_format($product->total_sets, 0);
            $product->display_unit = 'طقم';
            $product->display_min_stock = number_format($product->min_stock, 0);
            $product->low_stock = false;

            // ✅ سعر الحبة المفردة - مهم جداً
            $product->piece_price_display = number_format($product->piece_price, 0);

            // ✅ سعر الطقم كاملاً
            $product->set_price_display = number_format($product->price, 0);
        } else {
            // منتج عادي
            $product->display_quantity = number_format($product->quantity, 0);
            $product->display_unit = 'قطعة';
            $product->display_min_stock = number_format($product->min_stock, 0);
            $product->low_stock = false;
        }

        // ✅ حساب القيم الإجمالية بشكل متوافق مع عرض المنتجات الكسرية
        $valueQuantity = $product->quantity;

        if ($product->product_type === 'fractional' && $product->roll_length > 0) {
            $valueQuantity = $product->quantity / $product->roll_length;
        }

        $product->total_cost = $product->cost_price * $valueQuantity;
        $product->total_value = $product->price * $valueQuantity;

        // معالجة fractions إذا وجدت
        if ($product->fractions && $product->fractions->count() > 0) {
            $product->fractions->map(function($f) {
                $f->price = (float) $f->price;
                $f->deduction_value = (float) $f->deduction_value;
                return $f;
            });
        }
    }

    // جلب الأقسام الخاصة بالمتجر
    $categories = $store->categories()
        ->select('id', 'name', 'is_main_category')
        ->orderBy('is_main_category', 'desc')
        ->orderBy('name', 'asc')
        ->get();

    return view('accountants.pos.searchProduct', compact(
        'store',
        'products',
        'categories',
        'accountant'
    ));
}
    public function exportCsv(Store $store)
    {
        $filename = 'products-store-' . $store->id . '-' . now()->format('Ymd_His') . '.csv';

        $products = $store->products()
            ->with(['category:id,name', 'fractions:id,product_id,option_label,deduction_value,price'])
            ->orderBy('name')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->streamDownload(function () use ($products) {
            $out = fopen('php://output', 'w');
            fwrite($out, "ï»¿");

            fputcsv($out, [
                'category_name',
                'product_name',
                'barcode',
                'description',
                'status',
                'min_stock',
                'sale_price',
                'cost_price',
                'quantity',
                'product_type',
                'usage_type',
                'is_splittable',
                'items_per_unit',
                'piece_price',
                'roll_length',
                'waste_percentage',
                'fractions_json',
            ]);

            foreach ($products as $product) {
                [$salePrice, $costPrice] = $this->normalizeTransferPrices($product->price, $product->cost_price);

                $fractionsJson = $product->fractions->isEmpty()
                    ? ''
                    : json_encode($product->fractions->map(fn ($fraction) => [
                        'option_label' => $fraction->option_label,
                        'deduction_value' => (float) $fraction->deduction_value,
                        'price' => (float) $fraction->price,
                    ])->values()->all(), JSON_UNESCAPED_UNICODE);

                fputcsv($out, [
                    $product->category->name ?? 'بدون قسم',
                    $product->name,
                    $product->barcode,
                    $product->description,
                    $product->status,
                    (float) ($product->min_stock ?? 0),
                    $salePrice,
                    $costPrice,
                    0, // شرط النقل: الكمية دائماً صفر
                    $product->product_type ?? 'standard',
                    $product->usage_type ?? Product::USAGE_TYPE_SALE,
                    $product->is_splittable ? 1 : 0,
                    (int) ($product->items_per_unit ?? 1),
                    (float) ($product->piece_price ?? 0),
                    (float) ($product->roll_length ?? 0),
                    (float) ($product->waste_percentage ?? 0),
                    $fractionsJson,
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }

    public function importCsv(Request $request, Store $store)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            return back()->with('error', 'تعذر قراءة ملف CSV.');
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            return back()->with('error', 'الملف فارغ أو غير صالح.');
        }

        $header = array_map(function ($h) {
            $key = trim((string) $h);
            // معالجة حالة BOM في أول عمود حتى لا يفشل التحقق من category_name
            $key = preg_replace('/^\xEF\xBB\xBF/u', '', $key);
            return strtolower($key);
        }, $header);

        $requiredColumns = ['category_name', 'product_name', 'sale_price', 'cost_price'];
        foreach ($requiredColumns as $column) {
            if (! in_array($column, $header, true)) {
                fclose($handle);
                return back()->with('error', 'الملف لا يحتوي على العمود الإلزامي: ' . $column);
            }
        }

        $col = array_flip($header);
        $created = 0;
        $createdCategories = 0;
        $skipped = 0;
        $duplicates = 0;

        DB::transaction(function () use ($handle, $col, $store, &$created, &$createdCategories, &$skipped, &$duplicates) {
            while (($row = fgetcsv($handle)) !== false) {
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $name = trim((string) $this->csvValue($row, $col, 'product_name'));
                $categoryName = trim((string) $this->csvValue($row, $col, 'category_name'));

                if ($name === '' || $categoryName === '') {
                    $skipped++;
                    continue;
                }

                [$salePrice, $costPrice] = $this->normalizeTransferPrices(
                    $this->toNullableNumber($this->csvValue($row, $col, 'sale_price')),
                    $this->toNullableNumber($this->csvValue($row, $col, 'cost_price'))
                );

                $category = Category::firstOrCreate(
                    [
                        'store_id' => $store->id,
                        'name' => $categoryName,
                    ],
                    [
                        'user_id' => auth()->id(),
                        'slug' => $this->generateImportCategorySlug($store, $categoryName),
                        'status' => 'active',
                        'description' => null,
                        'is_main_category' => false,
                    ]
                );

                if ($category->wasRecentlyCreated) {
                    $createdCategories++;
                }

                $barcode = trim((string) $this->csvValue($row, $col, 'barcode')) ?: null;
                $product = $this->findProductForImport($store, $name, $barcode, $category->id);

                $productType = trim((string) $this->csvValue($row, $col, 'product_type'));
                $productType = in_array($productType, ['standard', 'fractional'], true) ? $productType : 'standard';
                $usageType = trim((string) $this->csvValue($row, $col, 'usage_type'));
                $usageType = in_array($usageType, [Product::USAGE_TYPE_SALE, Product::USAGE_TYPE_OWNER_PURCHASE], true)
                    ? $usageType
                    : Product::USAGE_TYPE_SALE;

                $isSplittable = (int) $this->toNullableNumber($this->csvValue($row, $col, 'is_splittable')) === 1;
                $itemsPerUnit = max(1, (int) ($this->toNullableNumber($this->csvValue($row, $col, 'items_per_unit')) ?? 1));
                $piecePrice = (float) ($this->toNullableNumber($this->csvValue($row, $col, 'piece_price')) ?? 0);
                $rollLength = (float) ($this->toNullableNumber($this->csvValue($row, $col, 'roll_length')) ?? 0);
                $wastePercentage = (float) ($this->toNullableNumber($this->csvValue($row, $col, 'waste_percentage')) ?? 0);
                $minStock = (float) ($this->toNullableNumber($this->csvValue($row, $col, 'min_stock')) ?? 1);

                $payload = [
                    'store_id' => $store->id,
                    'user_id' => auth()->id(),
                    'category_id' => $category->id,
                    'name' => $name,
                    'slug' => $product ? $product->slug : $this->generateImportProductSlug($store, $name),
                    'barcode' => $barcode,
                    'description' => $this->csvValue($row, $col, 'description'),
                    'status' => in_array($this->csvValue($row, $col, 'status'), ['active', 'inactive'], true)
                        ? $this->csvValue($row, $col, 'status')
                        : 'active',
                    'price' => $salePrice,
                    'cost_price' => $costPrice,
                    'quantity' => 0,
                    'min_stock' => $minStock,
                    'product_type' => $productType,
                    'usage_type' => $usageType,
                    'is_splittable' => $productType === 'standard' ? $isSplittable : false,
                    'items_per_unit' => $productType === 'standard' && $isSplittable ? $itemsPerUnit : 1,
                    'piece_price' => $productType === 'standard' ? $piecePrice : 0,
                    'roll_length' => $productType === 'fractional' ? $rollLength : 0,
                    'waste_percentage' => $wastePercentage,
                ];

                if ($product) {
                    // حسب الطلب: المنتج الموجود مسبقاً لا يتم استيراده مرة أخرى
                    $duplicates++;
                    continue;
                }

                $product = Product::create($payload);
                $created++;

                // نقل خيارات المتر/القص إن وجدت للمنتجات الجديدة فقط
                $fractionsJson = $this->csvValue($row, $col, 'fractions_json');
                $fractions = $this->decodeFractions($fractionsJson);
                if ($productType === 'fractional' && ! empty($fractions)) {
                    foreach ($fractions as $fraction) {
                        $product->fractions()->create([
                            'option_label' => (string) ($fraction['option_label'] ?? ''),
                            'deduction_value' => (float) ($fraction['deduction_value'] ?? 0),
                            'price' => (float) ($fraction['price'] ?? 0),
                        ]);
                    }
                }
            }
        });

        fclose($handle);

        return redirect()->route('user.stores.products.index', $store->id)
            ->with('success', "تم استيراد CSV بنجاح: {$created} جديد، {$duplicates} مكرر تم تجاهله، {$createdCategories} أقسام جديدة، {$skipped} صفوف متجاوزة.");
    }

    public function create(Store $store, Request $request)
    {
        $categories = Category::where('store_id', $store->id)->get();

        // القسم المختار تلقائيًا
        $selectedCategory = $request->category_id;
        // أنواع المنتجات المتاحة
        $productTypes = [
            'standard' => 'منتج عادي (بالحبة)',
            'fractional' => 'منتج قابل للتجزئة (رول/قص)'
        ];

        return view('user.stores.products.create', compact(
            'store',
            'categories',
            'selectedCategory',
            'productTypes'
        ));
    }

    public function store(Request $request, Store $store)
    {
        // 1. التعديل في الـ Validation
        $request->validate([
            'name'             => 'required|string|max:255',
            'category_id'      => ['required', Rule::exists('categories', 'id')->where('store_id', $store->id)],
            'price'            => 'required|numeric|min:0',
            'cost_price'       => 'nullable|numeric|min:0',
            'quantity'         => 'required_if:product_type,standard|nullable|numeric|min:0',
            'min_stock'        => 'nullable|numeric|min:0',
            'description'      => 'nullable|string',
            'carton_qty'       => 'nullable|integer|min:1', // حقل سعة الكرتون الجديد
            'status'           => 'required|in:active,inactive',
            'image'            => 'nullable|image|max:2048',

            // الحقول الجديدة
            'product_type'     => 'required|in:standard,fractional',
            'usage_type'       => 'nullable|in:sale,owner_purchase',
            'waste_percentage' => 'nullable|numeric|min:0|max:100',
            'num_rolls'        => 'required_if:product_type,fractional|nullable|numeric|min:0',
            'roll_length'      => 'exclude_unless:product_type,fractional|required|numeric|min:0.01',

            // حقول الأطقم
            'is_splittable'    => 'nullable|boolean',
            'items_per_unit'   => 'required_if:is_splittable,1|nullable|integer|min:1',
            'piece_price'      => 'nullable|numeric|min:0',
            'quick_sale_default_unit' => 'nullable|in:unit,piece',

            // التحقق من خيارات التجزئة
            'fractions'        => 'exclude_unless:product_type,fractional|required|array',
            'fractions.*.option_label'    => 'exclude_unless:product_type,fractional|required|string|max:255',
            'fractions.*.deduction_value' => 'exclude_unless:product_type,fractional|required|numeric|min:0',
            'fractions.*.price'           => 'exclude_unless:product_type,fractional|required|numeric|min:0',
        ], $this->productValidationMessages());

        if ($request->product_type === 'standard' && ! $request->boolean('is_splittable') && $request->filled('min_stock')) {
            $minStock = (float) $request->min_stock;
            if (abs($minStock - round($minStock)) > 0.0001) {
                return back()
                    ->withErrors(['min_stock' => 'الحد الأدنى للمنتج العادي بالحبة يجب أن يكون رقمًا صحيحًا. فعّل نظام الطقم إذا كنت تحتاج نصف طقم أو ربع طقم.'])
                    ->withInput();
            }
        }

        if ($request->product_type === 'standard' && $request->boolean('is_splittable') && $request->filled('min_stock')) {
            $itemsPerUnit = max(1, (int) $request->items_per_unit);
            $piecesEquivalent = (float) $request->min_stock * $itemsPerUnit;
            if (abs($piecesEquivalent - round($piecesEquivalent)) > 0.0001) {
                return back()
                    ->withErrors(['min_stock' => "الحد الأدنى للطقم يجب أن يعادل عدد حبات صحيحًا. إذا كان الطقم {$itemsPerUnit} حبات فاستخدم قيمة تتحول إلى حبات كاملة."])
                    ->withInput();
            }
        }

        $usageType = $request->input('usage_type', Product::USAGE_TYPE_SALE);
        if ($this->hasDisallowedDuplicateProductName($store->id, $request->name, $usageType)) {
            $message = 'هذا المنتج موجود مسبقاً في المنتجات أو سلة المحذوفات.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['name' => [$message]]], 422);
            }

            return back()->withErrors(['name' => $message])->withInput();
        }

        $slug = $this->buildUniqueStoreScopedSlug($request->name, $store->id);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        // 2. حساب الكمية النهائية بناءً على النوع
        if ($request->product_type === 'fractional') {
            $finalQuantity = (float)$request->num_rolls * (float)$request->roll_length;
            $rollLength = $request->roll_length;
            $isSplittable = false;
        } else {
            $finalQuantity = $request->quantity;
            $rollLength = 0;
            $isSplittable = $request->has('is_splittable');
        }

        // 3. إنشاء المنتج الأساسي
        try {
            $product = Product::create([
                'store_id'         => $store->id,
                'user_id'          => auth()->id(),
                'category_id'      => $request->category_id,
                'name'             => $request->name,
                'slug'             => $slug,
                'description'      => $request->description,
                'price'            => $request->price,
                'cost_price'       => $request->cost_price,
                'quantity'         => $finalQuantity,
                'roll_length'      => $rollLength,
                'min_stock'        => $request->min_stock ?? 1,
                'status'           => $request->status,
                'image'            => $imagePath,
                'carton_qty'       => $request->carton_qty, // حفظ سعة الكرتون
                'product_type'     => $request->product_type,
                'usage_type'       => $request->input('usage_type', Product::USAGE_TYPE_SALE),
                'waste_percentage' => $request->waste_percentage ?? 0,
                // حقول الأطقم
                'is_splittable'    => $isSplittable,
                'items_per_unit'   => $isSplittable ? $request->items_per_unit : 1,
                'piece_price'      => $request->piece_price,
                'quick_sale_default_unit' => $request->has('is_splittable') ? ($request->quick_sale_default_unit ?? 'unit') : 'unit',
            ]);
        } catch (QueryException $e) {
            if ($this->isProductsSlugUniqueViolation($e)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'تعذر حفظ المنتج بسبب تعارض تقني في الرابط المختصر، حاول الحفظ مرة أخرى.',
                        'errors' => ['name' => ['تعذر حفظ المنتج بسبب تعارض تقني في الرابط المختصر، حاول الحفظ مرة أخرى.']],
                    ], 422);
                }

                return back()->withErrors([
                    'name' => 'تعذر حفظ المنتج بسبب تعارض تقني في الرابط المختصر، حاول الحفظ مرة أخرى.'
                ])->withInput();
            }

            throw $e;
        }

        // 4. حفظ خيارات التجزئة إذا كان المنتج fractional
        if ($request->product_type === 'fractional' && $request->has('fractions')) {
            foreach ($request->fractions as $fraction) {
                $product->fractions()->create([
                    'option_label'    => $fraction['option_label'],
                    'deduction_value' => $fraction['deduction_value'],
                    'price'           => $fraction['price'],
                ]);
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'تم إنشاء المنتج بنجاح.',
                'product' => $product->only(['id', 'name', 'cost_price', 'usage_type']),
            ], 201);
        }

        if ($request->has('stay_here')) {
            return redirect()
                ->route('user.stores.products.create', $store->id)
                ->with('success', 'تم إضافة المنتج بنجاح، يمكنك إضافة منتج آخر');
        }

        return redirect()
            ->route('user.stores.products.index', $store->id)
            ->with('success', 'تم إضافة المنتج بنجاح');
    }

    public function edit(Store $store, Product $product)
    {
        $this->ensureProductBelongsToStore($store, $product);

        $categories = Category::where('store_id', $store->id)->get();
        // جلب المنتج مع خيارات التجزئة المرتبطة به
        $product->load('fractions');
        return view('user.stores.products.edit', compact(
            'store',
            'product',
            'categories'
        ));
    }

    public function update(Request $request, Store $store, Product $product)
    {
        $this->ensureProductBelongsToStore($store, $product);

        $request->validate([
            'name'             => 'required|string|max:255',
            'category_id'      => ['required', Rule::exists('categories', 'id')->where('store_id', $store->id)],
            'price'            => 'required|numeric|min:0',
            'cost_price'       => 'nullable|numeric|min:0',
            'min_stock'        => 'nullable|numeric|min:0',
            'status'           => 'required|in:active,inactive',
            'image'            => 'nullable|image|max:2048',
            'product_type'     => 'required|in:standard,fractional',
            'usage_type'       => 'nullable|in:sale,owner_purchase',
            'waste_percentage' => 'nullable|numeric|min:0|max:100',
            'roll_length'      => 'exclude_unless:product_type,fractional|required|numeric|min:0.01',
            'carton_qty'       => 'nullable|integer|min:1', // حقل سعة الكرتون الجديد في التعديل
            'is_splittable'    => 'nullable|boolean',
            'items_per_unit'   => 'required_if:is_splittable,1|nullable|integer|min:1',
            'piece_price'      => 'nullable|numeric|min:0',
            'quick_sale_default_unit' => 'nullable|in:unit,piece',

            'fractions'        => 'exclude_unless:product_type,fractional|required|array',
            'fractions.*.option_label'    => 'exclude_unless:product_type,fractional|required|string',
            'fractions.*.deduction_value' => 'exclude_unless:product_type,fractional|required|numeric',
            'fractions.*.price'           => 'exclude_unless:product_type,fractional|required|numeric',
        ], $this->productValidationMessages());

        $usageType = $request->input('usage_type', Product::USAGE_TYPE_SALE);
        if ($this->hasDisallowedDuplicateProductName($store->id, $request->name, $usageType, (int) $product->id)) {
            return back()
                ->withErrors(['name' => 'هذا المنتج موجود مسبقاً في المنتجات أو سلة المحذوفات.'])
                ->withInput();
        }

        if ($request->product_type === 'standard' && ! $request->boolean('is_splittable') && $request->filled('min_stock')) {
            $minStock = (float) $request->min_stock;
            if (abs($minStock - round($minStock)) > 0.0001) {
                return back()
                    ->withErrors(['min_stock' => 'الحد الأدنى للمنتج العادي بالحبة يجب أن يكون رقمًا صحيحًا. فعّل نظام الطقم إذا كنت تحتاج نصف طقم أو ربع طقم.'])
                    ->withInput();
            }
        }

        if ($request->product_type === 'standard' && $request->boolean('is_splittable') && $request->filled('min_stock')) {
            $itemsPerUnit = max(1, (int) $request->items_per_unit);
            $piecesEquivalent = (float) $request->min_stock * $itemsPerUnit;
            if (abs($piecesEquivalent - round($piecesEquivalent)) > 0.0001) {
                return back()
                    ->withErrors(['min_stock' => "الحد الأدنى للطقم يجب أن يعادل عدد حبات صحيحًا. إذا كان الطقم {$itemsPerUnit} حبات فاستخدم قيمة تتحول إلى حبات كاملة."])
                    ->withInput();
            }
        }

        // ملاحظة مهمة:
        // واجهة التعديل تعرض roll_length للمنتج الكَسري، لذلك يجب التحقق منه
        // وحفظه هنا فعلياً حتى لا تبقى الواجهة تعرض قيمة لا تنعكس في قاعدة البيانات.

        /*
         * عند تعديل التكلفة أو أي حقل آخر دون تغيير الاسم يجب إبقاء slug الحالي.
         * إعادة بنائه في كل حفظ كانت تسبب تعارضاً وهمياً لبعض المنتجات القديمة
         * التي تملك slug مختلفاً عن الصيغة الحالية المرتبطة بالمتجر.
         */
        $slug = $this->resolveProductSlugForUpdate($product, $request->name, $store->id);

        $imagePath = $product->image;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $oldSalePrice = (float) $product->price;
        $oldCostPrice = (float) ($product->cost_price ?? 0);

        try {
            $product->update([
                'category_id'      => $request->category_id,
                'name'             => $request->name,
                'slug'             => $slug,
                'description'      => $request->description,
                'price'            => $request->price,
                'cost_price'       => $request->cost_price,
                'min_stock'        => $request->min_stock ?? 1,
                'carton_qty'       => $request->carton_qty, // تحديث سعة الكرتون
                'status'           => $request->status,
                'image'            => $imagePath,
                'product_type'     => $request->product_type,
                'usage_type'       => $request->input('usage_type', Product::USAGE_TYPE_SALE),
                'waste_percentage' => $request->waste_percentage ?? 0,
                // نخزن طول الرول فقط للمنتجات الكَسرية.
                // أما إذا تغير النوع إلى standard فنصفر القيمة لتفادي بقاء بيانات fractional قديمة
                // تؤثر على حسابات العرض أو الربحية أو التوريد لاحقاً.
                'roll_length'      => $request->product_type === 'fractional'
                    ? (float) ($request->roll_length ?? 0)
                    : 0,

                'is_splittable'    => $request->has('is_splittable'),
                'items_per_unit'   => $request->has('is_splittable') ? $request->items_per_unit : 1,
                'piece_price'      => $request->piece_price,
                'quick_sale_default_unit' => $request->has('is_splittable') ? ($request->quick_sale_default_unit ?? 'unit') : 'unit',
            ]);
        } catch (QueryException $e) {
            if ($this->isProductsSlugUniqueViolation($e)) {
                return back()->withErrors([
                    'name' => 'تعذر تحديث المنتج بسبب تعارض تقني في الرابط المختصر، حاول الحفظ مرة أخرى.'
                ])->withInput();
            }

            throw $e;
        }

        $newSalePrice = (float) $product->price;
        $newCostPrice = (float) ($product->cost_price ?? 0);
        if (abs($oldSalePrice - $newSalePrice) > 0.0001 || abs($oldCostPrice - $newCostPrice) > 0.0001) {
            app(LogService::class)->add('product_price_changed', 'تم تعديل سعر المنتج: ' . $product->name, $product, [
                'product_name' => $product->name,
                'old_price' => $oldSalePrice,
                'new_price' => $newSalePrice,
                'old_cost_price' => $oldCostPrice,
                'new_cost_price' => $newCostPrice,
            ]);
        }

        // معالجة الكسور (Fractions)
        if ($request->product_type === 'fractional') {
            $product->fractions()->delete();
            foreach ($request->fractions as $fraction) {
                $product->fractions()->create([
                    'option_label'    => $fraction['option_label'],
                    'deduction_value' => $fraction['deduction_value'],
                    'price'           => $fraction['price'],
                ]);
            }
        } else {
            $product->fractions()->delete();
        }

        return redirect()->route('user.stores.products.index', $store->id)
                         ->with('success', 'تم تحديث المنتج بنجاح');
    }

    public function destroy(Store $store, Product $product)
    {
        $this->ensureProductBelongsToStore($store, $product);

        $product->delete();

        return redirect()->route('user.stores.products.index', $store->id)
                         ->with('success', 'تم حذف المنتج');
    }

    public function trash(Store $store)
    {
        $query = Product::onlyTrashed()->where('store_id', $store->id);

        if (! app(SupportSessionService::class)->active()) {
            $query->whereNotIn('id', ArchivedItem::query()
                ->where('archivable_type', Product::class)
                ->where('status', 'archived')
                ->select('archivable_id'));
        }

        $products = $query->with('archivedItem')->get();
        return view('user.stores.products.trash', compact('store', 'products'));
    }


    public function emptyTrash(Store $store)
    {
        $products = Product::onlyTrashed()
            ->where('store_id', $store->id)
            ->whereNotIn('id', ArchivedItem::query()
                ->where('archivable_type', Product::class)
                ->where('status', 'archived')
                ->select('archivable_id'))
            ->get();

        $deletedCount = 0;

        foreach ($products as $product) {
            $this->archiveProduct($store, $product);
            $deletedCount++;
        }

        $message = $deletedCount > 0
            ? "تم حذف {$deletedCount} منتج نهائيًا من حسابك. يمكن طلب الاستعادة من الدعم التقني خلال 30 يومًا."
            : 'لم يتم حذف أي منتج نهائياً.';

        return redirect()->route('user.stores.products.trash', $store->id)
            ->with('success', $message);
    }

    public function restore(Store $store, $id)
    {
        $product = Product::onlyTrashed()
            ->where('store_id', $store->id)
            ->where('id', $id)
            ->firstOrFail();

        $archive = ArchivedItem::where('archivable_type', Product::class)
            ->where('archivable_id', $product->id)
            ->where('status', 'archived')
            ->first();

        if ($archive) {
            abort_unless(app(SupportSessionService::class)->active(), 404);

            $restoreError = DB::transaction(function () use ($store, $product, $archive) {
                $lockedProduct = Product::withTrashed()->lockForUpdate()->findOrFail($product->id);
                $lockedArchive = ArchivedItem::lockForUpdate()->findOrFail($archive->id);

                $nameConflict = Product::withTrashed()
                    ->where('store_id', $store->id)
                    ->where('name', $lockedArchive->original_name)
                    ->where('id', '!=', $lockedProduct->id)
                    ->exists();
                $slugConflict = $lockedArchive->original_slug
                    && $this->productSlugExists($lockedArchive->original_slug, (int) $lockedProduct->id);

                if ($nameConflict || $slugConflict) {
                    $message = 'تعذرت الاستعادة لوجود منتج مطابق في الاسم أو الرابط داخل المتجر.';
                    $lockedArchive->update(['admin_message' => $message]);
                    return $message;
                }

                $lockedProduct->name = $lockedArchive->original_name ?: $lockedProduct->name;
                $lockedProduct->slug = $lockedArchive->original_slug ?: $this->resolveProductSlugForRestore($lockedProduct);
                $lockedProduct->restore();
                $lockedArchive->update([
                    'status' => 'restored',
                    'restored_at' => now(),
                    'restored_by' => app(SupportSessionService::class)->active()?->admin_id,
                    'admin_message' => 'تمت الاستعادة بواسطة الدعم التقني.',
                ]);

                return null;
            });

            if ($restoreError) {
                return back()->with('error', $restoreError);
            }
        } else {
            $product->slug = $this->resolveProductSlugForRestore($product);
            $product->restore();
        }

        return redirect()->route('user.stores.products.trash', $store->id)
                         ->with('success', 'تم استرجاع المنتج');
    }

    public function forceDelete(Store $store, $id)
    {
        // جلب المنتج من المحذوفات
        $product = Product::onlyTrashed()
            ->where('store_id', $store->id)
            ->where('id', $id)
            ->firstOrFail();

        $archive = ArchivedItem::where('archivable_type', Product::class)
            ->where('archivable_id', $product->id)
            ->where('status', 'archived')
            ->first();

        if (! app(SupportSessionService::class)->active() || ! $archive) {
            $this->archiveProduct($store, $product);

            return redirect()->route('user.stores.products.trash', $store->id)
                ->with('success', 'تم حذف المنتج نهائيًا من حسابك. يمكن طلب استعادته من الدعم التقني خلال 30 يومًا.');
        }

        $blockers = $this->permanentDeleteBlockers($product);
        if ($blockers !== []) {
            $archive->update(['admin_message' => 'تعذر الحذف الفعلي لارتباط المنتج بـ ' . implode('، ', $blockers)]);
            return back()->with('error', $archive->admin_message);
        }

        $purged = DB::transaction(function () use ($product, $archive) {
            $lockedProduct = Product::withTrashed()->lockForUpdate()->findOrFail($product->id);
            $lockedArchive = ArchivedItem::lockForUpdate()->findOrFail($archive->id);
            $lateBlockers = $this->permanentDeleteBlockers($lockedProduct);
            if ($lateBlockers !== []) {
                $lockedArchive->update(['admin_message' => 'تعذر الحذف الفعلي لارتباط المنتج بـ ' . implode('، ', $lateBlockers)]);
                return false;
            }
            $image = $lockedProduct->image;

            $lockedProduct->fractions()->delete();
            $lockedProduct->forceDelete();
            $lockedArchive->update([
                'status' => 'purged',
                'admin_message' => 'حُذف السجل فعلياً بواسطة الدعم التقني بعد اجتياز فحوص الارتباطات.',
            ]);

            if ($image) {
                DB::afterCommit(function () use ($image) {
                    if (\Storage::disk('public')->exists($image)) {
                        \Storage::disk('public')->delete($image);
                    }
                });
            }

            return true;
        });

        if (! $purged) {
            return back()->with('error', $archive->fresh()->admin_message);
        }

        return redirect()->route('user.stores.products.trash', $store->id)
                         ->with('success', 'تم حذف المنتج وكافة خياراته نهائياً');
    }

    private function archiveProduct(Store $store, Product $product): ArchivedItem
    {
        return DB::transaction(function () use ($store, $product) {
            $lockedProduct = Product::withTrashed()->lockForUpdate()->findOrFail($product->id);
            $archive = ArchivedItem::lockForUpdate()->firstOrNew([
                'archivable_type' => Product::class,
                'archivable_id' => $lockedProduct->id,
            ]);

            if (! $archive->exists) {
                $supportSession = app(SupportSessionService::class)->active();
                $archive->fill([
                    'owner_id' => $store->user_id,
                    'store_id' => $store->id,
                    'original_name' => $lockedProduct->name,
                    'original_slug' => $lockedProduct->slug,
                    'reference' => 'ARC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
                    'archived_by' => $supportSession?->admin_id ?: auth('web')->id(),
                    'archived_at' => now(),
                    'owner_restore_deadline' => now()->addDays(30),
                ]);
            }

            $archivedSlug = ($archive->original_slug ?: 'product-' . $lockedProduct->id) . '--archived-' . $lockedProduct->id;
            $lockedProduct->slug = $archivedSlug;
            $lockedProduct->saveQuietly();

            $archive->fill([
                'archived_slug' => $archivedSlug,
                'status' => 'archived',
            ])->save();

            app(LogService::class)->add('product_archived', 'تم حذف المنتج نهائيًا من حساب المالك مع إبقاء إمكانية الاستعادة المؤقتة.', $lockedProduct, [
                'archive_reference' => $archive->reference,
                'owner_restore_deadline' => $archive->owner_restore_deadline?->toDateTimeString(),
            ]);

            return $archive;
        });
    }

    public function updateArchiveMessage(Request $request, Store $store, int $id)
    {
        abort_unless(app(SupportSessionService::class)->active(), 403);

        $validated = $request->validate([
            'admin_message' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $archive = ArchivedItem::where('store_id', $store->id)
            ->where('archivable_type', Product::class)
            ->where('archivable_id', $id)
            ->where('status', 'archived')
            ->firstOrFail();

        $archive->update(['admin_message' => $validated['admin_message']]);

        app(LogService::class)->add('product_archive_message_updated', 'حدّث الدعم التقني ملاحظة سجل محذوفات المنتج.', $archive, [
            'archive_reference' => $archive->reference,
        ]);

        return back()->with('success', 'تم حفظ رسالة الدعم الخاصة بالمنتج المحذوف من الحساب.');
    }

    private function permanentDeleteBlockers(Product $product): array
    {
        $checks = [
            'sale_items' => ['label' => 'مبيعات سابقة بلا صورة تاريخية', 'columns' => ['product_id'], 'snapshot' => 'product_name_snapshot'],
            'stock_movements' => ['label' => 'حركات مخزون بلا صورة تاريخية', 'columns' => ['product_id'], 'snapshot' => 'product_name_snapshot'],
            'inventory_logs' => ['label' => 'سجلات جرد بلا صورة تاريخية', 'columns' => ['product_id'], 'snapshot' => 'product_name_snapshot'],
            'purchases' => ['label' => 'مشتريات بلا صورة تاريخية', 'columns' => ['product_id'], 'snapshot' => 'product_name_snapshot'],
            'store_purchase_order_items' => ['label' => 'طلبيات توريد بلا صورة تاريخية', 'columns' => ['product_id', 'matched_product_id'], 'snapshot' => 'custom_product_name'],
            'store_transfer_items' => ['label' => 'تحويلات بين المتاجر بلا صورة تاريخية', 'columns' => ['sender_product_id'], 'snapshot' => 'product_name_snapshot'],
        ];

        $blockers = [];
        foreach ($checks as $table => $config) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = array_values(array_filter(
                $config['columns'],
                fn (string $column) => Schema::hasColumn($table, $column)
            ));

            if ($columns === [] || ! Schema::hasColumn($table, $config['snapshot'])) {
                $blockers[] = $config['label'];
                continue;
            }

            $count = DB::table($table)
                ->where(function ($query) use ($columns, $product) {
                    foreach ($columns as $column) {
                        $query->orWhere($column, $product->id);
                    }
                })
                ->whereNull($config['snapshot'])
                ->count();

            if ($count > 0) {
                $blockers[] = $config['label'];
            }
        }

        return $blockers;
    }


    private function csvValue(array $row, array $col, string $key): ?string
    {
        if (! array_key_exists($key, $col)) {
            return null;
        }

        $index = $col[$key];
        return isset($row[$index]) ? trim((string) $row[$index]) : null;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function toNullableNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function normalizeTransferPrices(?float $salePrice, ?float $costPrice): array
    {
        $hasSale = $salePrice !== null && $salePrice > 0;
        $hasCost = $costPrice !== null && $costPrice > 0;

        if ($hasSale && $hasCost) {
            return [$salePrice, $costPrice];
        }

        return [0, 0];
    }

    private function findProductForImport(Store $store, string $name, ?string $barcode, ?int $categoryId = null): ?Product
    {
        if ($barcode) {
            $byBarcode = Product::where('store_id', $store->id)->where('barcode', $barcode)->first();
            if ($byBarcode) {
                return $byBarcode;
            }
        }

        // حسب الطلب: الاعتماد على اسم المنتج كما هو بالضبط داخل نفس المتجر
        $byExactName = Product::where('store_id', $store->id)
            ->where('name', $name)
            ->first();

        if ($byExactName) {
            return $byExactName;
        }

        $candidateSlug = Str::slug($name);
        $candidateSlug = $candidateSlug !== '' ? $candidateSlug : str_replace(' ', '-', trim($name));

        return Product::where('store_id', $store->id)
            ->where('slug', $candidateSlug)
            ->first();
    }

    private function generateImportCategorySlug(Store $store, string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : str_replace(' ', '-', trim($name));
        $slug = $base;
        $counter = 1;

        while (Category::where('store_id', $store->id)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * يضمن أن المنتج المسترجع من الحذف الناعم لا يصطدم بـ slug منتج نشط أو محذوف آخر.
     */
    protected function resolveProductSlugForRestore(Product $product): string
    {
        $currentSlug = (string) $product->slug;

        if ($currentSlug !== '' && ! $this->productSlugExists($currentSlug, (int) $product->id)) {
            return $currentSlug;
        }

        $base = $this->buildStoreScopedSlug((string) $product->name, (int) $product->store_id);
        $slug = $base . '-restored-' . $product->id;
        $counter = 2;

        while ($this->productSlugExists($slug, (int) $product->id)) {
            $slug = $base . '-restored-' . $product->id . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * يحافظ على slug المنتج عند تعديل بيانات لا تشمل الاسم، ويولّد قيمة جديدة
     * مرتبطة بالمتجر فقط عندما يتغير الاسم فعلياً.
     */

    private function hasDisallowedDuplicateProductName(int $storeId, string $name, string $usageType, ?int $ignoreProductId = null): bool
    {
        $baseSlug = $this->buildStoreScopedSlug($name, $storeId);
        $duplicates = Product::withTrashed()
            ->where('store_id', $storeId)
            ->where('slug', 'like', $baseSlug . '%')
            ->when($ignoreProductId, fn ($query) => $query->where('id', '!=', $ignoreProductId))
            ->get(['id', 'usage_type', 'slug']);

        if ($duplicates->isEmpty()) {
            return false;
        }

        if ($usageType !== Product::USAGE_TYPE_SALE) {
            return true;
        }

        return $duplicates->contains(fn (Product $product) => ($product->usage_type ?? Product::USAGE_TYPE_SALE) !== Product::USAGE_TYPE_OWNER_PURCHASE);
    }

    private function productValidationMessages(): array
    {
        return [
            'name.required' => 'اسم المنتج مطلوب.',
            'category_id.required' => 'القسم مطلوب.',
            'price.required' => 'سعر البيع مطلوب.',
            'price.numeric' => 'سعر البيع يجب أن يكون رقماً.',
            'cost_price.numeric' => 'سعر التكلفة يجب أن يكون رقماً.',
            'quantity.required_if' => 'الكمية الابتدائية مطلوبة للمنتج العادي.',
            'num_rolls.required_if' => 'عدد الرولات مطلوب للمنتج القابل للتجزئة.',
            'roll_length.required' => 'طول الرول مطلوب للمنتج القابل للتجزئة.',
            'fractions.required_if' => 'يجب إضافة خيار تجزئة واحد على الأقل للمنتج القابل للتجزئة.',
            'fractions.*.option_label.required' => 'اسم خيار التجزئة مطلوب.',
            'fractions.*.deduction_value.required' => 'استهلاك خيار التجزئة بالمتر مطلوب.',
            'fractions.*.deduction_value.numeric' => 'استهلاك خيار التجزئة يجب أن يكون رقماً.',
            'fractions.*.price.required' => 'سعر بيع خيار التجزئة مطلوب.',
            'fractions.*.price.numeric' => 'سعر بيع خيار التجزئة يجب أن يكون رقماً.',
        ];
    }

    protected function resolveProductSlugForUpdate(Product $product, string $name, int $storeId): string
    {
        if ($product->name === $name) {
            return (string) $product->slug;
        }

        return $this->buildUniqueStoreScopedSlug($name, $storeId, (int) $product->id);
    }

    private function buildUniqueStoreScopedSlug(string $name, int $storeId, ?int $ignoreProductId = null): string
    {
        $base = $this->buildStoreScopedSlug($name, $storeId);
        $slug = $base;
        $counter = 2;

        while (Product::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreProductId, fn ($query) => $query->where('id', '!=', $ignoreProductId))
            ->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * توليد slug مرتبط بالمتجر لتفادي الاصطدام مع القيد العالمي الحالي على عمود slug.
     */
    private function buildStoreScopedSlug(string $name, int $storeId): string
    {
        $normalized = preg_replace('/\s+/u', '-', trim($name));
        $base = trim((string) $normalized, '-');

        if ($base === '') {
            $base = 'product';
        }

        return "{$base}-s{$storeId}";
    }

    private function productSlugExists(string $slug, int $ignoreProductId): bool
    {
        return Product::withTrashed()
            ->where('slug', $slug)
            ->where('id', '!=', $ignoreProductId)
            ->exists();
    }

    private function isProductsSlugUniqueViolation(QueryException $e): bool
    {
        $errorInfo = $e->errorInfo ?? [];
        $sqlState = $errorInfo[0] ?? null;
        $driverCode = (string) ($errorInfo[1] ?? '');
        $message = (string) ($errorInfo[2] ?? $e->getMessage());

        return $sqlState === '23000'
            && $driverCode === '1062'
            && str_contains($message, 'products_slug_unique');
    }

    private function generateImportProductSlug(Store $store, string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : str_replace(' ', '-', trim($name));
        $slug = $base;
        $counter = 1;

        // products.slug فريد على مستوى الجدول بالكامل وليس على مستوى المتجر فقط
        while (Product::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function decodeFractions(?string $json): array
    {
        if (! $json) {
            return [];
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, function ($item) {
            return is_array($item)
                && ! empty($item['option_label'])
                && isset($item['deduction_value'], $item['price']);
        }));
    }

    private function ensureProductBelongsToStore(Store $store, Product $product): void
    {
        if ((int) $product->store_id !== (int) $store->id) {
            abort(403);
        }
    }

    public function priceHistory(Store $store, Product $product)
    {
        $this->ensureProductBelongsToStore($store, $product);

        $history = Log::with('user')->where('store_id', $store->id)
            ->where('model_type', Product::class)
            ->where('model_id', $product->id)
            ->where('action', 'product_price_changed')
            ->latest()
            ->limit(30)
            ->get()
            ->map(function (Log $log) {
                $details = $log->details ?? [];

                return [
                    'date' => optional($log->created_at)->format('Y-m-d'),
                    'time' => optional($log->created_at)->format('h:i A'),
                    'old_price' => number_format((float) ($details['old_price'] ?? 0), 2),
                    'new_price' => number_format((float) ($details['new_price'] ?? 0), 2),
                    'old_cost_price' => number_format((float) ($details['old_cost_price'] ?? 0), 2),
                    'new_cost_price' => number_format((float) ($details['new_cost_price'] ?? 0), 2),
                    'latest_receipt_unit_cost' => isset($details['latest_receipt_unit_cost'])
                        ? number_format((float) $details['latest_receipt_unit_cost'], 2)
                        : null,
                    'actor' => ($details['source_type'] ?? null) === 'purchase_order'
                        ? ($details['source_name'] ?? 'طلبية توريد')
                        : $log->actor_display_name,
                ];
            });

        return response()->json([
            'product' => [
                'name' => $product->name,
                'price' => number_format((float) $product->price, 2),
                'cost_price' => number_format((float) ($product->cost_price ?? 0), 2),
                'updated_at' => optional($product->updated_at)->format('Y-m-d h:i A'),
            ],
            'history' => $history,
        ]);
    }

    public function toggleStatus(Store $store, Product $product)
    {
        $this->ensureProductBelongsToStore($store, $product);

        $product->status = $product->status === 'active' ? 'inactive' : 'active';
        $product->save();

        return redirect()->route('user.stores.products.index', $store->id)
                         ->with('success', 'تم تحديث حالة المنتج');
    }
}
