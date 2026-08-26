@extends('dashboard.app')
@section('title', 'متجر — ' . $store->name)
@section('content')

@php
    // =============================================
    // جميع المتغيرات والإحصائيات في كتلة واحدة
    // =============================================

    // 2. إحصائيات سريعة
    // ملاحظة ربط: نستخدم القيم القادمة من الكنترولر عند توفرها لتفادي ازدواج الحساب داخل الـ Blade.
    $accountantsCount = $accountantsCount ?? $store->accountants()->count();
    $employeesCount = $employeesCount ?? $store->employees()->count();
    $pendingEmployeesCount = $store->employees()->whereDoesntHave('accountant')->count();
    $productsCount = $productsCount ?? $store->products()->where('status', 'active')->sellable()->count();
    $lowStockProducts = $store->products()
        ->where('status', 'active')
        ->sellable()
        ->lowStock()
        ->count();
    $lowStockProductsList = $store->products()
        ->where('status', 'active')
        ->sellable()
        ->lowStock()
        ->latest()
        ->limit(6)
        ->get(['id', 'name', 'quantity', 'min_stock', 'price', 'created_at', 'product_type', 'roll_length', 'is_splittable', 'items_per_unit', 'quick_sale_default_unit']);
    $latestProducts = $store->products()
        ->where('status', 'active')
        ->sellable()
        ->latest()
        ->limit(6)
        ->get(['id', 'name', 'quantity', 'min_stock', 'price', 'created_at', 'product_type', 'roll_length', 'is_splittable', 'items_per_unit', 'quick_sale_default_unit']);
    $inventoryAuditProducts = $store->products()->where('status', 'active')->sellable()->get();
    $inventoryAuditCounts = ['total' => $inventoryAuditProducts->count(), 'red' => 0, 'yellow' => 0, 'green' => 0];
    foreach ($inventoryAuditProducts as $inventoryAuditProduct) {
        $inventoryAuditColor = $inventoryAuditProduct->inventoryAuditStatus($store)['color'] ?? 'red';
        $inventoryAuditCounts[$inventoryAuditColor] = ($inventoryAuditCounts[$inventoryAuditColor] ?? 0) + 1;
    }
    $inventoryAuditCycleStart = \App\Models\Product::inventoryAuditCycleStart($store);
    // نهاية الدورة تتبع مدة الجرد المحفوظة للمتجر وتحافظ على سلامة نهاية الشهر.
    $inventoryAuditCycleEnd = $inventoryAuditCycleStart->copy()->addMonthsNoOverflow($store->inventoryAuditCycleMonths());
    $invoicesCount = $invoicesCount ?? $store->sales()->count();
    $categoriesCount = $categoriesCount ?? $store->categories()->count();
    $consumptionCount = $consumptionCount ?? $store->sales()->where('sale_type', 'internal_use')->count();
    // توحيد مصدر استهلاك المحاسب مع صفحة المشتريات (تعتمد على sales.total)
    $monthlyAccountantConsumption = (float) \DB::table('sales')
        ->where('store_id', $store->id)
        ->where('sale_type', 'internal_use')
        ->whereYear('created_at', now()->year)
        ->whereMonth('created_at', now()->month)
        ->where(function ($query) {
            $query->whereNull('description')
                ->orWhere('description', '!=', 'manual_invoice_entry');
        })
        ->selectRaw('COALESCE(SUM(COALESCE(total, paid_amount, products_total, 0)), 0) as internal_use_total')
        ->value('internal_use_total');

    // نفس مصدر صفحة "المشتريات" لبطاقات المقارنة
    $monthlyOwnerPurchases = \App\Models\Purchase::where('store_id', $store->id)
        ->whereYear('created_at', now()->year)
        ->whereMonth('created_at', now()->month)
        ->sum('cost');
    $monthlyOperationalPurchases = \App\Models\Purchase::where('store_id', $store->id)
        ->whereYear('created_at', now()->year)
        ->whereMonth('created_at', now()->month)
        ->whereNull('product_id')
        ->sum('cost');
    $monthlyPurchasesAndConsumption = (float) $monthlyAccountantConsumption + (float) $monthlyOwnerPurchases;

    // 3. المبيعات (المحصل الفعلي + عدد العمليات) لتفادي أي لبس في البطاقات
    $includedSaleTypes = ['cash', 'card', 'credit', 'mixed'];
    $todaySalesCount = $store->sales()
        ->whereDate('created_at', today())
        ->whereIn('sale_type', $includedSaleTypes)
        ->count();
    $monthSalesCount = $store->sales()
        ->whereYear('created_at', now()->year)
        ->whereMonth('created_at', now()->month)
        ->whereIn('sale_type', $includedSaleTypes)
        ->count();

    $todaySales = $todaySales ?? $store->sales()
        ->whereDate('created_at', today())
        ->whereIn('sale_type', $includedSaleTypes)
        ->sum('paid_amount');
    $monthSales = $monthSales ?? $store->sales()
        ->whereYear('created_at', now()->year)
        ->whereMonth('created_at', now()->month)
        ->whereIn('sale_type', $includedSaleTypes)
        ->sum('paid_amount');

    // 4. بيانات الرسم البياني (آخر 7 أيام)
    if (!isset($chartData, $chartLabels, $profitData)) {
        $chartData = [];
        $chartLabels = [];
        $profitData = [];
        for($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $chartLabels[] = $date->format('d/m');
            $daySales = $store->sales()
                ->whereDate('created_at', $date)
                ->whereIn('sale_type', $includedSaleTypes)
                ->get();
            $chartData[] = $daySales->sum('paid_amount');
            $profitData[] = $daySales->sum(fn($sale) => (float) ($sale->paid_amount ?? 0) - (((float) ($sale->products_total ?? 0) + (float) ($sale->labor_total ?? 0)) - (float) ($sale->profit ?? 0)));
        }
    }

    // 5. أفضل المنتجات مبيعاً
    $topProducts = $topProducts ?? \DB::table('sale_items')
        ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
        ->leftJoin('products', 'sale_items.product_id', '=', 'products.id')
        ->where('sales.store_id', $store->id)
        ->where(function ($query) {
            $query->where('sale_items.product_usage_snapshot', \App\Models\Product::USAGE_TYPE_SALE)
                ->orWhere(function ($legacy) {
                    $legacy->whereNull('sale_items.product_usage_snapshot')
                        ->where(function ($usage) {
                            $usage->where('products.usage_type', \App\Models\Product::USAGE_TYPE_SALE)
                                ->orWhereNull('products.usage_type');
                        });
                });
        })
        ->where('sale_items.created_at', '>=', now()->subDays(30))
        ->selectRaw("COALESCE(sale_items.product_name_snapshot, products.name, 'منتج محذوف') as name")
        ->selectRaw('COALESCE(sale_items.sale_price_snapshot, products.price, 0) as price')
        ->selectRaw('SUM(COALESCE(sale_items.quantity_snapshot, sale_items.quantity)) as total_sold')
        ->groupBy('sale_items.product_name_snapshot', 'products.name', 'sale_items.sale_price_snapshot', 'products.price')
        ->orderBy('total_sold', 'desc')
        ->limit(10)
        ->get();

    // 6. آخر العمليات
    $operations = $operations ?? \App\Models\Log::where('store_id', $store->id)
        ->with('user')
        ->latest()
        ->limit(10)
        ->get();

    $monthStart = now()->copy()->startOfMonth();
    $monthEnd = now()->copy()->endOfMonth();
    $daysInMonth = (int) now()->daysInMonth;

    // 8. بطاقات التنقل السريع
    $cards = [
        [
            'title' => 'الموظفين',
            'icon' => 'fa-users',
            'color' => 'green',
            'count' => $employeesCount,
            'desc' => $pendingEmployeesCount > 0 ? $pendingEmployeesCount.' موظف جديد' : 'إدارة الموظفين',
            'tooltip' => 'متابعة جميع موظفي ومحاسبي المتجر وإدارة بياناتهم',
            'url' => route('user.employees.index', ['store' => $store->id])
        ],
        [
            'title' => 'المبيعات',
            'icon' => 'fa-chart-line',
            'color' => 'yellow',
            'count' => $todaySalesCount,
            'desc' => 'محصل اليوم '.number_format($todaySales, 2).' ر.س',
            'tooltip' => 'الانتقال لصفحة مبيعات اليوم والتعديلات اليومية',
            'url' => route('user.stores.daily', $store->id)
        ],
        [
            'title' => 'التقارير',
            'icon' => 'fa-file-alt',
            'color' => 'blue',
            'count' => '3',
            'desc' => 'تقارير المتجر',
            'tooltip' => 'فتح مركز التقارير (يومي / شهري / PDF)',
            'url' => route('user.stores.reports.index', $store->id)
        ],
        [
            'title' => 'الأقسام',
            'icon' => 'fa-layer-group',
            'color' => 'warning',
            'count' => $categoriesCount,
            'desc' => 'إدارة الأقسام والأنشطة',
            'tooltip' => 'إدارة أقسام وأنشطة المتجر بشكل مستقل عن صفحة المنتجات',
            'url' => route('user.stores.categories.index', $store->id)
        ],
        [
            'title' => 'المنتجات',
            'icon' => 'fa-boxes-stacked',
            'color' => 'cyan',
            'count' => $productsCount,
            'desc' => 'إدارة المنتجات والمخزون',
            'tooltip' => 'فتح صفحة منتجات المتجر ومتابعة أسعارها وكمياتها',
            'url' => route('user.stores.products.index', $store->id)
        ],
        [
            'title' => 'طلبيات توريد',
            'icon' => 'fa-clipboard-list',
            'color' => 'warning',
            'count' => 'جديد',
            'desc' => 'طلبات الموردين',
            'tooltip' => 'إنشاء ومراجعة طلبيات التوريد قبل تحديث المخزون',
            'url' => route('user.stores.purchase-orders.index', $store->id)
        ],
        [
            'title' => 'المشتريات',
            'icon' => 'fa-bolt',
            'color' => 'purple',
            'count' => number_format($monthlyPurchasesAndConsumption, 2) . ' ر.س',
            'desc' => 'الإجمالي الشهري',
            'tooltip' => 'استهلاك المحاسب + مشتريات المالك في صفحة واحدة',
            'url' => route('user.stores.internal-use.report.view', $store->id)
        ]
    ];

    // 9. بطاقات "مبيعات اليوم" (بدون السحب)
    $todayExpenses = (float) \App\Models\Expense::where('store_id', $store->id)
        ->whereDate('created_at', today())
        ->sum('amount');

    $todayCash = (float) $store->sales()
        ->whereDate('created_at', today())
        ->whereIn('sale_type', $includedSaleTypes)
        ->sum('cash_amount');

    $todayCard = (float) $store->sales()
        ->whereDate('created_at', today())
        ->whereIn('sale_type', $includedSaleTypes)
        ->sum('card_amount');

    $todayCredit = (float) $store->sales()
        ->whereDate('created_at', today())
        ->whereIn('sale_type', $includedSaleTypes)
        ->sum('remaining_amount');

    $todayFinanceStats = [
        ['title'=>'محصل اليوم','value'=>number_format($todaySales,2),'icon'=>'fa-calendar-day','color'=>'blue','desc'=>now()->translatedFormat('Y/m/d').' • '.$todaySalesCount.' عملية (المحصل)','tooltip'=>'إجمالي المبالغ المحصلة فعليًا اليوم','unit'=>'ر.س'],
        ['title'=>'مصروف اليوم','value'=>number_format($todayExpenses,2),'icon'=>'fa-file-invoice-dollar','color'=>'red','desc'=>'مصروفات اليوم','tooltip'=>'إجمالي المصروفات المسجلة خلال اليوم','unit'=>'ر.س'],
        ['title'=>'كاش اليوم','value'=>number_format($todayCash,2),'icon'=>'fa-money-bill-wave','color'=>'green','desc'=>'نقدي','tooltip'=>'إجمالي المدفوعات النقدية اليوم','unit'=>'ر.س'],
        ['title'=>'شبكة اليوم','value'=>number_format($todayCard,2),'icon'=>'fa-credit-card','color'=>'cyan','desc'=>'مدفوعات شبكة','tooltip'=>'إجمالي المدفوعات بالشبكة اليوم','unit'=>'ر.س'],
        ['title'=>'آجل اليوم','value'=>number_format($todayCredit,2),'icon'=>'fa-hourglass-half','color'=>'yellow','desc'=>'آجل جديد','tooltip'=>'قيمة الآجل الجديد الناتج عن مبيعات اليوم','unit'=>'ر.س'],
    ];

    // 10. بطاقات ملخص الشهر
    $monthExpenses = (float) \App\Models\Expense::where('store_id', $store->id)->whereBetween('created_at', [$monthStart, $monthEnd])->sum('amount');
    $monthlySalaries = (float) $store->employees()->sum('salary');
    $monthlyWithdrawals = (float) \App\Models\Withdrawal::where('store_id', $store->id)->whereBetween('created_at', [$monthStart, $monthEnd])->sum('amount');
    $netMonthlySalaries = max(0, (float) $monthlySalaries - (float) $monthlyWithdrawals);

    if (\Illuminate\Support\Facades\Schema::hasColumn('sale_items', 'total_cost')) {
        $monthlySoldProductsCost = (float) \DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.store_id', $store->id)
            ->whereBetween('sales.created_at', [$monthStart, $monthEnd])
            ->whereIn('sales.sale_type', $includedSaleTypes)
            ->where(function ($query) {
                $query->whereNull('sales.description')
                    ->orWhere('sales.description', '!=', 'manual_invoice_entry');
            })
            ->sum(\DB::raw('COALESCE(sale_items.total_cost, 0)'));
    } else {
        $monthlySoldProductsCost = (float) $store->sales()
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->whereIn('sale_type', $includedSaleTypes)
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', '!=', 'manual_invoice_entry');
            })
            ->sum('products_total');
    }

    // مطابق للتقرير الشهري: صافي النتيجة = المحصل - (تكلفة المنتجات المباعة + استهلاك المحاسب + مشتريات المالك + المصروفات).
    // الرواتب والسحبيات تعرض للتوضيح فقط ولا تدخل في معادلة الربح.
    $monthNetProfit = (float) $monthSales - ((float) $monthlySoldProductsCost + (float) $monthlyAccountantConsumption + (float) $monthlyOwnerPurchases + (float) $monthExpenses);

    $monthFinanceStats = [
        ['title'=>'إجمالي المبيعات','value'=>number_format($monthSales,2),'icon'=>'fa-wallet','color'=>'green','desc'=>now()->translatedFormat('Y/m'),'tooltip'=>'إجمالي المحصل الفعلي خلال الشهر','unit'=>'ر.س'],
        ['title'=>'إجمالي المصروفات','value'=>number_format($monthExpenses,2),'icon'=>'fa-receipt','color'=>'red','desc'=>'','tooltip'=>'إجمالي المصروفات المسجلة خلال الشهر','unit'=>'ر.س'],
        ['title'=>'استهلاك المحاسب','value'=>number_format($monthlyAccountantConsumption,2),'icon'=>'fa-box-open','color'=>'yellow','desc'=>'','tooltip'=>'استهلاك المحاسب يُعرض منفصلًا عن بطاقة مشتريات المالك','unit'=>'ر.س'],
        ['title'=>'تكلفة المنتجات المباعة','value'=>number_format($monthlySoldProductsCost,2),'icon'=>'fa-cart-shopping','color'=>'cyan','desc'=>'','tooltip'=>'إجمالي تكلفة المنتجات المباعة بسعر التكلفة','unit'=>'ر.س'],
        ['title'=>'مشتريات المالك','value'=>number_format($monthlyOwnerPurchases,2),'icon'=>'fa-truck-ramp-box','color'=>'blue','desc'=>'','tooltip'=>'قيمة مشتريات المالك المعروضة في التقرير الشهري','unit'=>'ر.س'],
        ['title'=>'صافي الرواتب','value'=>number_format($netMonthlySalaries,2),'icon'=>'fa-user-tie','color'=>'purple','desc'=>'','tooltip'=>'صافي الرواتب بعد خصم السحوبات','unit'=>'ر.س'],
        ['title'=>$monthNetProfit<0?'الخسائر':'الأرباح','value'=>number_format(abs($monthNetProfit),2),'icon'=>'fa-chart-line','color'=>$monthNetProfit>=0?'success':'danger','desc'=>'','tooltip'=>$monthNetProfit<0?'إجمالي الخسارة = (المصروفات + استهلاك المحاسب + مشتريات المالك + تكلفة المنتجات المباعة) - المحصل':'إجمالي الربح = المحصل - (تكلفة المنتجات المباعة + استهلاك المحاسب + مشتريات المالك + المصروفات)','unit'=>'ر.س'],
    ];

    // 11. بطاقة مستحقات الموظفين
    $employeeMonthlyWithdrawals = (float) \App\Models\Withdrawal::where('store_id', $store->id)
        ->where('person_type', \App\Models\Employee::class)
        ->whereBetween('created_at', [$monthStart, $monthEnd])
        ->sum('amount');
    $employeeMonthlyDebts = (float) \App\Models\Debt::where('store_id', $store->id)
        ->where('person_type', \App\Models\Employee::class)
        ->whereBetween('created_at', [$monthStart, $monthEnd])
        ->sum('amount');
    $employees = $store->employees()->get(['id', 'salary']);
    $employeeIds = $employees->pluck('id');
    $absenceRows = \App\Models\Absence::where('store_id', $store->id)
        ->where('person_type', \App\Models\Employee::class)
        ->whereIn('person_id', $employeeIds)
        ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
        ->selectRaw('person_id, COUNT(*) as absence_days')
        ->groupBy('person_id')
        ->pluck('absence_days', 'person_id')
        ->map(fn($days) => (int) $days);

    $employeeMonthlyAbsenceCost = 0.0;
    foreach ($employees as $employee) {
        $days = (int) ($absenceRows[$employee->id] ?? 0);
        if ($days > 0) {
            $dailySalary = ((float) $employee->salary) / max($daysInMonth, 1);
            $employeeMonthlyAbsenceCost += $dailySalary * $days;
        }
    }

    $employeeRemainingSalary = (float) $monthlySalaries - ((float) $employeeMonthlyWithdrawals + (float) $employeeMonthlyAbsenceCost + (float) $employeeMonthlyDebts);
    $employeePayrollStats = [
        ['title'=>'راتب الموظف (الإجمالي)','value'=>number_format($monthlySalaries,2),'icon'=>'fa-sack-dollar','color'=>'blue','desc'=>'إجمالي رواتب الشهر قبل السحب','tooltip'=>'الراتب الأساسي الإجمالي لكل الموظفين قبل خصم السحبيات','unit'=>'ر.س'],
        ['title'=>'سحبيات الموظف','value'=>number_format($employeeMonthlyWithdrawals,2),'icon'=>'fa-hand-holding-dollar','color'=>'yellow','desc'=>'إجمالي السحبيات الشهرية','tooltip'=>'مجموع سحبيات الموظفين خلال الشهر','unit'=>'ر.س'],
        ['title'=>'صافي الرواتب','value'=>number_format($netMonthlySalaries,2),'icon'=>'fa-wallet','color'=>'indigo','desc'=>'بعد خصم السحبيات','tooltip'=>'قيمة توضيحية فقط ولا تدخل في الربحية الشهرية','unit'=>'ر.س'],
        ['title'=>'قيمة غياب الموظف','value'=>number_format($employeeMonthlyAbsenceCost,2),'icon'=>'fa-user-clock','color'=>'red','desc'=>'محتسبة: الراتب ÷ أيام الشهر × أيام الغياب','tooltip'=>'خصم الغياب محسوب بناءً على الأجر اليومي لكل موظف','unit'=>'ر.س'],
        ['title'=>'المديونية','value'=>number_format($employeeMonthlyDebts,2),'icon'=>'fa-file-invoice','color'=>'warning','desc'=>'إجمالي المديونيات الشهرية','tooltip'=>'إجمالي المديونيات المسجلة على الموظفين','unit'=>'ر.س'],
        ['title'=>'المتبقي من الراتب','value'=>number_format($employeeRemainingSalary,2),'icon'=>'fa-coins','color'=>$employeeRemainingSalary>=0?'success':'danger','desc'=>'الراتب - (السحبيات + الغياب + المديونية)','tooltip'=>'المتبقي المستحق بعد الخصومات والسحبيات والمديونيات','unit'=>'ر.س'],
    ];

@endphp



<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

    {{-- ===== شريط العنوان والأزرار (مصغر) ===== --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-5">
        <div class="flex items-center gap-3">
            @if($store->logo)
                <img src="{{ Storage::url($store->logo) }}"
                     alt="{{ $store->name }}"
                     class="w-10 h-10 rounded-2xl object-cover border ui-border shadow-lg">
            @else
                <div class="w-10 h-10 ui-op-accent-info rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fa-solid fa-store ui-title text-sm"></i>
                </div>
            @endif
            <div>
                <h1 class="text-xl md:text-2xl font-bold ui-title">
                    {{ $store->name }}
                </h1>
                <span class="px-2 py-0.5 rounded-full ui-text-caption font-bold {{ $store->status == 'active' ? 'ui-status-success-bg ui-status-success border ui-border' : 'ui-status-danger-bg ui-status-danger' }}">
                    {{ $store->status == 'active' ? 'نشط' : 'معطل' }}
                </span>
                {{-- بيانات التواصل الأساسية بلا بطاقة أو حدود إضافية. --}}
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 ui-text-caption ui-text-soft">
                    <span><i class="fa-solid fa-location-dot ml-1" aria-hidden="true"></i>{{ $store->address ?: 'العنوان غير مضاف' }}</span>
                    <span dir="ltr"><i class="fa-solid fa-phone mr-1" aria-hidden="true"></i>{{ $store->phone ?: 'رقم الهاتف غير مضاف' }}</span>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('user.stores.edit', ['store' => $store->id, 'return_to' => 'show']) }}"
               class="ui-btn ui-btn-primary px-3 py-2 text-sm">
                <i class="fa-solid fa-edit ui-text-caption"></i>
                <span>تعديل</span>
            </a>

            <a href="{{ route('user.stores.index') }}"
               class="ui-btn ui-btn-secondary px-3 py-2 text-sm">
                <i class="fa-solid fa-arrow-right ui-text-caption"></i>
                <span>رجوع</span>
            </a>
        </div>
    </div>

    @if($secondShiftRestoreCandidate)
        <div class="mb-6 rounded-2xl border {{ $secondShiftRestoreBlocked ? 'ui-border ui-status-danger-bg' : 'ui-border ui-status-warning-bg' }} p-4 shadow-lg">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="ui-title font-bold flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left {{ $secondShiftRestoreBlocked ? 'ui-status-danger' : 'ui-status-warning' }}"></i>
                        إعادة تفعيل الشفت الثاني
                    </h2>
                    <p class="text-sm {{ $secondShiftRestoreBlocked ? 'ui-status-danger' : 'ui-status-warning' }} mt-2 leading-6">
                        آخر إقفال انتقل إلى تاريخ العمل التالي بدل فتح الشفت الثاني.
                        @if($secondShiftRestoreBlocked)
                            توجد عمليات مسجلة بعد هذا الإقفال، لذلك لا يمكن إعادة التفعيل تلقائيًا ويجب مراجعة البيانات أولًا.
                        @else
                            يمكن للمالك إعادة تفعيل الشفت الثاني لنفس التاريخ إذا كان رفضه تم بالخطأ.
                        @endif
                    </p>
                </div>

                @if(! $secondShiftRestoreBlocked)
                    {{-- إصلاح مطبق: تأكيد إعادة تفعيل الشفت الثاني موحد عبر الحوار المركزي دون تغيير شروط الإتاحة. --}}
                    <form method="POST" action="{{ route('user.stores.restore-second-shift', $store->id) }}"
                          data-ui-confirm="هل تريد إعادة تفعيل الشفت الثاني لنفس التاريخ؟"
                          data-ui-confirm-title="تأكيد إعادة التفعيل">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl ui-status-warning-bg ui-status-warning font-bold px-4 py-2 transition">
                            <i class="fa-solid fa-rotate-left"></i>
                            إعادة التفعيل
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    {{-- ===== معلومات المتجر (مصغرة جداً) ===== --}}
    <div class="ui-card rounded-xl p-4 mb-6">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
            <div class="rounded-lg ui-status-info-bg py-2">
                <p class="ui-status-info ui-text-caption font-semibold mb-1">رقم المتجر</p>
                <p class="ui-title text-sm font-mono flex items-center justify-center gap-1">
                    <i class="fa-solid fa-hashtag ui-status-info ui-text-caption"></i>
                    {{ str_pad($store->id, 4, '0', STR_PAD_LEFT) }}
                </p>
            </div>
            <div>
                <p class="ui-text-muted ui-text-caption">تاريخ الإنشاء</p>
                <p class="ui-title text-sm">{{ $store->created_at->translatedFormat('Y/m/d') }}</p>
            </div>
            <div>
                <p class="ui-text-muted ui-text-caption">الورديات</p>
                <p class="ui-title text-sm">{{ $store->number_of_shifts }} وردية</p>
            </div>
            <div>
                <p class="ui-text-muted ui-text-caption">المنتجات</p>
                <p class="ui-title text-sm">{{ $productsCount }}</p>
            </div>
        </div>

    </div>

    {{-- ===== بطاقة حالة الجرد المدمجة من صفحة الكاتلوج ===== --}}
    <div class="ui-card p-5 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold ui-title flex items-center gap-2">
                    <i class="fa-solid fa-clipboard-check ui-status-success"></i>
                    حالة جرد المنتجات
                </h2>
                {{-- نعرض مدة الدورة بجانب نطاقها حتى يتأكد المالك من تطبيق إعداد المتجر. --}}
                <div class="ui-inline-frame mt-3 ui-text-caption">
                    <span class="ui-text-soft">دورة الجرد الحالية ({{ $store->inventoryAuditCycleMonths() }} شهرًا)</span>
                    <strong class="ui-title">{{ $inventoryAuditCycleStart->format('Y-m-d') }} إلى {{ $inventoryAuditCycleEnd->format('Y-m-d') }}</strong>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div class="rounded-xl ui-surface-muted-bg border ui-border px-4 py-3 text-center">
                    <p class="ui-text-muted ui-text-caption">عدد المنتجات</p>
                    <p class="ui-title font-black text-2xl">{{ $inventoryAuditCounts['total'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl ui-status-danger-bg border ui-border px-4 py-3 text-center">
                    {{-- تستخدم النقاط لون الحالة النصي القوي حتى تبقى واضحة فوق خلفيات الوضع الفاتح. --}}
                    <p class="ui-status-danger ui-text-caption flex items-center justify-center gap-1"><span class="w-2 h-2 rounded-full ui-dot-danger" title="أحمر: بيانات ناقصة أو لم تدخل الكمية بعد."></span> أحمر</p>
                    <p class="ui-status-danger font-black text-2xl">{{ $inventoryAuditCounts['red'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl ui-status-warning-bg border ui-border px-4 py-3 text-center">
                    <p class="ui-status-warning ui-text-caption flex items-center justify-center gap-1"><span class="w-2 h-2 rounded-full ui-dot-warning" title="أصفر: المنتج مكتمل البيانات لكن لم يتم تأكيد جرده في دورة الجرد الحالية."></span> أصفر</p>
                    <p class="ui-status-warning font-black text-2xl">{{ $inventoryAuditCounts['yellow'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl ui-status-success-bg border ui-border px-4 py-3 text-center">
                    <p class="ui-status-success ui-text-caption flex items-center justify-center gap-1"><span class="w-2 h-2 rounded-full ui-dot-success" title="أخضر: تم تأكيد جرد المنتج في دورة الجرد الحالية."></span> أخضر</p>
                    <p class="ui-status-success font-black text-2xl">{{ $inventoryAuditCounts['green'] ?? 0 }}</p>
                </div>
            </div>
            <a href="{{ route('user.stores.products.audit', ['store' => $store->id]) }}" class="ui-btn ui-btn-primary px-4 py-2 text-sm shrink-0"><i class="fa-solid fa-arrow-left"></i> فتح صفحة الجرد</a>
        </div>
    </div>

    {{-- ===== الأقسام القابلة للفتح (Accordion) ===== --}}
    <div class="space-y-3 mb-8">
        <details class="ui-card   border ui-border rounded-xl group">
            <summary class="list-none cursor-pointer p-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-compass ui-status-info"></i>
                    <h3 class="ui-title font-bold text-sm">التنقل السريع</h3>
                </div>
                <i class="fa-solid fa-chevron-down ui-text-muted transition-transform group-open:rotate-180"></i>
            </summary>
            <div class="px-4 pb-4">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    @foreach($cards as $card)
                    <a href="{{ $card['url'] }}"
                       title="{{ $card['tooltip'] ?? $card['desc'] }}"
                       class="group ui-card p-3 transition-all ui-hover-info hover:scale-[1.02]">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-8 h-8 ui-status-info-bg rounded-lg flex items-center justify-center">
                                <i class="fa-solid {{ $card['icon'] }} ui-status-info text-sm"></i>
                            </div>
                            <span class="ui-status-info font-bold text-lg">{{ $card['count'] }}</span>
                        </div>
                        <h4 class="ui-title font-bold text-sm mb-1">{{ $card['title'] }}</h4>
                        <p class="ui-text-muted ui-text-caption">{{ $card['desc'] }}</p>
                    </a>
                    @endforeach
                </div>
            </div>
        </details>

        {{-- ===== آخر المنتجات والمخزون المنخفض ===== --}}
        <details open class="ui-card   border ui-border rounded-xl group">
            <summary class="list-none cursor-pointer p-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-boxes-stacked ui-status-success"></i>
                    <h3 class="ui-title font-bold text-sm">متابعة المنتجات</h3>
                </div>
                <i class="fa-solid fa-chevron-down ui-text-muted transition-transform group-open:rotate-180"></i>
            </summary>
            <div class="px-4 pb-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="ui-card p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h4 class="ui-title font-bold text-sm flex items-center gap-2">
                                <i class="fa-solid fa-clock ui-status-info"></i>
                                آخر المنتجات المضافة
                            </h4>
                            <x-ui.help title="آخر المنتجات المضافة" body="أحدث المنتجات المضافة في هذا المتجر" />
                        </div>

                    </div>
                    <div class="space-y-2">
                        @forelse($latestProducts as $product)
                            <div class="flex items-center justify-between gap-3 rounded-lg border ui-border ui-surface-muted-bg px-3 py-2">
                                <div class="min-w-0">
                                    <p class="ui-title text-sm font-bold truncate">{{ $product->name }}</p>
                                    <p class="ui-text-muted ui-text-caption">تم التوريد: {{ optional($product->created_at)->format('Y-m-d') }}</p>
                                </div>
                                <div class="text-left shrink-0">
                                    <p class="ui-status-success text-sm font-bold">{{ number_format((float) $product->price, 2) }} ر.س</p>
                                    <p class="ui-text-muted ui-text-caption">الكمية: {{ \App\Support\ProductQuantityFormatter::currentStock($product, $product->is_splittable && $product->quick_sale_default_unit === 'piece' ? 'piece' : 'unit') }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg border ui-border ui-surface-muted-bg p-4 text-center ui-text-muted text-sm">لا توجد منتجات مضافة بعد</div>
                        @endforelse
                    </div>
                </div>

                <div class="ui-card p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h4 class="ui-title font-bold text-sm flex items-center gap-2">
                                <i class="fa-solid fa-triangle-exclamation ui-status-warning"></i>
                                منتجات منخفضة المخزون
                            </h4>
                            <x-ui.help variant="warning" title="المنتجات منخفضة المخزون" body="منتجات وصلت أو نزلت عن الحد الأدنى" />
                        </div>

                    </div>
                    <div class="space-y-2">
                        @forelse($lowStockProductsList as $product)
                            <div class="flex items-center justify-between gap-3 rounded-lg border ui-border ui-status-warning-bg px-3 py-2">
                                <div class="min-w-0">
                                    <p class="ui-title text-sm font-bold truncate">{{ $product->name }}</p>
                                    <p class="ui-text-muted ui-text-caption">المخزون: {{ \App\Support\ProductQuantityFormatter::currentStock($product, $product->is_splittable && $product->quick_sale_default_unit === 'piece' ? 'piece' : 'unit') }}</p>
                                </div>
                                <span class="rounded-full ui-status-warning-bg border ui-border px-3 py-1 ui-status-warning ui-text-caption font-bold shrink-0">
                                    الحد الأدنى: {{ \App\Support\ProductQuantityFormatter::minimumStock($product, $product->is_splittable && $product->quick_sale_default_unit === 'piece' ? 'piece' : 'unit') }}
                                </span>
                            </div>
                        @empty
                            <div class="rounded-lg border ui-border ui-status-success-bg p-4 text-center ui-status-success text-sm">
                                <i class="fa-solid fa-circle-check ml-1"></i>
                                لا توجد منتجات منخفضة المخزون حالياً
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </details>

        <details class="ui-card   border ui-border rounded-xl group">
            <summary class="list-none cursor-pointer p-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-calendar-day ui-status-info"></i>
                    <h3 class="ui-title font-bold text-sm">مبيعات اليوم</h3>
                </div>
                <i class="fa-solid fa-chevron-down ui-text-muted transition-transform group-open:rotate-180"></i>
            </summary>
            <div class="px-4 pb-4">
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                    @foreach($todayFinanceStats as $stat)
                    <div class="ui-card p-3">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-8 h-8 ui-status-info-bg rounded-lg flex items-center justify-center">
                                <i class="fa-solid {{ $stat['icon'] }} ui-status-info text-sm"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 mb-1"><p class="ui-text-muted ui-text-caption">{{ $stat['title'] }}</p>@if($stat['title'] === 'صافي الرواتب')<x-ui.help title="صافي الرواتب" body="صافي الرواتب بعد خصم السحوبات" />@endif</div>
                        <p class="ui-status-info font-bold text-lg">{{ $stat['value'] }}
                            @if(!empty($stat['unit']))
                            <span class="ui-text-muted ui-text-caption mr-1">{{ $stat['unit'] }}</span>
                            @endif
                        </p>
                        @if(!empty($stat['desc']))<p class="ui-text-muted ui-text-caption">{{ $stat['desc'] }}</p>@endif
                    </div>
                    @endforeach
                </div>
            </div>
        </details>

        <details class="ui-card   border ui-border rounded-xl group">
            <summary class="list-none cursor-pointer p-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-calendar ui-status-success"></i>
                    <h3 class="ui-title font-bold text-sm">ملخص الشهر</h3>
                </div>
                <i class="fa-solid fa-chevron-down ui-text-muted transition-transform group-open:rotate-180"></i>
            </summary>
            <div class="px-4 pb-4">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    @foreach($monthFinanceStats as $stat)
                    <div class="ui-card p-3">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-8 h-8 ui-status-info-bg rounded-lg flex items-center justify-center">
                                <i class="fa-solid {{ $stat['icon'] }} ui-status-info text-sm"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 mb-1"><p class="ui-text-muted ui-text-caption">{{ $stat['title'] }}</p>@if($stat['title'] === 'صافي الرواتب')<x-ui.help title="صافي الرواتب" body="صافي الرواتب بعد خصم السحوبات" />@endif</div>
                        <p class="ui-status-info font-bold text-lg">{{ $stat['value'] }}
                            @if(!empty($stat['unit']))
                            <span class="ui-text-muted ui-text-caption mr-1">{{ $stat['unit'] }}</span>
                            @endif
                        </p>
                        @if(!empty($stat['desc']))<p class="ui-text-muted ui-text-caption">{{ $stat['desc'] }}</p>@endif
                    </div>
                    @endforeach
                </div>
                <div class="mt-3">
                    <x-ui.help variant="warning" title="طريقة حساب صافي النتيجة" body="صافي النتيجة = المحصل الشهري - (المصروفات + استهلاك المحاسب + تكلفة المنتجات المباعة + المشتريات التشغيلية + صافي الرواتب بعد خصم سحب العمال). وإذا كانت أقل من الصفر فهي تمثل إجمالي الخسارة." />
                </div>
            </div>
        </details>

        <details class="ui-card   border ui-border rounded-xl group">
            <summary class="list-none cursor-pointer p-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-users ui-status-warning"></i>
                    <h3 class="ui-title font-bold text-sm">مستحقات الموظفين</h3>
                </div>
                <i class="fa-solid fa-chevron-down ui-text-muted transition-transform group-open:rotate-180"></i>
            </summary>
            <div class="px-4 pb-4">
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                    @foreach($employeePayrollStats as $stat)
                    <div class="ui-card p-3">
                        <div class="flex items-center justify-between mb-2">
                            <div class="w-8 h-8 ui-status-info-bg rounded-lg flex items-center justify-center">
                                <i class="fa-solid {{ $stat['icon'] }} ui-status-info text-sm"></i>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 mb-1"><p class="ui-text-muted ui-text-caption">{{ $stat['title'] }}</p>@if($stat['title'] === 'صافي الرواتب')<x-ui.help title="صافي الرواتب" body="صافي الرواتب بعد خصم السحوبات" />@endif</div>
                        <p class="ui-status-info font-bold text-lg">{{ $stat['value'] }}
                            @if(!empty($stat['unit']))
                            <span class="ui-text-muted ui-text-caption mr-1">{{ $stat['unit'] }}</span>
                            @endif
                        </p>
                        @if(!empty($stat['desc']))<p class="ui-text-muted ui-text-caption">{{ $stat['desc'] }}</p>@endif
                    </div>
                    @endforeach
                </div>
            </div>
        </details>
    </div>

    {{-- ===== الرسوم البيانية والإحصائيات ===== --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
        {{-- مبيعات آخر 7 أيام --}}
        <div class="ui-card   border ui-border rounded-xl p-4">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="ui-title font-bold text-base">مبيعات آخر 7 أيام</h3>
                    <p class="ui-text-muted ui-text-caption">إجمالي المبيعات اليومية</p>
                </div>
                @if(array_sum($chartData) > 0)
                <div class="ui-surface-muted-bg px-2 py-1 rounded ui-text-caption">
                    <span class="ui-text-muted">الإجمالي: </span>
                    <span class="ui-title font-bold">{{ number_format(array_sum($chartData), 2) }} ر.س</span>
                </div>
                @endif
            </div>

            <div class="h-56">
                <div id="salesChartContainer" class="h-full">
                    @if(array_sum($chartData) > 0)
                    <div class="h-full flex items-center justify-center">
                        <div class="w-12 h-12 border-4 ui-border border-t-blue-500 rounded-full animate-spin"></div>
                    </div>
                    @else
                    <div class="h-full flex flex-col items-center justify-center">
                        <div class="w-12 h-12 ui-surface-muted-bg rounded-xl flex items-center justify-center mb-2">
                            <i class="fa-solid fa-chart-line ui-text-muted text-lg"></i>
                        </div>
                        <p class="ui-text-muted text-sm">لا توجد بيانات مبيعات</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- أفضل المنتجات مبيعاً --}}
        <div class="ui-card   border ui-border rounded-xl p-4">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="ui-title font-bold text-base">أفضل المنتجات (بالكمية)</h3>
                    <p class="ui-text-muted ui-text-caption">في آخر 30 يوم</p>
                </div>
                @if($topProducts->count() > 0)
                <div class="ui-surface-muted-bg px-2 py-1 rounded ui-text-caption">
                    <span class="ui-text-muted">المجموع: </span>
                    <span class="ui-title font-bold">{{ $topProducts->sum('total_sold') }} وحدة</span>
                </div>
                @endif
            </div>

            <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                @if($topProducts->count() > 0)
                    @foreach($topProducts as $index => $product)
                    <div class="flex items-center justify-between p-2 ui-surface-muted-bg rounded-lg border ui-border">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 flex items-center justify-center ui-status-info-bg ui-status-info rounded ui-text-caption font-bold">{{ $index + 1 }}</span>
                            <span class="ui-title ui-text-caption truncate max-w-[120px]">{{ $product->name }}</span>
                        </div>
                        <span class="ui-status-success font-bold text-sm">{{ number_format((float) ($product->total_sold ?? 0), 0) }} وحدة</span>
                    </div>
                    @endforeach
                @else
                <div class="h-40 flex flex-col items-center justify-center">
                    <div class="w-12 h-12 ui-surface-muted-bg rounded-xl flex items-center justify-center mb-2">
                        <i class="fa-solid fa-box ui-text-muted text-lg"></i>
                    </div>
                    <p class="ui-text-muted text-sm">لا توجد مبيعات</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===== آخر العمليات (مصغرة) ===== --}}
    <div class="ui-card   border ui-border rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b ui-border ui-surface-muted-bg">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-1 h-6 ui-status-info-bg rounded-full"></div>
                    <h3 class="ui-title font-bold text-sm">آخر العمليات</h3>
                </div>
                @if($operations->count() > 0)
                <span class="ui-text-muted ui-text-caption">{{ $operations->count() }} عملية</span>
                @endif
            </div>
        </div>

        <div class="divide-y divide-ui-border max-h-80 overflow-y-auto">
            @forelse($operations as $op)
            @php
                $actionColors = [
                    'create' => 'ui-status-success',
                    'update' => 'ui-status-info',
                    'delete' => 'ui-status-danger',
                    'sale' => 'ui-status-success',
                ];
                $color = $actionColors[$op->action] ?? 'ui-text-muted';
            @endphp
            <div class="ui-frame-row px-4 py-3 text-sm">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="{{ $color }} ui-text-caption font-bold">{{ $op->action_label ?? 'عملية' }}</span>
                        <span class="ui-text-muted ui-text-caption">{{ $op->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                    <span class="ui-text-soft ui-text-caption leading-6 sm:max-w-2xl">{{ $op->description ?? '' }}</span>
                </div>
            </div>
            @empty
            <div class="p-6 text-center">
                <p class="ui-text-muted text-sm">لا توجد عمليات حديثة</p>
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- JavaScript للرسوم البيانية --}}
{{-- عقد بيانات الرسم فقط؛ القيم المالية محسوبة في الخادم ولا يعاد حسابها داخل الواجهة. --}}
<div class="hidden"
     data-store-sales-chart-config="{{ json_encode([
         'chartData' => $chartData,
         'chartLabels' => $chartLabels,
         'profitData' => $profitData,
     ], JSON_HEX_APOS | JSON_HEX_QUOT) }}"
     aria-hidden="true"></div>

@endsection
