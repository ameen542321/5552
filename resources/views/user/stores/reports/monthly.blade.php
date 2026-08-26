@extends('dashboard.app')

@section('title', 'التقرير الشهري - ' . $store->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 text-right" dir="rtl">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0 text-center sm:text-right">
            <h1 class="text-2xl font-bold ui-title">التقرير الشهري للمتجر</h1>
            <p class="mt-1 break-words text-sm ui-text-muted">{{ $store->name }}</p>
        </div>
        <a href="{{ route('user.stores.reports.index', $store->id) }}" class="ui-btn ui-btn-secondary ui-title inline-flex w-full items-center justify-center rounded-lg px-4 py-2 text-sm sm:w-auto">
            العودة لمركز التقارير
        </a>
    </div>

    <form method="GET" class="mb-4 grid grid-cols-1 gap-3 sm:flex sm:items-end">
        <div class="w-full sm:w-auto">
            <label class="block ui-text-caption ui-text-muted mb-1">الشهر</label>
            <input type="month" name="month" value="{{ $month }}" class="ui-surface-muted-bg ui-border w-full rounded-lg px-3 py-2 ui-title text-sm sm:w-auto">
        </div>
        <button class="ui-btn ui-btn-primary ui-title w-full rounded-lg px-4 py-2 text-sm sm:w-auto">عرض</button>
    </form>

    <div class="mb-4 ui-surface-strong-bg border ui-border rounded-2xl p-4">
        <p class="ui-title font-semibold text-base mb-4">إصدار تقرير PDF</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="rounded-xl border ui-border ui-status-success-bg p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="inline-flex items-center gap-2 ui-status-success font-bold">تقرير مختصر
                        <x-ui.help title="التقرير المختصر" body="يشمل الملخص المالي، المؤشرات، المصروفات، وصافي النتيجة بدون جدول تفاصيل المبيعات." />
                    </span>
                    <span class="ui-text-caption ui-status-success-bg px-2 py-1 rounded-full">الأسرع</span>
                </div>
                <a href="{{ route('user.stores.reports.monthly.pdf', ['store' => $store->id, 'month' => $month]) }}" class="ui-btn ui-btn-primary mt-3">تحميل المختصر</a>
            </div>
            <div class="rounded-xl border ui-border ui-status-info-bg p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="inline-flex items-center gap-2 ui-status-info font-bold">تقرير تفصيلي
                        <x-ui.help title="التقرير التفصيلي" body="يشمل كل بيانات التقرير المختصر مع تفاصيل الأيام واستهلاك المحاسب ومشتريات المالك والمصروفات والعمال." />
                    </span>
                    <span class="ui-text-caption ui-status-info-bg px-2 py-1 rounded-full">مع التفاصيل</span>
                </div>
                <a href="{{ route('user.stores.reports.monthly.pdf', ['store' => $store->id, 'month' => $month, 'include_sales_details' => 1]) }}" class="ui-btn ui-btn-info mt-3">تحميل التفصيلي</a>
            </div>
        </div>
    </div>

    @php
        $profitFormulaHint = 'ربح المبيعات المحصلة هو المبلغ الذي استلمه المتجر فعلًا من البيع ناقص تكلفة المنتجات التي يغطيها هذا المبلغ. بعد ذلك يطرح التقرير استهلاك المحاسب ومشتريات المالك والمصروفات للوصول إلى صافي النتيجة.';
        $salesTotalHint = 'إجمالي المبيعات هو المبلغ الذي قبضه المتجر من عمليات البيع خلال الشهر. يعرض التقرير كم وصل كاش وكم وصل شبكة، وإذا دفع العميل جزءًا كاش وجزءًا شبكة فكل جزء يظهر في مكانه. المعادلة العملية: الكاش المقبوض + الشبكة المقبوضة + أي مبلغ محصل غير موزع = إجمالي المبيعات.';
        $productsCostHint = 'تكلفة المنتجات المباعة تجمع تكلفة كل بند بيع: الكمية المباعة × تكلفة الوحدة المحفوظة وقت البيع. إذا كان سجل قديمًا بلا تكلفة محفوظة، يستخدم النظام تكلفة البيع القديمة المسجلة في الفاتورة. سعر البيع أو شغل اليد لا يعدان تكلفة منتج.';
        $withdrawalsHint = 'الراتب يظهر كمستحق للموظف عن الشهر، والسحوبات تظهر كدفعات استلمها الموظف مبكرًا من راتبه. لذلك تخصم السحوبات من باقي راتب الموظف داخل جدول الرواتب، ولا تخصم مرة ثانية من الربح لأنها ليست مصروفًا مستقلًا جديدًا.';
        $inventoryHint = 'تحسب القيمة من رصيد كل منتج عند أول الشهر × تكلفة الوحدة المحفوظة عند حد البداية، ومن رصيده عند آخر الشهر × تكلفة الوحدة عند حد النهاية. لا تجمع حركات الشهر، وتبقى أرصدة الحبة والطقم والمتر بوحدة المخزون الأساسية، كما تستبعد منتجات مشتريات المالك من مخزون نهاية الشهر.';
        $operatingCostsHint = 'مصروفات تشغيلية تجمع ما خرج على التشغيل خلال الشهر: استهلاك المحاسب، ومشتريات المالك، والمصروفات المسجلة. تظهر كل قيمة منفصلة في التفاصيل حتى يعرف المالك مصدر الإجمالي.';
        $approvedSupplyHint = 'طلبات التوريد المعتمدة تظهر هنا فقط بعد الاعتماد المخزني. إذا كانت الطلبية للشهر السابق لكنها اعتمدت مخزنيًا في هذا الشهر فستظهر في شهر الاعتماد، وليس شهر الإنشاء.';
        $transferHint = 'النقل المخزني يعرض الصادر والوارد المكتملين خلال الشهر ككشف حركة فقط. الصادر والوارد لا يدخلان مباشرة في الربح، لكنهما يساعدانك على مراجعة انتقال تكلفة المخزون بين المتاجر.';
        $transferSummary = $transferSummary ?? ['outgoing_count' => 0, 'incoming_count' => 0, 'outgoing_cost' => 0, 'incoming_cost' => 0, 'note' => 'النقل المخزني كشف تشغيلي للصادر والوارد فقط، ولا يدخل في معادلة الربح.'];
        $approvedSupplySummary = $approvedSupplySummary ?? ['orders_count' => 0, 'total_cost' => 0, 'owner_products_count' => 0, 'owner_products_cost' => 0, 'stock_products_count' => 0, 'stock_products_cost' => 0, 'rows' => collect()];
        $operationsCostTotal = ($internalUseSales ?? 0) + ($ownerPurchases ?? 0) + ($expensesTotal ?? 0);
        $dailyRowsCollection = collect($dailyRows ?? [])->sortByDesc('day')->values();
        $dailyTotals = [
            'ops_count' => $dailyRowsCollection->sum('ops_count'),
            'cash_total' => $dailyRowsCollection->sum('cash_total'),
            'card_total' => $dailyRowsCollection->sum('card_total'),
            'sales_total' => $dailyRowsCollection->sum('sales_total'),
            'recognized_cost' => $dailyRowsCollection->sum('recognized_cost'),
            'recognized_profit' => $dailyRowsCollection->sum('recognized_profit'),
            'deferred_profit' => $dailyRowsCollection->sum('deferred_profit'),
        ];
        $monthlyModalTitles = [
            'sales' => 'إجمالي المبيعات',
            'operatingCosts' => 'مصروفات تشغيلية',
            'payroll' => 'تفاصيل الرواتب والسحوبات',
            'transfers' => 'تفاصيل النقل المخزني',
            'approvedSupplies' => 'تفاصيل الطلبيات المعتمدة',
        ];
    @endphp

    <div x-data="{ monthlyModal: null, monthlyModalTitles: @js($monthlyModalTitles) }" class="space-y-4">
        <div class="grid grid-cols-1 items-stretch gap-3 text-sm sm:grid-cols-2 xl:grid-cols-3">
            @if(($totalSales ?? 0) > 0)
            <div class="ui-card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="ui-text-soft">إجمالي المبيعات</p>
                            <x-ui.help title="إجمالي المبيعات" :body="$salesTotalHint" />
                        </div>
                        <p class="ui-status-success font-bold text-lg mt-2">{{ number_format($totalSales, 2) }} ر.س</p>
                    </div>
                    <span class="ui-status-success text-xl">💳</span>
                </div>
                <button type="button" @click="monthlyModal = 'sales'" class="ui-btn ui-btn-secondary mt-3 w-full">عرض التفاصيل</button>
            </div>
            @endif

            @if($operationsCostTotal > 0)
            <div class="ui-card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="ui-text-soft">مصروفات تشغيلية</p>
                            <x-ui.help title="مصروفات تشغيلية" :body="$operatingCostsHint" />
                        </div>
                        <p class="ui-status-warning font-bold text-lg mt-2">{{ number_format($operationsCostTotal, 2) }} ر.س</p>
                    </div>
                    <span class="ui-status-warning text-xl">🧾</span>
                </div>
                <button type="button" @click="monthlyModal = 'operatingCosts'" class="ui-btn ui-btn-secondary mt-3 w-full">عرض التفاصيل</button>
            </div>
            @endif

            @if(($monthlySoldProductsCost ?? 0) > 0)
            <div class="ui-card p-4">
                <div class="flex items-start justify-between gap-3">
                    <p class="ui-text-soft">تكلفة المنتجات المباعة</p>
                    <x-ui.help title="طريقة احتساب تكلفة المنتجات" :body="$productsCostHint" />
                </div>
                <p class="ui-title font-bold mt-2">الإجمالي: {{ number_format($monthlySoldProductsCost ?? 0, 2) }} ر.س</p>
                @if(($profitDeductionTotal ?? $recognizedProductsCost ?? 0) > 0)
                    <p class="ui-status-danger ui-text-caption mt-1">المخصوم الآن: {{ number_format($profitDeductionTotal ?? $recognizedProductsCost ?? 0, 2) }} ر.س</p>
                @endif
                @if(($uncoveredProductsCost ?? 0) > 0)
                    <p class="ui-status-warning ui-text-caption mt-1">المؤجل مع غير المحصل: {{ number_format($uncoveredProductsCost ?? 0, 2) }} ر.س</p>
                @endif
            </div>
            @endif

            @if(($monthlySalaries ?? 0) > 0 || ($withdrawalsTotal ?? 0) > 0)
            <div class="ui-card p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <p class="ui-text-soft">الرواتب والسحوبات</p>
                        <x-ui.help title="مسار السحوبات" :body="$withdrawalsHint" />
                    </div>
                    <span class="ui-status-info text-xl">👥</span>
                </div>
                @if(($monthlySalaries ?? 0) > 0)
                    <p class="ui-status-info font-bold mt-2">رواتب: {{ number_format($monthlySalaries, 2) }} ر.س</p>
                @endif
                @if(($withdrawalsTotal ?? 0) > 0)
                    <p class="ui-status-info font-bold {{ ($monthlySalaries ?? 0) > 0 ? '' : 'mt-2' }}">سحوبات: {{ number_format($withdrawalsTotal, 2) }} ر.س</p>
                @endif
                <button type="button" @click="monthlyModal = 'payroll'" class="ui-btn ui-btn-secondary mt-3 w-full">عرض التفاصيل</button>
            </div>
            @endif

            @if(($transferSummary['outgoing_count'] ?? 0) > 0 || ($transferSummary['incoming_count'] ?? 0) > 0)
            <div class="ui-card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="ui-text-soft">النقل المخزني</p>
                            <x-ui.help title="النقل المخزني" :body="$transferHint" />
                        </div>
                        @if(($transferSummary['outgoing_cost'] ?? 0) > 0)
                            <p class="ui-status-danger font-bold mt-2">صادر: {{ number_format($transferSummary['outgoing_cost'] ?? 0, 2) }} ر.س</p>
                        @endif
                        @if(($transferSummary['incoming_cost'] ?? 0) > 0)
                            <p class="ui-status-success font-bold {{ ($transferSummary['outgoing_cost'] ?? 0) > 0 ? '' : 'mt-2' }}">وارد: {{ number_format($transferSummary['incoming_cost'] ?? 0, 2) }} ر.س</p>
                        @endif
                    </div>
                    <span class="ui-status-info text-xl">↔️</span>
                </div>
                <button type="button" @click="monthlyModal = 'transfers'" class="ui-btn ui-btn-secondary mt-3 w-full">عرض التفاصيل</button>
            </div>
            @endif

            @if(($approvedSupplySummary['orders_count'] ?? 0) > 0)
            <div class="ui-card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="ui-text-soft">طلبات توريد معتمدة</p>
                            <x-ui.help title="طلبات توريد معتمدة" :body="$approvedSupplyHint" />
                        </div>
                        <p class="ui-status-success font-bold text-lg mt-2">{{ number_format($approvedSupplySummary['total_cost'] ?? 0, 2) }} ر.س</p>
                        <p class="ui-text-soft ui-text-caption mt-1">{{ number_format($approvedSupplySummary['orders_count'] ?? 0) }} عملية معتمدة خلال الشهر.</p>
                    </div>
                    <span class="ui-status-success text-xl">📦</span>
                </div>
                <button type="button" @click="monthlyModal = 'approvedSupplies'" class="ui-btn ui-btn-secondary mt-3 w-full">عرض التفاصيل</button>
            </div>
            @endif

            @if(($inventoryValueStart ?? 0) != 0 || ($inventoryValueEnd ?? 0) != 0)
            <div class="ui-card p-4">
                <div class="flex items-start justify-between gap-3">
                    <p class="ui-text-soft">قيمة المخزون بسعر التكلفة</p>
                    <x-ui.help title="قيمة المخزون" :body="$inventoryHint" />
                </div>
                @if(($inventoryValueStart ?? 0) != 0)
                    <p class="ui-status-info font-bold mt-2">أول الشهر: {{ number_format($inventoryValueStart ?? 0, 2) }} ر.س</p>
                @endif
                @if(($inventoryValueEnd ?? 0) != 0)
                    <p class="ui-status-info font-bold {{ ($inventoryValueStart ?? 0) != 0 ? '' : 'mt-2' }}">آخر الشهر: {{ number_format($inventoryValueEnd ?? 0, 2) }} ر.س</p>
                @endif
                @if(($inventoryValueDifferenceAbs ?? 0) > 0)
                    <p class="ui-text-muted ui-text-caption mt-1">الفارق: {{ $inventoryValueDifferenceLabel ?? 'بدون تغيير' }} {{ number_format($inventoryValueDifferenceAbs ?? 0, 2) }} ر.س</p>
                @endif
            </div>
            @endif
        </div>

        <div class="ui-status-success-bg border ui-border rounded-xl p-4 text-sm flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2 ui-status-success">
                <span>صافي النتيجة بعد التكاليف:</span>
                <span class="font-bold text-lg">{{ number_format($netAfterCosts, 2) }} ر.س</span>
                    <x-ui.help title="ما معنى الربح المحتسب؟" :body="$profitFormulaHint" />
            </div>
            <div class="flex items-center gap-3 ui-text-caption">
                @if(($recognizedProfit ?? 0) != 0)
                    <span class="ui-status-info">ربح محتسب: <strong>{{ number_format($recognizedProfit ?? 0, 2) }}</strong> ر.س</span>
                @endif
                @if(($deferredProfit ?? 0) > 0)
                    <span class="ui-status-warning">ربح مؤجل: <strong>{{ number_format($deferredProfit, 2) }}</strong> ر.س</span>
                @endif
            </div>
        </div>

        <div x-show="monthlyModal" class="ui-modal-backdrop" x-cloak>
                <div class="ui-modal-dismiss-layer" @click="monthlyModal = null"></div>
                <div class="ui-modal-panel ui-modal-panel-wide ui-modal-panel-transfer relative">
                    <div class="ui-help-modal-header">
                        <h3 class="ui-title font-black flex items-center gap-2"><i class="fa-solid fa-lightbulb ui-status-info" aria-hidden="true"></i><span x-text="monthlyModalTitles[monthlyModal] ?? 'عرض التفاصيل'"></span></h3>
                        <button type="button" @click="monthlyModal = null" class="ui-help-modal-close" aria-label="إغلاق نافذة عرض التفاصيل"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                    <div class="ui-help-modal-body">

                    <div x-show="monthlyModal === 'sales'" class="space-y-4">
                        <div class="flex items-center justify-center gap-2"><h3 class="ui-title font-bold text-lg">إجمالي المبيعات</h3><x-ui.help title="طريقة احتساب الإجمالي" :body="$salesTotalHint" /></div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            @if(($cashSales ?? 0) > 0)
                                <div class="rounded-xl border ui-border ui-status-success-bg p-4"><p class="ui-status-success">كاش</p><p class="ui-title font-bold">{{ number_format($cashSales, 2) }} ر.س</p></div>
                            @endif
                            @if(($cardSales ?? 0) > 0)
                                <div class="rounded-xl border ui-border ui-status-info-bg p-4"><p class="ui-status-info">شبكة</p><p class="ui-title font-bold">{{ number_format($cardSales, 2) }} ر.س</p></div>
                            @endif
                            @if(abs($unallocatedCollectedSales ?? 0) > 0.009)
                                <div class="rounded-xl border ui-border ui-status-warning-bg p-4"><p class="ui-status-warning">محصل غير موزع</p><p class="ui-title font-bold">{{ number_format($unallocatedCollectedSales, 2) }} ر.س</p></div>
                            @endif
                        </div>
                        <div class="ui-alert ui-alert-neutral"><span class="ui-alert-title">المعادلة</span><span class="ui-alert-body block mt-1">{{ number_format($cashSales, 2) }} كاش + {{ number_format($cardSales, 2) }} شبكة @if(abs($unallocatedCollectedSales ?? 0) > 0.009) + {{ number_format($unallocatedCollectedSales, 2) }} محصل غير موزع @endif = {{ number_format($totalSales, 2) }} ر.س.</span></div>
                        @if(collect($salesReconciliationRows ?? [])->isNotEmpty())
                            <div class="ui-table-wrap"><table class="ui-table"><thead class="ui-table-head"><tr><th>رقم العملية</th><th>التاريخ</th><th>المحصل</th><th>كاش</th><th>شبكة</th><th>الفرق</th></tr></thead><tbody>@foreach($salesReconciliationRows as $differenceRow)<tr><td>#{{ $differenceRow['id'] }}</td><td>{{ $differenceRow['date'] }}</td><td>{{ number_format($differenceRow['paid'], 2) }}</td><td>{{ number_format($differenceRow['cash'], 2) }}</td><td>{{ number_format($differenceRow['card'], 2) }}</td><td class="ui-status-warning font-bold">{{ number_format($differenceRow['difference'], 2) }}</td></tr>@endforeach</tbody></table></div>
                        @endif
                    </div>

                    <div x-show="monthlyModal === 'operatingCosts'" class="space-y-4">
                        <h3 class="ui-title font-bold text-lg">مصروفات تشغيلية</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            @if(($internalUseSales ?? 0) > 0)
                                <div class="rounded-xl border ui-border ui-status-warning-bg p-4"><p class="ui-status-warning">استهلاك المحاسب</p><p class="ui-title font-bold">{{ number_format($internalUseSales, 2) }} ر.س</p></div>
                            @endif
                            @if(($ownerPurchases ?? 0) > 0)
                                <div class="rounded-xl border ui-border ui-status-warning-bg p-4"><p class="ui-status-warning">مشتريات المالك</p><p class="ui-title font-bold">{{ number_format($ownerPurchases, 2) }} ر.س</p></div>
                            @endif
                            @if(($expensesTotal ?? 0) > 0)
                                <div class="rounded-xl border ui-border ui-status-danger-bg p-4"><p class="ui-status-danger">المصروفات</p><p class="ui-title font-bold">{{ number_format($expensesTotal, 2) }} ر.س</p></div>
                            @endif
                        </div>
                    </div>

                    <div x-show="monthlyModal === 'payroll'" class="space-y-4">
                        <h3 class="ui-title font-bold text-lg">تفاصيل الرواتب والسحوبات</h3>
                        <div class="overflow-x-auto rounded-xl border ui-border">
                            <table class="w-full text-sm">
                                <thead class="ui-surface-strong-bg ui-text-soft"><tr><th class="p-3 text-right">الموظف</th><th class="p-3 text-right">الراتب</th><th class="p-3 text-right">السحوبات</th><th class="p-3 text-right">الغياب</th><th class="p-3 text-right">أيام العمل</th><th class="p-3 text-right">الباقي</th></tr></thead>
                                <tbody>
                                    @forelse(($employeeRows ?? collect()) as $employeeRow)
                                        <tr class="border-t ui-border ui-text-soft"><td class="p-3">{{ $employeeRow['name'] }}</td><td class="p-3">{{ number_format($employeeRow['salary'], 2) }}</td><td class="p-3">{{ number_format($employeeRow['withdrawals'], 2) }}</td><td class="p-3">{{ number_format($employeeRow['absences_count'] ?? 0) }}</td><td class="p-3">{{ number_format($employeeRow['worked_days'] ?? 0) }}</td><td class="p-3 ui-status-success font-bold">{{ number_format($employeeRow['net_salary'], 2) }}</td></tr>
                                    @empty
                                        <tr><td colspan="6" class="p-4 text-center ui-text-muted">لا توجد بيانات موظفين.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div x-show="monthlyModal === 'transfers'" class="space-y-4">
                        <h3 class="ui-title font-bold text-lg">كشف النقل المخزني</h3>
                        <p class="ui-text-muted ui-text-caption">{{ $transferSummary['note'] ?? 'النقل المخزني كشف تشغيلي فقط.' }}</p>
                        <div class="overflow-x-auto rounded-xl border ui-border">
                            <table class="w-full text-sm">
                                <thead class="ui-surface-strong-bg ui-text-soft"><tr><th class="p-3 text-right">الوقت</th><th class="p-3 text-right">المنتج</th><th class="p-3 text-right">النوع</th><th class="p-3 text-right">المتجر الآخر</th><th class="p-3 text-right">السعر</th><th class="p-3 text-right">الإجمالي</th></tr></thead>
                                <tbody>
                                    @forelse(($transferRows ?? collect()) as $row)
                                        <tr class="border-t ui-border ui-text-soft"><td class="p-3">{{ $row['date'] }}</td><td class="p-3">{{ $row['direction'] === 'صادر' ? ($row['sender_product'] ?? '—') : ($row['receiver_product'] ?? $row['sender_product'] ?? '—') }}</td><td class="p-3 {{ $row['direction'] === 'صادر' ? 'ui-status-danger' : 'ui-status-success' }}">{{ $row['direction'] }}</td><td class="p-3">{{ $row['other_store'] ?? '—' }}</td><td class="p-3">{{ number_format($row['cost_price'] ?? 0, 2) }}</td><td class="p-3 font-bold">{{ number_format($row['total_cost'] ?? 0, 2) }}</td></tr>
                                    @empty
                                        <tr><td colspan="6" class="p-4 text-center ui-text-muted">لا توجد عمليات نقل مخزني.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @if(($transferSummary['outgoing_cost'] ?? 0) > 0)
                                <div class="rounded-xl border ui-border ui-status-danger-bg p-4"><p class="ui-status-danger">إجمالي الصادرات</p><p class="ui-title font-bold">{{ number_format($transferSummary['outgoing_cost'] ?? 0, 2) }} ر.س</p></div>
                            @endif
                            @if(($transferSummary['incoming_cost'] ?? 0) > 0)
                                <div class="rounded-xl border ui-border ui-status-success-bg p-4"><p class="ui-status-success">إجمالي الواردات</p><p class="ui-title font-bold">{{ number_format($transferSummary['incoming_cost'] ?? 0, 2) }} ر.س</p></div>
                            @endif
                        </div>
                    </div>

                    <div x-show="monthlyModal === 'approvedSupplies'" class="space-y-4">
                        <div class="flex items-center gap-2">
                            <h3 class="ui-title font-bold text-lg">تفاصيل الطلبيات المعتمدة</h3>
                            <x-ui.help title="احتساب التوريد"  :body="$approvedSupplyHint" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="rounded-xl border ui-border ui-status-info-bg p-4"><p class="ui-status-info">عدد العمليات</p><p class="ui-title font-bold">{{ number_format($approvedSupplySummary['orders_count'] ?? 0) }}</p></div>
                            @if(($approvedSupplySummary['owner_products_count'] ?? 0) > 0 || ($approvedSupplySummary['owner_products_cost'] ?? 0) > 0)
                                <div class="rounded-xl border ui-border ui-status-warning-bg p-4"><p class="ui-status-warning">مشتريات المالك</p><p class="ui-title font-bold">{{ number_format($approvedSupplySummary['owner_products_count'] ?? 0) }} منتج — {{ number_format($approvedSupplySummary['owner_products_cost'] ?? 0, 2) }} ر.س</p></div>
                            @endif
                            @if(($approvedSupplySummary['stock_products_count'] ?? 0) > 0 || ($approvedSupplySummary['stock_products_cost'] ?? 0) > 0)
                                <div class="rounded-xl border ui-border ui-status-success-bg p-4"><p class="ui-status-success">المضاف للمخزن</p><p class="ui-title font-bold">{{ number_format($approvedSupplySummary['stock_products_count'] ?? 0) }} منتج — {{ number_format($approvedSupplySummary['stock_products_cost'] ?? 0, 2) }} ر.س</p></div>
                            @endif
                        </div>
                        <div class="ui-table-wrap">
                            <table class="ui-table">
                                <thead class="ui-table-head"><tr><th>الطلبية</th><th>التاريخ</th><th>المورد</th><th>مشتريات المالك</th><th>تكلفتها</th><th>المضاف للمخزن</th><th>تكلفته</th><th>إجمالي الطلبية</th></tr></thead>
                                <tbody>
                                    @forelse(($approvedSupplySummary['rows'] ?? collect()) as $row)
                                        <tr><td>#{{ $row['id'] }}</td><td>{{ $row['date'] }}</td><td>{{ $row['supplier'] }}</td><td>{{ number_format($row['owner_products_count']) }}</td><td>{{ number_format($row['owner_products_cost'], 2) }}</td><td>{{ number_format($row['stock_products_count']) }}</td><td>{{ number_format($row['stock_products_cost'], 2) }}</td><td class="font-bold">{{ number_format($row['total_cost'], 2) }} ر.س</td></tr>
                                    @empty
                                        <tr><td colspan="8" class="p-4 text-center ui-text-muted">لا توجد عمليات توريد معتمدة مخزنيًا في هذا الشهر.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ui-surface-strong-bg overflow-x-auto rounded-2xl border ui-border">
        <table class="w-full min-w-[900px] text-sm">
            <thead class="ui-surface-muted-bg ui-text-soft">
                <tr>
                    <th class="p-3 text-right">#</th>
                    <th class="p-3 text-right">اليوم</th>
                    <th class="p-3 text-right">عدد العمليات</th>
                    <th class="p-3 text-right">كاش</th>
                    <th class="p-3 text-right">شبكة</th>
                    <th class="p-3 text-right">الإجمالي العام</th>
                    <th class="p-3 text-right">تكلفة مغطاة</th>
                    <th class="p-3 text-right">ربح محتسب</th>
                    <th class="p-3 text-right">ربح مؤجل</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dailyRowsCollection as $index => $row)
                    <tr class="border-t ui-border ui-text-soft">
                        <td class="p-3">{{ $index + 1 }}</td>
                        <td class="p-3">{{ $row->day }}</td>
                        <td class="p-3">{{ number_format($row->ops_count) }}</td>
                        <td class="p-3 ui-status-success">{{ number_format($row->cash_total ?? 0, 2) }} ر.س</td>
                        <td class="p-3 ui-status-info">{{ number_format($row->card_total ?? 0, 2) }} ر.س</td>
                        <td class="p-3 font-bold ui-status-success">{{ number_format($row->sales_total, 2) }} ر.س</td>
                        <td class="p-3 ui-status-success">{{ number_format($row->recognized_cost ?? 0, 2) }} ر.س</td>
                        <td class="p-3 ui-status-info">{{ number_format($row->recognized_profit ?? 0, 2) }} ر.س</td>
                        <td class="p-3 ui-status-warning">{{ number_format($row->deferred_profit ?? 0, 2) }} ر.س</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="p-6 text-center ui-text-muted">لا توجد بيانات في هذا الشهر.</td></tr>
                @endforelse
                @if($dailyRowsCollection->isNotEmpty())
                    <tr class="border-t ui-border ui-surface-muted-bg ui-title font-bold">
                        <td class="p-3" colspan="2">الإجمالي</td>
                        <td class="p-3">{{ number_format($dailyTotals['ops_count']) }}</td>
                        <td class="p-3 ui-status-success">{{ number_format($dailyTotals['cash_total'], 2) }} ر.س</td>
                        <td class="p-3 ui-status-info">{{ number_format($dailyTotals['card_total'], 2) }} ر.س</td>
                        <td class="p-3 ui-status-success">{{ number_format($dailyTotals['sales_total'], 2) }} ر.س</td>
                        <td class="p-3 ui-status-success">{{ number_format($dailyTotals['recognized_cost'], 2) }} ر.س</td>
                        <td class="p-3 ui-status-info">{{ number_format($dailyTotals['recognized_profit'], 2) }} ر.س</td>
                        <td class="p-3 ui-status-warning">{{ number_format($dailyTotals['deferred_profit'], 2) }} ر.س</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
