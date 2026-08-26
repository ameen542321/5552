@extends('dashboard.app')

@section('title', 'لوحة التحكم')

@section('content')
@php
    /*
     * قيم توافق آمنة لنسخة واجهة لوحة المالك الموسعة.
     * تبقى الواجهة قابلة للعمل مع الكنترولر الأصلي، وتعرض القيم الفارغة فقط
     * للبيانات الإضافية التي لا يرسلها الكنترولر بدل ظهور Undefined variable.
     */
    $dailySalesOperationsCount = $dailySalesOperationsCount ?? 0;
    $dashboardDate = $dashboardDate ?? today();
    $storesWithoutBalance = $storesWithoutBalance ?? collect();
    $lowStockCount = $lowStockCount ?? 0;
    $lowStockProducts = $lowStockProducts ?? collect();
    $topSellingProducts = $topSellingProducts ?? collect();
    $topSellingProductGroups = collect($topSellingProducts)->groupBy('store_id');
    $employeeSalaryRemainders = $employeeSalaryRemainders ?? [];
    $employeeSalaryThresholdAlerts = $employeeSalaryThresholdAlerts ?? collect();
    // موظفون بلا راتب: يستخدمان في التنبيه والقائمة التفصيلية أدناه.
    $employeesWithoutSalary = $employeesWithoutSalary ?? collect();
    $employeesWithoutSalaryCount = $employeesWithoutSalaryCount ?? $employeesWithoutSalary->count();
    $suspendedEmployeeAlerts = $suspendedEmployeeAlerts ?? collect();
    $pendingStoreTransfersCount = $pendingStoreTransfersCount ?? 0;
    $missingShiftAlerts = $missingShiftAlerts ?? collect();
    $firstStoreForTransfers = $stores->first();
    $pendingPurchaseOrderAlerts = $pendingPurchaseOrderAlerts ?? collect();
@endphp
<div class="p-4 sm:p-6 space-y-6">

    {{-- ========================================================= --}}
    {{--  القسم الأول: الهيدر الاحترافي --}}
    {{-- ========================================================= --}}
    <section class="ui-card p-4 sm:p-5" x-data="{ balanceWarningOpen: false }">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-bold ui-title">مرحباً، {{ $user->name }}</h1>
                <p class="ui-text-soft text-sm mt-1">نظرة عامة ذكية على أداء متاجرك.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 ui-text-caption">
                <x-purchase-order-alerts-button :alerts="$pendingPurchaseOrderAlerts" context="owner" />
                <span class="ui-badge ui-badge-info"><i class="fa-solid fa-layer-group" aria-hidden="true"></i>خطة {{ $user->plan->name ?? 'بدون خطة' }}</span>
                @if(isset($daysLeft))
                    @php $days = (int) $daysLeft; @endphp
                    <span class="ui-badge {{ $days > 3 ? 'ui-badge-success' : ($days >= 0 ? 'ui-badge-warning' : 'ui-badge-danger') }}">
                        {{ $days > 0 ? 'متبقي '.$days.' يوم' : ($days === 0 ? 'ينتهي اليوم' : 'منتهي منذ '.abs($days).' يوم') }}
                    </span>
                @endif
                <a href="{{ route('user.administrative-alerts') }}" class="ui-badge {{ ($administrativeAlertsCount ?? 0) > 0 ? 'ui-badge-warning' : 'ui-badge-info' }}"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>تنبيهات إدارية@if(($administrativeAlertsCount ?? 0) > 0) {{ $administrativeAlertsCount }}@endif</a>
            </div>
        </div>

        <div class="ui-date-filter mt-4 flex flex-wrap items-center gap-3">
            <form method="GET" action="{{ route('user.dashboard') }}" class="flex flex-wrap items-center gap-2">
                <label for="dashboard-date" class="ui-text-caption font-bold ui-title flex items-center gap-2">
                    عرض بيانات يوم
                    <x-ui.help title="عرض بيانات يوم" body="اختر تاريخًا سابقًا لعرض بيانات ذلك اليوم. للعودة إلى بيانات اليوم الحالي اضغط إلغاء عرض التاريخ." />
                </label>
                <input id="dashboard-date"
                       type="date"
                       name="date"
                       value="{{ $dashboardDate->toDateString() }}"
                       max="{{ today()->toDateString() }}"
                       class="ui-date-input"
                       data-ui-submit-on-change>
                {{-- إصلاح مطبق: تغيير تاريخ لوحة المالك يرسل النموذج عبر ui-actions بدل onchange المضمّن. --}}
            </form>

            @if($dashboardDate->toDateString() !== today()->toDateString())
                <a href="{{ route('user.dashboard') }}" class="ui-btn ui-btn-secondary ui-btn-small">إلغاء</a>
            @endif

            @if($dashboardDate->toDateString() !== today()->toDateString() && $storesWithoutBalance->isNotEmpty())
                <button type="button"
                        class="ui-topbar-action ui-warning-icon-action group"
                        aria-label="تنبيه بيانات اليوم المحدد"
                        :aria-expanded="balanceWarningOpen"
                        @click.prevent.stop="balanceWarningOpen = true">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <span class="ui-tooltip-popover">بيانات غير مكتملة</span>
                </button>

                    <div x-show="balanceWarningOpen" x-cloak x-transition.opacity class="ui-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="owner-balance-warning-title">
                        <div class="ui-modal-panel ui-modal-panel-transfer text-right" @click.outside="balanceWarningOpen = false">
                            <div class="ui-modal-header">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-triangle-exclamation ui-status-warning" aria-hidden="true"></i>
                                    <strong id="owner-balance-warning-title" class="ui-title text-lg">بيانات اليوم غير مكتملة</strong>
                                    <x-ui.help title="سبب التنبيه" body="تعتمد دقة ملخص اليوم على صدور موازنة كل متجر. يمكنك متابعة البيانات، لكن بعض الأرقام قد لا تكون مكتملة بعد." />
                                </div>
                                <button type="button" class="ui-modal-close-text-danger" @click="balanceWarningOpen = false">إغلاق</button>
                            </div>
                            <div class="p-5 space-y-4">
                                <div class="ui-card-muted p-3">
                                    <span class="ui-text-soft text-sm">التاريخ المحدد</span>
                                    <strong class="block ui-title mt-1">{{ $dashboardDate->toDateString() }}</strong>
                                </div>
                                <div>
                                    <strong class="ui-title">متاجر لم تصدر موازنتها</strong>
                                    <ul class="mt-2 space-y-2">
                                        @foreach($storesWithoutBalance as $storeWithoutBalance)
                                            <li class="ui-card-muted p-3 ui-text-soft">{{ $storeWithoutBalance->name }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
            @endif
        </div>
    </section>

    {{--  القسم الرابع: الإحصائيات العامة (دمج بين الداشبوردين) --}}
    {{-- ========================================================= --}}
    <div class="flex flex-wrap items-center justify-between gap-2 mt-1 mb-2">
        <p class="ui-text-caption font-semibold ui-text-soft">ملخص يوم {{ $dashboardDate->toDateString() }}</p>
        <div class="inline-flex items-center gap-2 ui-text-caption ui-text-soft">
            <span id="live-status-dot" class="w-2 h-2 rounded-full ui-dot-success animate-pulse"></span>
            <span>العمليات:</span>
            <strong id="live-operations-count" class="ui-status-info">{{ number_format($dailySalesOperationsCount) }}</strong>
            <span id="live-updated-at" class="ui-text-muted">تحديث مباشر</span>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">

        {{-- صافي الربح اليوم --}}
        <button id="daily-profit-card" type="button" class="text-right metric-card" data-metric="profit_today" aria-label="للمزيد من التفاصيل اضغط: صافي الربح في التاريخ المحدد حسب كل متجر">
            <x-stat-card title="صافي الربح"
                value="{{ number_format($profitToday) }}"
                value-id="daily-profit-value"
                color="{{ $profitToday >= 0 ? 'emerald' : 'red' }}" />
        </button>

        {{-- [تعديل آمن] مبيعات اليوم محسوبة من المحصّل الفعلي --}}
        <button id="daily-sales-card" type="button" class="text-right metric-card" data-metric="sales_today" aria-label="للمزيد من التفاصيل اضغط: مبيعات التاريخ المحدد حسب كل متجر">
            <x-stat-card title="المبيعات" value="{{ number_format($salesToday) }}" value-id="daily-sales-value" color="emerald" />
        </button>

        {{-- مصروفات اليوم --}}
        <button id="daily-expenses-card" type="button" class="text-right metric-card" data-metric="expenses_today" aria-label="للمزيد من التفاصيل اضغط: مصروفات التاريخ المحدد حسب كل متجر">
            <x-stat-card title="المصروفات" value="{{ number_format($expensesToday) }}" value-id="daily-expenses-value" color="red" />
        </button>

        <button id="daily-products-cost-card" type="button" class="text-right metric-card" data-metric="products_cost_today" aria-label="للمزيد من التفاصيل اضغط: تكلفة المنتجات المباعة في التاريخ المحدد حسب كل متجر">
            <x-stat-card title="تكلفة المنتجات المباعة" value="{{ number_format($productsCostToday, 2) }}" value-id="daily-products-cost-value" color="yellow" />
        </button>

        <div id="live-operation-card" class="ui-card relative overflow-hidden px-5 py-7 transition-colors duration-500 md:col-span-2 xl:col-span-4">
            <span id="live-operation-amount" class="absolute left-3 top-2 ui-text-caption font-bold ui-status-success">0.00</span>
            <div class="flex items-center gap-4 min-h-[96px] pl-12">
                <span class="w-8 h-8 shrink-0 rounded-lg ui-status-info-bg ui-status-info flex items-center justify-center">
                    <i class="fa-solid fa-bolt ui-text-caption"></i>
                </span>
                <div class="min-w-0 text-right">
                    <p id="live-operation-product" class="text-sm font-bold ui-title break-words">{{ $dailySalesOperationsCount > 0 ? 'جاري متابعة العمليات...' : 'لا توجد عمليات بيع في التاريخ المحدد.' }}</p>
                    <p class="ui-text-caption ui-text-muted mt-1 flex flex-wrap items-center gap-1">
                        <span id="live-operation-store">—</span>
                        <span class="mx-1">•</span>
                        <span id="live-operation-time">--:--</span>
                    </p>
                </div>
            </div>
        </div>

    </div>

    {{-- الصف الثاني --}}
    <p class="ui-text-caption font-semibold ui-text-soft mt-5 mb-2">ملخص شهر {{ $dashboardDate->format('Y-m') }}</p>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">

        <button type="button" class="text-right metric-card" data-metric="profit_month" aria-label="للمزيد من التفاصيل اضغط: صافي الربح الشهري حسب كل متجر">
            <x-stat-card title="صافي الربح الشهري"
                value="{{ number_format($profitMonth) }}"
                color="{{ $profitMonth >= 0 ? 'emerald' : 'red' }}" />
        </button>
        <button type="button" class="text-right metric-card" data-metric="sales_month" aria-label="للمزيد من التفاصيل اضغط: مبيعات الشهر حسب كل متجر">
            <x-stat-card title="مبيعات الشهر" value="{{ number_format($salesMonth) }}" color="emerald" />
        </button>
        <button type="button" class="text-right metric-card" data-metric="expenses_month" aria-label="للمزيد من التفاصيل اضغط: مصروفات الشهر حسب كل متجر">
            <x-stat-card title="مصروفات الشهر" value="{{ number_format($expensesMonth) }}" color="red" />
        </button>
        <button type="button" class="text-right metric-card" data-metric="salaries_month" aria-label="للمزيد من التفاصيل اضغط: الرواتب الشهرية حسب كل متجر">
            <x-stat-card title="الرواتب الشهرية" value="{{ number_format($monthlySalaries ?? 0) }}" color="indigo" />
        </button>
        <button id="salary-after-withdrawals-card" type="button" class="text-right" aria-label="عرض المتبقي من الرواتب بعد السحوبات وخصم الغياب حسب المتجر والموظف">
            <x-stat-card title="الرواتب بعد السحب والغياب" value="{{ number_format($netMonthlySalaries ?? 0) }}" color="blue" />
        </button>
    </div>

    <p class="ui-text-caption font-semibold ui-text-soft mt-5 mb-2">التشغيل والمشتريات</p>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <x-stat-card title="عدد المتاجر" value="{{ $stores->count() }}" color="indigo" />
        <x-stat-card title="عدد الموظفين" value="{{ $employeesCount }}" color="yellow" />

        <x-stat-card title="مشتريات المالك (شهري)"
            value="{{ number_format($monthlyOwnerPurchases ?? 0, 2) }} ر.س"
            color="blue" />

        <x-stat-card title="استهلاك داخلي (المحاسب)"
            value="{{ number_format($monthlyAccountantConsumption ?? 0, 2) }} ر.س"
            color="yellow" />
    </div>

    <div class="grid grid-cols-1 gap-4 mt-4">
        <button type="button" class="text-right metric-card" data-metric="monthly_purchases_consumption" aria-label="للمزيد من التفاصيل اضغط: المشتريات حسب كل متجر">
            <x-stat-card title="المشتريات (شهري)"
                value="{{ number_format($monthlyPurchasesAndConsumption, 2) }} ر.س"
                color="purple" />
        </button>
    </div>

    {{-- ========================================================= --}}
    {{--  القسم الخامس: تحليل المديونيات --}}
    {{-- ========================================================= --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-stat-card title="مديونيات مفتوحة" value="{{ $creditOpen }}" color="yellow" />
        <x-stat-card title="مديونيات مسددة" value="{{ $creditClosed }}" color="emerald" />
        <x-stat-card title="مديونيات متأخرة" value="{{ $creditLate }}" color="red" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="ui-card p-5">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-semibold ui-title">المنتجات منخفضة المخزون</p>
                <span class="ui-text-caption ui-status-warning">{{ number_format($lowStockCount) }} منتج</span>
            </div>
            <div class="max-h-72 overflow-y-auto custom-scrollbar space-y-2 pr-1">
                @forelse($lowStockProducts as $product)
                    <div class="flex items-center justify-between gap-3 ui-border-bottom pb-2">
                        <div>
                            <p class="text-sm ui-text-soft">{{ $product->name }}</p>
                            <p class="ui-text-caption ui-text-muted">{{ $product->store->name ?? 'متجر غير معروف' }}</p>
                        </div>
                        @php
                            $dashboardDisplayUnit = $product->is_splittable && $product->quick_sale_default_unit === 'piece' ? 'piece' : 'unit';
                        @endphp
                        <span class="ui-text-caption font-bold ui-status-warning">
                            {{ \App\Support\ProductQuantityFormatter::currentStock($product, $dashboardDisplayUnit) }}
                            <span class="ui-text-muted">— الحد الأدنى: {{ \App\Support\ProductQuantityFormatter::minimumStock($product, $dashboardDisplayUnit) }}</span>
                        </span>
                    </div>
                @empty
                    <p class="ui-text-caption ui-text-muted">لا توجد منتجات منخفضة المخزون.</p>
                @endforelse
            </div>
        </div>

        <div class="ui-card p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm font-semibold ui-title">الأكثر مبيعًا حسب المتجر</p>
                    <p class="ui-text-caption ui-text-muted mt-1">أفضل 5 منتجات خلال {{ $dashboardDate->format('Y-m') }}</p>
                </div>
                <i class="fa-solid fa-ranking-star ui-status-warning"></i>
            </div>
            <div class="max-h-72 overflow-y-auto custom-scrollbar space-y-5 pr-1">
                @if($topSellingProductGroups->isEmpty())
                    <p class="ui-text-caption ui-text-muted">لا توجد مبيعات منتجات خلال الشهر المحدد.</p>
                @else
                @foreach($topSellingProductGroups as $productsForStore)
                    @php
                        $highestQuantity = max(1, (float) $productsForStore->max('sold_quantity'));
                    @endphp
                    <div class="ui-card-muted p-3">
                        <div class="flex items-center justify-between mb-3">
                            <p class="ui-text-caption font-bold ui-status-success">{{ $productsForStore->first()?->store_name ?? 'متجر غير معروف' }}</p>
                            <span class="ui-text-caption ui-text-muted">{{ $productsForStore->count() }} منتجات</span>
                        </div>
                        <div class="space-y-3">
                            @foreach($productsForStore as $index => $product)
                                <div>
                                    <div class="flex items-center justify-between gap-3 ui-text-caption mb-1.5">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="w-5 h-5 shrink-0 rounded-full ui-surface-strong-bg ui-text-soft flex items-center justify-center ui-text-caption">{{ $index + 1 }}</span>
                                            <span class="ui-text-soft truncate">{{ $product->name }}</span>
                                        </div>
                                        <div class="text-left shrink-0">
                                            <span class="ui-status-info font-bold">{{ number_format((float) $product->sold_quantity, 2) }}</span>
                                            <span class="ui-text-caption ui-text-muted mr-1">{{ number_format((float) $product->sales_value, 2) }}</span>
                                        </div>
                                    </div>
                                    <progress class="ui-progress-bar" value="{{ min(100, ((float) $product->sold_quantity / $highestQuantity) * 100) }}" max="100" aria-label="نسبة مبيعات المنتج"></progress>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @endif
            </div>
        </div>
    </div>

    {{-- ========================================================= --}}
    {{--  القسم السادس: المخطط الذكي --}}
    {{-- ========================================================= --}}
    <div class="ui-card p-5">
        <p class="text-sm font-semibold ui-title mb-1">أداء 14 يومًا حتى {{ $dashboardDate->toDateString() }}</p>
        <p class="ui-text-caption ui-text-soft mb-3">
            {{-- [تعديل آمن] القيم في هذا المخطط تعرض اتجاه الأداء اليومي للفواتير والمصروفات والآجل لتسهيل القراءة السريعة. --}}
        </p>

        <div class="flex flex-wrap gap-4 mb-3 ui-text-caption">
            <span class="inline-flex items-center gap-2 ui-status-success"><span class="w-2.5 h-2.5 rounded-full ui-dot-success"></span>مبيعات</span>
            <span class="inline-flex items-center gap-2 ui-status-danger"><span class="w-2.5 h-2.5 rounded-full ui-dot-danger"></span>مصروفات</span>
            <span class="inline-flex items-center gap-2 ui-status-info"><span class="w-2.5 h-2.5 rounded-full ui-dot-info"></span>الديون المتبقية</span>
        </div>

        <canvas id="smartChart" class="w-full h-64"></canvas>
    </div>

    {{-- ========================================================= --}}
    {{--  القسم السابع: آخر العمليات --}}
    {{-- ========================================================= --}}
    <div class="ui-card p-5">
    <p class="text-sm font-semibold ui-title mb-3">آخر العمليات</p>

    <div class="space-y-4 max-h-72 overflow-y-auto custom-scrollbar">

        @forelse ($activities as $activity)
            @php
                $store = $activity->store;
                $employeeName = null;
                // تاريخ العملية المسجل هو الأساس، وتاريخ إدخال السجل بديل عند عدم توفره.
                $activityDate = data_get($activity->details, 'business_date')
                    ?? data_get($activity->details, 'operation_date')
                    ?? $activity->created_at;

                // استخراج اسم الموظف من الوصف إذا كان موجودًا
                if (preg_match('/الْمُوَظَّف\s+([^\s]+)/u', $activity->description, $matches)) {
                    $employeeName = $matches[1];
                }
            @endphp

            <div class="ui-border-bottom pb-3 last:border-none">

                {{-- اسم المتجر --}}
                <p class="ui-text-caption ui-status-success font-semibold">
                    {{ $store->name ?? 'متجر غير معروف' }}
                </p>

                {{-- اسم الموظف إن وجد --}}
                @if($employeeName)
                    <p class="ui-text-caption ui-text-soft">
                        الموظف: {{ $employeeName }}
                    </p>
                @endif

                {{-- وصف العملية --}}
                <p class="ui-text-caption ui-text-soft mt-1 leading-relaxed">
                    {{ $activity->description }}
                </p>

                {{-- الوقت --}}
                <p class="ui-text-caption ui-text-muted mt-1">
                    {{ \Carbon\Carbon::parse($activityDate)->format('Y-m-d') }}
                </p>
            </div>

        @empty
            <p class="ui-text-caption ui-text-muted">لا توجد عمليات مسجلة.</p>
        @endforelse

    </div>
</div>

{{-- نافذة تفاصيل البطاقات --}}
<div id="metric-modal" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="metric-modal-title">
    <div class="ui-modal-panel ui-modal-panel-transfer">
        <div class="ui-modal-header">
            <div class="flex items-center gap-2">
                <h3 id="metric-modal-title" class="ui-title font-bold text-lg"></h3>
                <span id="metric-modal-salary-help" class="hidden">
                    <x-ui.help title="الرواتب الشهرية" body="إجمالي الرواتب المستحقة حسب أيام العمل الفعلية دون خصم السحوبات." />
                </span>
            </div>
            <button type="button" id="metric-modal-close" class="ui-modal-close-text-danger">إغلاق</button>
        </div>
        <div class="p-5">
            <p id="metric-modal-value" class="text-2xl font-black ui-status-success mb-2"></p>
            <p id="metric-modal-details" class="text-sm ui-text-soft leading-7"></p>
        </div>
    </div>
</div>


{{-- نافذة الرواتب بعد السحب والغياب: المتاجر أولاً، ثم موظفو المتجر عند الضغط عليه. --}}
<div id="salary-withdrawals-modal" class="ui-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="salary-withdrawals-title">
    <div class="ui-modal-panel ui-modal-panel-wide ui-modal-panel-transfer">
        <div class="ui-modal-header">
            <div class="flex items-center gap-2">
                <h3 id="salary-withdrawals-title" class="ui-title font-bold text-lg">تفاصيل الراتب</h3>
                <x-ui.help variant="warning" title="تفاصيل الراتب" body="اضغط اسم المتجر لعرض الموظفين وإجمالي السحب وخصم الغياب اليومي والمتبقي من الراتب." />
            </div>
            <button type="button" id="salary-withdrawals-close" class="ui-modal-close-text-danger">إغلاق</button>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-3 ui-border-bottom ui-input-bg">
            <div class="rounded-xl ui-border p-3">
                <p class="ui-text-caption ui-text-muted">إجمالي الرواتب</p>
                <p class="ui-title font-bold mt-1">{{ number_format($monthlySalaries ?? 0, 2) }}</p>
            </div>
            <div class="rounded-xl ui-border p-3">
                <p class="ui-text-caption ui-text-muted">إجمالي السحوبات</p>
                <p class="ui-status-danger font-bold mt-1">{{ number_format($monthlyWorkerWithdrawals ?? 0, 2) }}</p>
            </div>
            <div class="rounded-xl ui-border p-3">
                <p class="ui-text-caption ui-text-muted">المتبقي من الرواتب</p>
                <p class="ui-title font-bold mt-1">{{ number_format($netMonthlySalaries ?? 0, 2) }}</p>
            </div>
        </div>
        <div id="salary-withdrawals-stores" class="p-5 space-y-3"></div>
    </div>
</div>

</div>




{{-- ========================================================= --}}
{{--  سكربت المخطط --}}
{{-- ========================================================= --}}








{{-- عقد إعداد لوحة المالك؛ جميع القيم المالية محسوبة في الخادم ولا يعاد حساب مصادرها هنا. --}}
<div class="hidden"
     data-owner-dashboard-config="{{ json_encode([
         'chartLabels' => $chartLabels,
         'chartSales' => $chartSales,
         'chartExpenses' => $chartExpenses,
         'chartCredit' => $chartCredit,
         'storeBreakdowns' => $metricStoreBreakdowns ?? [],
         'metricDefinitions' => [
             'profit_today' => ['title' => 'صافي الربح — ' . $dashboardDate->toDateString(), 'value' => number_format($profitToday, 2) . ' ر.س'],
             'sales_today' => ['title' => 'المبيعات — ' . $dashboardDate->toDateString(), 'value' => number_format($salesToday, 2) . ' ر.س'],
             'expenses_today' => ['title' => 'المصروفات — ' . $dashboardDate->toDateString(), 'value' => number_format($expensesToday, 2) . ' ر.س'],
             'products_cost_today' => ['title' => 'تكلفة المنتجات المباعة — ' . $dashboardDate->toDateString(), 'value' => number_format($productsCostToday, 2) . ' ر.س'],
             'profit_month' => ['title' => 'صافي الربح الشهري', 'value' => number_format($profitMonth, 2) . ' ر.س'],
             'sales_month' => ['title' => 'مبيعات الشهر', 'value' => number_format($salesMonth, 2) . ' ر.س'],
             'expenses_month' => ['title' => 'مصروفات الشهر', 'value' => number_format($expensesMonth, 2) . ' ر.س'],
             'salaries_month' => ['title' => 'الرواتب الشهرية', 'value' => number_format($monthlySalaries ?? 0, 2) . ' ر.س'],
             'monthly_purchases_consumption' => ['title' => 'المشتريات (شهري)', 'value' => number_format($monthlyPurchasesAndConsumption, 2) . ' ر.س'],
         ],
         'salaryRows' => $employeeSalaryRemainders ?? [],
         'snapshotUrl' => route('user.dashboard.daily-snapshot'),
         'dashboardDate' => $dashboardDate->toDateString(),
     ], JSON_HEX_APOS | JSON_HEX_QUOT) }}"
     aria-hidden="true"></div>
@endsection
