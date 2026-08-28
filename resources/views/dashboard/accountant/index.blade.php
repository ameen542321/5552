@extends('dashboard.app')

@section('title', 'لوحة المحاسب')

@section('content')
@php
    $collectionRowsForDashboard = collect($accountantFinanceMovements['collection_rows'] ?? []);
    $creditCollectionRowsForDashboard = $collectionRowsForDashboard->where('collection_kind', 'credit');
    $debtCollectionRowsForDashboard = $collectionRowsForDashboard->where('collection_kind', 'debt');
    $collectionBadge = function ($label, $rows) {
        $rows = collect($rows);
        $cash = (float) $rows->sum('cash_amount');
        $card = (float) $rows->sum('card_amount');
        $total = (float) $rows->sum('amount');

        return compact('label', 'cash', 'card', 'total');
    };
    $dashboardCollectionBadges = collect([
        $collectionBadge('تحصيل أجل', $creditCollectionRowsForDashboard),
        $collectionBadge('تحصيل مديونية', $debtCollectionRowsForDashboard),
    ])->filter(fn ($row) => ($row['total'] ?? 0) > 0)->values();
@endphp
<div>

{{-- العنوان --}}
<section class="ui-card mb-6 p-4 sm:p-5">
<div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div>
        <h1 class="text-2xl font-bold ui-title">لوحة المحاسب</h1>
        <p class="ui-text-soft text-sm mt-1">نظرة عامة على العمليات المالية لليوم</p>
        <div class="flex items-center gap-2 mt-2">
            <span class="ui-store-badge">المتجر: {{ auth('accountant')->user()->store->name ?? 'غير محدد' }}</span>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if(($pendingInventoryCountSessions ?? collect())->isNotEmpty())
            <a href="{{ route('accountant.inventory-counts.index') }}" class="ui-btn ui-btn-warning" aria-label="فتح طلبات الجرد">
                <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>
                طلبات الجرد
                <x-ui.badge variant="warning">{{ $pendingInventoryCountSessions->count() }}</x-ui.badge>
            </a>
        @endif
        <x-purchase-order-alerts-button :alerts="$pendingPurchaseOrderAlerts ?? collect()" context="accountant" />
        <x-accountant-dashboard-alerts
            :incoming-transfers="$pendingIncomingTransfersCount ?? 0"
            :outgoing-transfers="$pendingOutgoingTransfersCount ?? 0"
            :shift-requests="$pendingShiftGapRequests ?? collect()"
            :active-reference-date="$activeShiftGapBusinessDate ?? null" />
    </div>
</div>
</section>

{{-- البطاقات الإحصائية --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

    {{-- مبيعات الوردية مع التولتيب --}}
   <div class="ui-card p-5 ui-hover-info-border transition-all">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <p class="ui-text-soft text-sm">💰 مبيعات</p>
                {{-- توضيح: استبدلنا عبارة التاريخ بتاريخ العمل لتقليل الالتباس عند المحاسب. --}}
                <button type="button" data-ui-help-title="مبيعات الشفت" data-ui-help-body="إجمالي عمليات البيع المسجلة على تاريخ العمل الحالي للشفت المفتوح." class="ui-help-btn" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
            </div>
            <h3 class="text-2xl font-bold ui-title mt-1">{{ number_format($totalSinceBalance, 2) }} <span class="ui-text-caption ui-text-soft">ريال</span></h3>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-2 mb-1">
                {{-- نقداً مع التولتيب --}}
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 ui-dot-success rounded-full"></span>
                    <span class="ui-text-caption ui-text-soft">نقداً:</span>
                    <span class="ui-text-caption ui-title font-bold">{{ number_format(($cashSales ?? 0) + ($cashFromCollections ?? 0), 2) }}</span>
                    <button type="button" data-ui-help-title="النقد" data-ui-help-body="المبلغ المقبوض نقداً ضمن مبيعات الشفت المفتوح، ويشمل تحصيلات الآجل/المديونية النقدية التي نفذها المحاسب." class="ui-help-btn ui-help-btn-sm" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                </div>

                {{-- شبكة مع التولتيب --}}
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 ui-dot-info rounded-full"></span>
                    <span class="ui-text-caption ui-text-soft">شبكة:</span>
                    <span class="ui-text-caption ui-title font-bold">{{ number_format($cardSales ?? 0, 2) }}</span>
                    <button type="button" data-ui-help-title="الشبكة" data-ui-help-body="المبلغ المقبوض عبر الشبكة ضمن مبيعات الشفت المفتوح." class="ui-help-btn ui-help-btn-sm" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                </div>

                {{-- آجل مع التولتيب --}}
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 ui-dot-warning rounded-full"></span>
                    <span class="ui-text-caption ui-text-soft">آجل:</span>
                    <span class="ui-text-caption ui-title font-bold">{{ number_format($pendingCreditTotal ?? 0, 2) }}</span>
                    <button type="button" data-ui-help-title="الآجل" data-ui-help-body="المبالغ المتبقية من فواتير الآجل المرتبطة بالشفت الحالي." class="ui-help-btn ui-help-btn-sm" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                </div>
            </div>
            @if($dashboardCollectionBadges->isNotEmpty())
                <div class="mt-2 space-y-1 pt-2">
                    @foreach($dashboardCollectionBadges as $badge)
                        <div class="ui-text-caption ui-text-soft">
                            <span class="font-bold ui-status-warning">{{ $badge['label'] }}:</span>
                            @if(($badge['cash'] ?? 0) > 0)<span class="ui-status-success">{{ number_format($badge['cash'], 2) }} كاش</span>@endif
                            @if(($badge['cash'] ?? 0) > 0 && ($badge['card'] ?? 0) > 0)<span class="ui-text-muted"> / </span>@endif
                            @if(($badge['card'] ?? 0) > 0)<span class="ui-status-info">{{ number_format($badge['card'], 2) }} شبكة</span>@endif
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="flex items-center gap-2 mt-2">
                <span class="ui-text-caption ui-text-muted">منذ {{ $startTime->format('h:i A') }}</span>
                @if($salesEfficiency != 0)
                <span class="ui-text-caption px-2 py-1 rounded {{ $salesEfficiency >= 0 ? 'ui-status-success-bg ui-status-success' : 'ui-status-danger-bg ui-status-danger' }}">
                    {{ $salesEfficiency >= 0 ? '+' : '' }}{{ number_format($salesEfficiency, 1) }}%
                </span>
                @endif
            </div>
        </div>
        <div class="ui-status-info-bg ui-status-info p-3 rounded-lg">
            <i class="fa-solid fa-cart-shopping text-xl"></i>
        </div>
    </div>
</div>

    {{-- مصاريف الوردية مع التولتيب --}}
    <div class="ui-card p-5 ui-hover-danger-border transition-all">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <p class="ui-text-soft text-sm">💸 المصروفات</p>
                    <button type="button" data-ui-help-title="المصاريف" data-ui-help-body="المصاريف التي تم دفعها أو تسديدها من كاش الشفت الحالي." class="ui-help-btn" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                </div>
                <h3 class="text-2xl font-bold ui-status-danger mt-1">{{ number_format($currentShiftExpenses, 2) }} <span class="ui-text-caption ui-text-soft">ريال</span></h3>
                <div class="mt-2">
                    <span class="ui-text-caption ui-text-muted">
                        {{ $stats['monthly_expenses'] > 0 ? round(($currentShiftExpenses / $stats['monthly_expenses']) * 100, 1) : 0 }}% من إجمالي الشهر
                    </span>
                </div>
            </div>
            <div class="ui-status-danger-bg ui-status-danger p-3 rounded-lg">
                <i class="fa-solid fa-receipt text-xl"></i>
            </div>
        </div>
    </div>

    {{-- سحوبات الوردية مع التولتيب --}}
    <div class="ui-card p-5 ui-hover-warning-border transition-all">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <p class="ui-text-soft text-sm">🏦 السحوبات</p>
                    <button type="button" data-ui-help-title="سحوبات الشفت" data-ui-help-body="المبالغ التي خرجت من الدرج كسحب على موظف أو جهة محددة داخل الشفت الحالي." class="ui-help-btn" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                </div>
                <h3 class="text-2xl font-bold ui-status-warning mt-1">{{ number_format($currentShiftWithdrawals, 2) }} <span class="ui-text-caption ui-text-soft">ريال</span></h3>
                <div class="mt-2">
                    <span class="ui-text-caption ui-text-muted">
                        {{ $stats['monthly_withdrawals'] > 0 ? round(($currentShiftWithdrawals / $stats['monthly_withdrawals']) * 100, 1) : 0 }}% من إجمالي الشهر
                    </span>
                </div>
            </div>
            <div class="ui-status-warning-bg ui-status-warning p-3 rounded-lg">
                <i class="fa-solid fa-hand-holding-usd text-xl"></i>
            </div>
        </div>
    </div>

    {{-- بطاقة التحصيلات: تعرض تحصيل الأجل وتحصيل نظام المديونية كتحصيلات منفصلة نفذها المحاسب فقط. --}}
    @if(($accountantFinanceMovements['collections_total'] ?? $cashFromCollections) > 0)
    <div class="ui-card p-5 ui-hover-success-border transition-all">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <p class="ui-text-soft text-sm">💳 التحصيلات</p>
                    <button type="button" data-ui-help-title="التحصيلات" data-ui-help-body="تعرض فقط المبالغ التي تم تحصيلها فعلياً من آجل أو مديونية خلال الشفت. يظهر التحصيل كجزئي أو كلي، ولا تظهر التحصيلات ذات القيمة صفر." class="ui-help-btn" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                </div>
                <h3 class="text-2xl font-bold ui-status-success mt-1">{{ number_format($accountantFinanceMovements['collections_total'] ?? $cashFromCollections, 2) }} <span class="ui-text-caption ui-text-soft">ريال</span></h3>
                <div class="mt-2">
                    <span class="ui-text-caption ui-text-muted">{{ $creditCollections['count'] ?? 0 }} عملية تحصيل</span>
                    <div class="mt-1 flex flex-wrap gap-2 ui-text-caption">
                        @foreach($dashboardCollectionBadges as $badge)
                            <span class="rounded-full {{ $badge['label'] === 'تحصيل مديونية' ? 'ui-status-info-bg ui-status-info' : 'ui-status-warning-bg ui-status-warning' }} px-2 py-0.5">
                                {{ $badge['label'] }}
                                @if(($badge['card'] ?? 0) > 0) {{ number_format($badge['card'], 2) }} شبكة@endif
                                @if(($badge['cash'] ?? 0) > 0) {{ number_format($badge['cash'], 2) }} كاش@endif
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="ui-status-success-bg ui-status-success p-3 rounded-lg">
                <i class="fa-solid fa-money-check-dollar text-xl"></i>
            </div>
        </div>
    </div>
    @else
    <div class="ui-card p-5 ui-hover-info-border transition-all">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <p class="ui-text-soft text-sm">📅 إحصائيات الشهر</p>
                    <button type="button" data-ui-help-title="إحصائيات الشهر" data-ui-help-body="عدد أيام العمل ومتوسط المبيعات اليومي للشهر الحالي، وتظهر صفراً أثناء معالجة يوم مرجع حتى لا تختلط بعمليات اليوم الحالي." class="ui-help-btn" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                </div>
                <h3 class="text-2xl font-bold ui-status-info mt-1">{{ $workingDays }} <span class="ui-text-caption ui-text-soft">يوم عمل</span></h3>
                <div class="mt-2">
                    <span class="ui-text-caption ui-text-muted">متوسط يومي: {{ number_format($dailyAverage, 2) }} ريال</span>
                </div>
            </div>
            <div class="ui-status-info-bg ui-status-info p-3 rounded-lg">
                <i class="fa-solid fa-chart-line text-xl"></i>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- بطاقة تفاصيل العمليات + مودال الشفت --}}
<div x-data="{ openOperationsModal: false }" class="mt-6">
    <div class="ui-card p-5 ui-hover-info-border transition-all cursor-pointer"
         @click="openOperationsModal = true">
        <div class="flex items-center justify-between">
            <div>
                {{-- توضيح: زر المساعدة هنا يشرح أن تفاصيل العمليات مرتبطة بالشفت/يوم المرجع وليس بسجل عام. --}}
                <div class="flex items-center gap-2"><p class="ui-title text-sm font-bold">📋 تفاصيل العمليات</p><button type="button" @click.stop data-ui-help-title="تفاصيل العمليات" data-ui-help-body="يعرض تفصيلاً للعمليات المرتبطة بالشفت المفتوح أو يوم المرجع المفعل، مع فصل النقد والشبكة والآجل والمصاريف والسحوبات." class="ui-help-btn" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button></div>
                <h3 class="text-2xl font-bold ui-status-info mt-1">{{ number_format($shiftOperationDetails['count'] ?? 0) }} <span class="ui-text-caption ui-text-soft">عملية</span></h3>
                <p class="ui-text-caption ui-text-soft mt-2">المعروض فقط خلال وقت الشفت الحالي (من {{ $startTime->format('h:i A') }})</p>
            </div>
            <div class="ui-status-info-bg ui-status-info p-3 rounded-lg">
                <i class="fa-solid fa-list-check text-xl"></i>
            </div>
        </div>
    </div>

    <div x-show="openOperationsModal" x-transition.opacity class="ui-modal-backdrop" x-cloak>
        <div @click.away="openOperationsModal = false" class="ui-modal-panel ui-modal-panel-wide">
            <div class="ui-modal-header">
                <h2 class="ui-title text-xl font-bold">تفاصيل العمليات</h2>
                <button type="button" @click="openOperationsModal = false" class="ui-modal-close-text-danger">إغلاق</button>
            </div>
            <div class="overflow-x-auto p-5">
                <div class="mb-4 flex flex-wrap items-center gap-2 ui-text-caption ui-text-soft">
                        @if(($shiftOperationDetails['sales_total'] ?? 0) > 0)<span aria-label="المبيعات" class="inline-flex items-center gap-1 rounded-full ui-status-success-bg px-2 py-1 ui-status-success"><i class="fa-solid fa-cart-shopping"></i>{{ number_format($shiftOperationDetails['sales_total'], 2) }}</span>@endif
                        @if(($shiftOperationDetails['cash_total'] ?? 0) > 0)<span aria-label="كاش" class="inline-flex items-center gap-1 rounded-full ui-status-success-bg px-2 py-1 ui-status-success"><i class="fa-solid fa-money-bill-wave"></i>{{ number_format($shiftOperationDetails['cash_total'], 2) }}</span>@endif
                        @if(($shiftOperationDetails['card_total'] ?? 0) > 0)<span aria-label="شبكة" class="inline-flex items-center gap-1 rounded-full ui-status-info-bg px-2 py-1 ui-status-info"><i class="fa-solid fa-credit-card"></i>{{ number_format($shiftOperationDetails['card_total'], 2) }}</span>@endif
                        @if((($shiftOperationDetails['collections_total'] ?? 0) + ($shiftOperationDetails['credit_total'] ?? 0)) > 0)<span aria-label="تحصيلات المحاسب" class="inline-flex items-center gap-1 rounded-full ui-status-warning-bg px-2 py-1 ui-status-warning"><i class="fa-solid fa-clock"></i>{{ number_format(($shiftOperationDetails['collections_total'] ?? 0) + ($shiftOperationDetails['credit_total'] ?? 0), 2) }}</span>@endif
                        @if(($shiftOperationDetails['expenses_total'] ?? 0) > 0)<span aria-label="مصروف" class="inline-flex items-center gap-1 rounded-full ui-status-danger-bg px-2 py-1 ui-status-danger"><i class="fa-solid fa-receipt"></i>{{ number_format($shiftOperationDetails['expenses_total'], 2) }}</span>@endif
                        @if(($shiftOperationDetails['withdrawals_total'] ?? 0) > 0)<span aria-label="سحب" class="inline-flex items-center gap-1 rounded-full ui-status-warning-bg px-2 py-1 ui-status-warning"><i class="fa-solid fa-arrow-up-from-bracket"></i>{{ number_format($shiftOperationDetails['withdrawals_total'], 2) }}</span>@endif
                </div>
                <table class="ui-table w-full text-right text-sm">
                    <thead class="ui-table-head">
                        <tr>
                            <th class="p-3">الوقت</th>
                            <th class="p-3">نوع العملية</th>
                            <th class="p-3">المنتج / البيان</th>
                            <th class="p-3">نوع الدفع</th>
                            <th class="p-3 text-left">المبلغ</th>
                        </tr>
                    </thead>
                    <tbody class="ui-divide-border">
                        @forelse(($shiftOperationDetails['rows'] ?? collect()) as $row)
                            <tr class="ui-hover-surface">
                                <td class="p-3 ui-text-soft">{{ \Carbon\Carbon::parse($row['time'])->format('h:i A') }}</td>
                                <td class="p-3 ui-title">{{ $row['operation_type'] }}</td>
                                <td class="p-3 ui-text-soft">
                                    <div class="font-medium ui-text-soft">{{ $row['product'] ?: '-' }}</div>
                                    @if(!empty($row['note']))
                                        <div class="mt-1 ui-text-caption ui-text-muted">{{ $row['note'] }}</div>
                                    @endif
                                    @if(!empty($row['employee_name']) || !empty($row['actor']) || !empty($row['operation_date']) || str_contains(($row['operation_type'] ?? ''), 'آجل') || str_contains(($row['operation_type'] ?? ''), 'تحصيل'))
                                        <div class="mt-1 flex flex-wrap gap-2 ui-text-caption ui-text-soft">
                                            @if(!empty($row['employee_name']))<span>الموظف: {{ $row['employee_name'] }}</span>@endif
                                            @if(!empty($row['actor']))<span>القيد: {{ $row['actor'] }}</span>@endif
                                            @if(!empty($row['operation_date']))<span>بتاريخ: {{ $row['operation_date'] }}</span>@endif
                                            @if(isset($row['debt_parent_amount']))<span>إجمالي المديونية المحصل منها: {{ number_format($row['debt_parent_amount'], 2) }}</span>@endif
                                            @if(isset($row['operation_amount']) && !isset($row['debt_parent_amount']))<span>إجمالي العملية: {{ number_format($row['operation_amount'], 2) }}</span>@endif
                                            @if(($row['credit_amount'] ?? 0) > 0)<span>الأجل: {{ number_format($row['credit_amount'], 2) }}</span>@endif
                                            @if(($row['remaining_amount'] ?? 0) > 0)<span>المتبقي: {{ number_format($row['remaining_amount'], 2) }}</span>@endif
                                        </div>
                                    @endif
                                </td>
                                <td class="p-3 ui-text-soft">
                                    @if(($row['cash_amount'] ?? 0) > 0 || ($row['card_amount'] ?? 0) > 0)
                                        <div class="flex flex-col gap-0.5 ui-text-caption">
                                            @if(($row['cash_amount'] ?? 0) > 0)<span class="ui-status-success">كاش: {{ number_format($row['cash_amount'], 2) }}</span>@endif
                                            @if(($row['card_amount'] ?? 0) > 0)<span class="ui-status-info">شبكة: {{ number_format($row['card_amount'], 2) }}</span>@endif
                                        </div>
                                    @else
                                        <div>{{ $row['payment_type'] }}</div>
                                    @endif
                                </td>
                                <td class="p-3 text-left font-bold {{ in_array($row['operation_type'], ['مصروف', 'سحب', 'مديونية']) ? 'ui-status-danger' : 'ui-status-success' }}">
                                    {{ number_format($row['amount'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center ui-text-muted">لا توجد عمليات مرتبطة بالشفت الحالي.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


{{-- بطاقة إقفال الشفت (مع منطق الحساب اللحظي) --}}
<div x-data="{
	    openConfirm: false,
	    actualCash: {{ $cashInSafe }},
	    baseExpectedCash: {{ $cashInSafe }},
	    includedDebtAdjustments: [],
	    notes: '',
	    get includedDebtTotal() {
	        return this.includedDebtAdjustments.reduce((sum, item) => sum + Number(item.amount || 0), 0);
	    },
	    get expectedCash() {
	        return this.baseExpectedCash - this.includedDebtTotal;
	    },
    get difference() {
        let diff = this.actualCash - this.expectedCash;
        return diff;
    },
    get isShortage() {
        // عجز: الفرق سالب (الفعلي أقل من المتوقع)
        return this.difference < 0;
    },
    get isSurplus() {
        // زيادة: الفرق موجب (الفعلي أكثر من المتوقع)
        return this.difference > 0;
    },
    get isBalanced() {
        // مطابق: الفرق صفر
        return this.difference == 0;
    },
    get shortageAmount() {
        return this.isShortage ? Math.abs(this.difference) : 0;
    },
    get surplusAmount() {
        return this.isSurplus ? this.difference : 0;
    },
    get differenceDisplay() {
        let diff = this.difference;
        if (diff > 0) return '+' + diff.toFixed(2);
        if (diff < 0) return diff.toFixed(2);
        return '0.00';
    }
}" class="relative mt-6">
    <div @click="openConfirm = true" class="ui-card p-5 cursor-pointer transition-all duration-300 ui-hover-brand-border">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <p class="ui-title text-sm font-bold">⏰ إصدار الموازنة اليومية</p>
                    <button type="button"
                            data-ui-help-title="شرح إصدار الموازنة" data-ui-help-body="إصدار الموازنة يغلق الشفت المفتوح ويربط عملياته بتاريخ العمل الخاص به. إذا اخترت الانتقال لليوم التالي مع بقاء شفت مطلوب لنفس التاريخ، سيبقى هذا الشفت ظاهرًا كطلب مؤجل فارغ حتى يتم فتحه وإقفاله لاحقًا دون خلطه بعمليات اليوم الحالي."
                            class="ui-help-btn"
                            aria-label="شرح إصدار الموازنة اليومية">
                        <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                    </button>
                </div>
                <h3 class="text-2xl font-bold ui-title mt-1">{{ number_format($totalSinceBalance, 2) }} <span class="ui-text-caption ui-text-muted">المبيعات</span></h3>
                <div class="mt-2 flex flex-wrap items-center gap-2 ui-text-caption ui-text-soft">
                    @if(($cashSales ?? 0) > 0)<span aria-label="كاش" class="inline-flex items-center gap-1 rounded-full ui-status-success-bg px-2 py-1 ui-status-success"><i class="fa-solid fa-money-bill-wave"></i>{{ number_format($cashSales, 2) }}</span>@endif
                    @if(($cardSales ?? 0) > 0)<span aria-label="شبكة" class="inline-flex items-center gap-1 rounded-full ui-status-info-bg px-2 py-1 ui-status-info"><i class="fa-solid fa-credit-card"></i>{{ number_format($cardSales, 2) }}</span>@endif
                    @if(($mixedSales ?? 0) > 0)<span aria-label="مكس" class="inline-flex items-center gap-1 rounded-full ui-status-info-bg px-2 py-1 ui-status-info"><i class="fa-solid fa-shuffle"></i>{{ number_format($mixedSales, 2) }}</span>@endif
	                    @if(($cashFromCollections ?? 0) > 0)<span aria-label="تحصيلات المحاسب" class="inline-flex items-center gap-1 rounded-full ui-status-warning-bg px-2 py-1 ui-status-warning"><i class="fa-solid fa-clock"></i>{{ number_format($cashFromCollections, 2) }}</span>@endif
	                    @if(($accountantFinanceMovements['debt_total'] ?? 0) > 0)<span aria-label="مديونيات محاسب اختيارية" class="inline-flex items-center gap-1 rounded-full ui-status-info-bg px-2 py-1 ui-status-info"><i class="fa-solid fa-file-invoice-dollar"></i>{{ number_format($accountantFinanceMovements['debt_total'], 2) }}</span>@endif
                    @if(($currentShiftExpenses ?? 0) > 0)<span aria-label="مصروف" class="inline-flex items-center gap-1 rounded-full ui-status-danger-bg px-2 py-1 ui-status-danger"><i class="fa-solid fa-receipt"></i>{{ number_format($currentShiftExpenses, 2) }}</span>@endif
                    @if(($currentShiftWithdrawals ?? 0) > 0)<span aria-label="سحب" class="inline-flex items-center gap-1 rounded-full ui-status-warning-bg px-2 py-1 ui-status-warning"><i class="fa-solid fa-arrow-up-from-bracket"></i>{{ number_format($currentShiftWithdrawals, 2) }}</span>@endif
                </div>
                <div class="mt-2 ui-text-caption leading-5 ui-text-soft">
                    التكلفة: {{ number_format($totalCost ?? 0, 2) }}
                </div>
            </div>
            <div class="relative">
                <div class="ui-surface-chip ui-brand-text p-3 rounded-lg animate-pulse">
                    <i class="fa-solid fa-scale-balanced text-xl"></i>
                </div>
                @if($shiftDuration > 8)
                <div class="absolute -top-2 -right-2 w-4 h-4 ui-dot-warning rounded-full animate-ping"></div>
                @endif
            </div>
        </div>
        <div class="mt-4 pt-4 ">
            <div class="flex justify-between ui-text-caption">
                <div>
                    <span class="ui-text-soft">المتوقع في الصندوق:</span>
	                    <span class="ui-title font-bold ml-2" x-text="Number(expectedCash).toFixed(2) + ' ريال'"></span>
                </div>
                <div class="text-right">
                    <span class="ui-text-soft">آخر اصدار:</span>
                    <span class="ui-text-soft ml-2">{{ $lastBalanceTime }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- نافذة التأكيد المحدثة مع سكرول --}}
    <div x-show="openConfirm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-90"
         x-transition:enter-end="opacity-100 scale-100"
         class="ui-modal-backdrop"
         x-cloak>

        <div class="ui-modal-panel" @click.away="openConfirm = false">
            <div class="ui-modal-header">
                <h2 class="text-xl font-bold ui-title">
                    {{ !empty($activeShiftGapBusinessDate) ? 'تأكيد إصدار موازنة يوم مرجع' : 'تأكيد إصدار الموازنة اليومية' }}
                </h2>
                <button type="button" @click="openConfirm = false" class="ui-modal-close-text-danger">إغلاق</button>
            </div>
            <div class="p-5 space-y-4">
                <p class="ui-text-soft text-sm text-center">
                    @if(!empty($activeShiftGapBusinessDate))
                        {{-- توضيح: عند معالجة يوم مرجع نخفي الشرح الطويل داخل تولتيب حتى لا يختلط مع إقفال الشفت الحالي. --}}
                        <span class="inline-flex items-center justify-center gap-2">موازنة يوم مرجع <button type="button" data-ui-help-title="موازنة يوم مرجع" data-ui-help-body="هذه الموازنة خاصة بالتاريخ المرجع {{ $activeShiftGapBusinessDate }} وليست إقفالًا للشفت الحالي." class="ui-help-btn ui-help-btn-sm" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button></span>
                    @else
                        ملخص الحساب النقدي
                    @endif
                </p>

                {{-- المحتوى القابل للسكرول --}}
                {{-- تفاصيل الحساب النقدي للوردية --}}
                <div class="ui-card p-4 space-y-3">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 ui-dot-info rounded-full"></span>
                            <span class="ui-title font-bold text-base">إجمالي المبيعات:</span>
                            <button type="button" data-ui-help-title="المبيعات" data-ui-help-body="مبيعات الشفت التي تم قبضها فعلياً نقداً أو شبكة، مع عرض الآجل المسجل في الشفت بشكل مستقل." class="ui-help-btn ui-help-btn-sm" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                        </div>
                        <span class="ui-title font-black text-lg">{{ number_format($totalSinceBalance, 2) }} ريال</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-2 mb-1">
                        {{-- نقداً --}}
                        <div class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 ui-dot-success rounded-full"></span>
                            <span class="ui-text-caption ui-text-soft">نقداً:</span>
                            <span class="ui-text-caption ui-title font-bold">{{ number_format(($cashSales ?? 0) + ($cashFromCollections ?? 0), 2) }}</span>
                            <button type="button" data-ui-help-title="النقد" data-ui-help-body="المقبوض نقداً داخل الشفت المفتوح شاملاً تحصيلات المحاسب النقدية." class="ui-help-btn ui-help-btn-sm" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                        </div>

                        {{-- شبكة --}}
                        <div class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 ui-dot-info rounded-full"></span>
                            <span class="ui-text-caption ui-text-soft">شبكة:</span>
                            <span class="ui-text-caption ui-title font-bold">{{ number_format($cardSales ?? 0, 2) }}</span>
                            <button type="button" data-ui-help-title="الشبكة" data-ui-help-body="المقبوض عبر الشبكة داخل الشفت المفتوح." class="ui-help-btn ui-help-btn-sm" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                        </div>

                        {{-- آجل --}}
                        <div class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 ui-dot-warning rounded-full"></span>
                            <span class="ui-text-caption ui-text-soft">آجل:</span>
                            <span class="ui-text-caption ui-status-warning font-bold">{{ number_format($officialCreditSales ?? 0, 2) }}</span>
                            <button type="button" data-ui-help-title="الآجل" data-ui-help-body="عمليات البيع التي تم تسجيلها آجل خلال الشفت الحالي ولم تقبض بالكامل بعد." class="ui-help-btn ui-help-btn-sm" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                        </div>
                    </div>

                    <div class="pt-2 space-y-2">
                        @if(isset($officialCreditSales) && $officialCreditSales > 0)
                        <div class="flex justify-between items-center pr-5 ui-status-info">
                            <span class="ui-text-caption font-medium italic">بيع آجل (موثق):</span>
                            <span class="ui-text-caption font-bold">- {{ number_format($officialCreditSales, 2) }} ريال</span>
                        </div>
                        @endif

                        @if(isset($paymentGaps) && $paymentGaps > 0)
                        <div class="flex justify-between items-center pr-5 ui-status-warning">
                            <span class="ui-text-caption font-medium italic">فوارق تحصيل :</span>
                            <span class="ui-text-caption font-bold">- {{ number_format($paymentGaps, 2) }} ريال</span>
                        </div>
                        @endif
                    </div>

	                    @if(($accountantFinanceMovements['collections_total'] ?? 0) > 0)
	                    <div class="pt-2 ui-text-caption ui-text-soft">
	                        <div class="flex items-center gap-2 ui-text-soft">
	                            <span class="w-2 h-2 ui-dot-success rounded-full"></span>
	                            <span class="font-bold">تحصيلات أجل/مديونية:</span>
	                            <button type="button" data-ui-help-title="تحصيلات أجل/مديونية" data-ui-help-body="هذه تفاصيل ما تم تحصيله من عمليات الأجل والمديونية خلال الشفت. الكاش دخل ضمن النقد، والشبكة دخلت ضمن الشبكة، وهذا القسم يوضح مصدر التحصيل للمحاسب فقط." class="ui-help-btn ui-help-btn-sm" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
	                        </div>
	                        <div class="mt-1 flex flex-wrap gap-2 pr-4">
	                            @foreach($dashboardCollectionBadges as $badge)
	                                <span>
	                                    {{ $badge['label'] }}
	                                    @if(($badge['card'] ?? 0) > 0) {{ number_format($badge['card'], 2) }} شبكة@endif
	                                    @if(($badge['cash'] ?? 0) > 0) {{ number_format($badge['cash'], 2) }} كاش@endif
	                                </span>
	                            @endforeach
	                        </div>
	                    </div>
	                    @endif

	                    @if(!empty($accountantFinanceMovements['debt_rows']))
	                    <div class="pt-3 space-y-2">
	                        <div class="flex items-center justify-between gap-2">
	                            <span class="text-sm font-bold ui-status-info">مديونيات سجلها المحاسب</span>
	                            <span class="ui-text-caption ui-text-muted">لا تدخل الحساب إلا عند اختيارها</span>
	                        </div>
	                        @foreach($accountantFinanceMovements['debt_rows'] as $debtRow)
	                        <label class="flex items-start justify-between gap-3 rounded-lg border ui-status-info-border ui-status-info-bg p-2 ui-text-caption ui-status-info">
	                            <span>
	                                <span class="block font-bold">مديونية {{ number_format((float) $debtRow['amount'], 2) }} ريال</span>
	                                <span class="block ui-text-soft">{{ $debtRow['employee_name'] ?? 'موظف' }} — {{ $debtRow['time'] ?? '--' }}</span>
	                            </span>
	                            <span class="inline-flex items-center gap-1 ui-text-caption ui-title">
	                                <input type="checkbox"
	                                       name="include_debt_adjustments[]"
	                                       value="{{ $debtRow['id'] }}"
	                                       @change="if ($event.target.checked) { includedDebtAdjustments.push({ id: {{ (int) $debtRow['id'] }}, amount: {{ (float) $debtRow['amount'] }} }); } else { includedDebtAdjustments = includedDebtAdjustments.filter((item) => item.id !== {{ (int) $debtRow['id'] }}); }"
	                                       class="rounded ui-status-info-border ui-surface-strong-bg ui-status-info ui-focus-info-border">
	                                إضافة للموازنة
	                            </span>
	                        </label>
	                        @endforeach
	                    </div>
	                    @endif

	                    <div class="flex justify-between items-center text-sm">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 ui-dot-danger rounded-full"></span>
                            <span class="ui-text-soft">المصروفات والسحوبات:</span>
                            <button type="button" data-ui-help-title="المصروفات" data-ui-help-body="المصاريف التي قام المحاسب بدفعها أو تسديدها من إجمالي الكاش اليومي، وتعرض منفصلة عن السحوبات حتى يعرف المحاسب سبب خروج المبلغ." class="ui-help-btn ui-help-btn-sm" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                        </div>
                        <span class="ui-status-danger font-medium">- {{ number_format($currentShiftExpenses + $currentShiftWithdrawals, 2) }} ريال</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 ui-text-caption">
                        <div class="flex justify-between rounded-lg ui-status-danger-bg border ui-status-danger-border px-3 py-2">
                            <span class="ui-status-danger">المصاريف</span>
                            <span class="ui-status-danger font-bold">{{ number_format($currentShiftExpenses, 2) }} ريال</span>
                        </div>
                        <div class="flex justify-between rounded-lg ui-status-warning-bg border ui-status-warning-border px-3 py-2">
                            <span class="ui-status-warning">السحوبات</span>
                            <span class="ui-status-warning font-bold">{{ number_format($currentShiftWithdrawals, 2) }} ريال</span>
                        </div>
                    </div>

                    <div class="pt-2 "></div>

	                    <div class="flex justify-between items-center pt-1">
	                        <span class="ui-status-info font-bold">صافي الكاش المتوقع بالدرج:</span>
	                        <span class="ui-title font-black text-xl">
	                            <span x-text="Number(expectedCash).toFixed(2)"></span> ريال
	                        </span>
	                    </div>
                </div>

                {{-- تنبيه العجز أو الزيادة المحسن --}}
                <template x-if="!isBalanced">
                    <div :class="isShortage ? 'ui-status-danger-bg ui-status-danger-border' : 'ui-status-success-bg ui-status-success-border'"
                         class="p-4 rounded-lg border">

                        {{-- رأس التنبيه --}}
                        <div class="flex justify-between items-center mb-2">
                            <div class="flex items-center gap-2">
                                <span :class="isShortage ? 'ui-status-danger' : 'ui-status-success'" class="text-lg">
                                    <i :class="isShortage ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-circle-exclamation'"></i>
                                </span>
                                <span class="text-sm font-bold ui-title" x-text="isShortage ? '⚠️ يوجد عجز في الصندوق' : '💰 توجد زيادة في الصندوق'"></span>
                            </div>
                            <span class="text-2xl font-black" :class="isShortage ? 'ui-status-danger' : 'ui-status-success'" x-text="Math.abs(difference).toFixed(2) + ' ريال'"></span>
                        </div>

                        {{-- تفاصيل الفرق --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3">
                            <div class="ui-frame-row">
                                <span class="ui-text-soft">المتوقع:</span>
                                <span class="ui-title font-bold mr-2" x-text="Number(expectedCash).toFixed(2) + ' ريال'"></span>
                            </div>
                            <div class="ui-frame-row">
                                <span class="ui-text-soft">الفعلي:</span>
                                <span class="ui-title font-bold mr-2" x-text="Number(actualCash).toFixed(2) + ' ريال'"></span>
                            </div>
                        </div>

                        {{-- رسالة توضيحية --}}
                        <p class="ui-text-caption ui-text-soft mt-3 ui-surface-strong-bg p-2 rounded-lg">
                            <i class="fa-solid fa-circle-info ml-1 ui-status-info"></i>
                            <span x-text="isShortage ?
                                'هذا العجز سيتم تسجيله في النظام' :
                                'هذه الزيادة سيتم تسجيلها في النظام'">
                            </span>
                        </p>
                    </div>
                </template>

                {{-- تنبيه المطابقة (عند عدم وجود فرق) --}}
                <template x-if="isBalanced">
                    <div class="ui-status-success-bg border ui-status-success-border p-4 rounded-lg">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <span class="ui-status-success text-lg">✅</span>
                                <span class="text-sm font-bold ui-title">الصندوق متطابق تماماً</span>
                            </div>
                            <span class="ui-status-success font-bold">0.00 ريال</span>
                        </div>
                    </div>
                </template>

                {{-- نموذج الإقفال --}}
	                {{-- البيانات التالية تضبط رسالة التأكيد فقط؛ أسماء الحقول ومسار الإقفال لم تتغير. --}}
	                <form action="{{ route('accountant.balance.store') }}" method="POST" class="space-y-4" data-accountant-balance-form data-active-shift-gap-date="{{ $activeShiftGapBusinessDate ?? '' }}">
	                    @csrf
	                    <template x-for="item in includedDebtAdjustments" :key="'debt-adjustment-' + item.id">
	                        <input type="hidden" name="include_debt_adjustments[]" :value="item.id">
	                    </template>

	                    <div>
                        <label class="ui-text-soft ui-text-caption mb-2 block text-center">أدخل المبلغ النقدي الفعلي الموجود معك الآن:</label>
                        <input type="number" step="0.01" name="actual_cash" required autofocus
                            x-model="actualCash"
                            class="w-full ui-surface-strong-bg border-2 ui-status-info-border rounded-xl px-4 py-4 ui-title text-2xl text-center ui-focus-info-border outline-none transition shadow-inner">
                        <p class="ui-text-caption ui-text-muted text-center mt-2">
                            <i class="fa-solid fa-circle-info ml-1"></i>
                            {{-- توضيح: المدخل المطلوب هو نقد الدرج فقط؛ الشبكة محسوبة آليًا لذلك وضعناها كتولتيب. --}}
                            أدخل النقد فقط <button type="button" data-ui-help-title="المبلغ النقدي الفعلي" data-ui-help-body="هذا المبلغ يمثل النقد (الدرج) فقط، مبيعات الشبكة تُحسب تلقائياً." class="ui-help-btn ui-help-btn-sm align-middle" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                        </p>
                    </div>

                    <div>
                        <label class="ui-text-soft ui-text-caption mb-1 block">ملاحظات (اختياري):</label>
                        <textarea name="notes" rows="2" x-model="notes"
                            class="w-full ui-card px-3 py-2 ui-title text-sm outline-none ui-focus-info-border"
                            placeholder="اكتب أي ملاحظة عن العجز أو الزيادة هنا..."></textarea>
                    </div>

                    @if($canChooseNextShiftBusinessDate)
                    {{-- توضيح: هذا القسم يظهر فقط عند إغلاق الشفت الأول في متجر متعدد الشفتات ليختار المحاسب فتح الشفت الثاني أو إنهاء اليوم. --}}
                    <div class="ui-card-muted p-4 space-y-3">
                        <p class="ui-title text-sm font-bold">اختر ما سيحدث بعد إغلاق الشفت الأول</p>
                        <label class="flex items-start gap-2 ui-text-caption ui-text-soft cursor-pointer">
                            <input type="radio" name="next_shift_decision" value="same_business_date" checked class="mt-1">
                            <span>تفعيل الشفت الثاني لنفس تاريخ {{ $currentBusinessDate }}</span>
                        </label>
                        <label class="flex items-start gap-2 ui-text-caption ui-text-soft cursor-pointer">
                            <input type="radio" name="next_shift_decision" value="next_business_date" class="mt-1">
                            {{-- توضيح: نستخدم يوم العمل بدل اليوم لتجنب اللبس في قرار الشفت التالي. --}}
                            <span>إنهاء يوم العمل والانتقال إلى تاريخ {{ $nextBusinessDateAfterCurrent }}. سيحتاج الرجوع للشفت الثاني لاحقًا إلى المدير.</span>
                        </label>
                    </div>
                    @endif

                    <div class="ui-modal-actions sticky bottom-0 flex gap-3">
                        <button type="button" @click="openConfirm = false"
                            class="ui-btn ui-btn-danger flex-1 py-3">
                            إلغاء
                        </button>
                        <button type="submit"
                            class="ui-btn ui-btn-primary flex-1 py-3">
                            {{ !empty($activeShiftGapBusinessDate) ? 'إصدار موازنة اليوم المرجع' : 'تأكيد الإقفال' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


{{-- التنبيهات والإشعارات --}}
@if($lowStockProductsCount > 0 || $pendingCreditCount > 0 || $lastBalanceTime != 'بداية اليوم' || $requiresSecondShiftConfirmation)
<div class="mt-6 ui-card p-6 shadow-lg">
    <h2 class="text-xl font-bold ui-title mb-6 flex items-center gap-2">
        <span class="ui-status-warning">⚠️</span>
        التنبيهات والإشعارات
    </h2>

    <div class="grid grid-cols-1 gap-4">
        @if($requiresSecondShiftConfirmation)
        <div class="ui-status-info-bg border ui-status-info-border rounded-lg p-4">
            <div class="flex items-start gap-3">
                <div class="ui-status-info-bg ui-status-info p-2 rounded-lg">
                    <i class="fa-solid fa-clock-rotate-left text-xl"></i>
                </div>
                <div>
                    <p class="ui-title font-bold text-sm">الشفت الحالي محسوب كشفت رقم {{ $currentShiftNumber }} لتاريخ {{ $currentBusinessDate }}</p>
                    <p class="ui-text-soft ui-text-caption mt-1">هذا المتجر يسمح بأكثر من شفت في نفس التاريخ. في الخطوة القادمة سيظهر تأكيد صريح قبل اعتماد الشفت الثاني أو الانتقال لليوم التالي.</p>
                </div>
            </div>
        </div>
        @endif

        @if($paymentGaps > 0)
        <div class="ui-status-danger-bg border ui-status-danger-border rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="ui-status-danger-bg ui-status-danger p-2 rounded-lg">
                        <i class="fa-solid fa-file-circle-exclamation text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold ui-title text-sm">فوارق فواتير معلقة</h3>
                        <p class="ui-text-caption ui-text-soft">هذه المبالغ لم تُدفع ولم تُسجل كآجل على موظف</p>
                    </div>
                </div>

                <div class="text-left">
                    <span class="block text-xl font-black ui-status-danger">{{ number_format($paymentGaps, 2) }} <small class="ui-text-caption">ريال</small></span>
                    <span class="ui-text-caption ui-text-muted">مسجلة عـلى: </span>
                    <span class="ui-text-caption ui-status-info font-bold">{{ $accountant->name }}</span>
                </div>
            </div>
        </div>
        @endif

        {{-- تنبيه المبالغ المعلقة --}}
        @if($pendingCreditCount > 0)
        <div class="ui-status-warning-bg border ui-status-warning-border rounded-lg p-4 transition ui-hover-warning-bg">
            <div class="flex items-start justify-between">
                <div class="flex items-start gap-3">
                    <div class="ui-status-warning-bg ui-status-warning p-2 rounded-lg">
                        <i class="fa-solid fa-hand-holding-dollar text-lg"></i>
                    </div>
                    <div>
                        <h3 class="font-bold ui-title mb-1">مبالغ بانتظار السداد</h3>

                        {{-- تفصيل المبالغ --}}
                        <div class="flex flex-wrap gap-x-4 gap-y-1 mb-2">
                            <div class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 ui-dot-info rounded-full"></span>
                                <span class="ui-text-caption ui-text-soft">بيع آجل:</span>
                                <span class="ui-text-caption ui-title font-bold">{{ number_format($officialCreditSales, 2) }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 ui-dot-danger rounded-full"></span>
                                <span class="ui-text-caption ui-text-soft">فوارق فواتير:</span>
                                <span class="ui-text-caption ui-title font-bold">{{ number_format($paymentGaps, 2) }}</span>
                            </div>
                        </div>

                        <p class="ui-text-soft text-sm">
                            إجمالي <span class="font-bold ui-status-warning">{{ $pendingCreditCount }}</span> فواتير بقيمة
                            <span class="font-bold ui-status-warning">{{ number_format($pendingCreditTotal, 2) }}</span> ريال
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <a href="{{ route('accountant.pos.collection.page') }}" class="ui-btn ui-btn-secondary px-3 py-2 text-sm">
                        <span>مراجعة</span>
                        <i class="fa-solid fa-chevron-left ui-text-caption"></i>
                    </a>
                </div>
            </div>
        </div>
        @endif

        {{-- تنبيه المخزون المنخفض --}}
        @if($lowStockProductsCount > 0)
        <div class="ui-status-danger-bg border ui-status-danger-border rounded-lg p-4">
            <div class="flex items-start justify-between">
                <div class="flex items-start gap-3">
                    <div class="ui-status-danger-bg ui-status-danger p-2 rounded-lg">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                    <div>
                        <h3 class="font-bold ui-title mb-1">تنبيه المخزون</h3>
                        <p class="ui-text-soft text-sm">هناك {{ $lowStockProductsCount }} منتجات وصلت للحد الأدنى</p>
                    </div>
                </div>
                <a href="{{ route('accountant.pos.searchProduct') }}" class="ui-status-danger ui-hover-title ui-text-caption">عرض المنتجات</a>
            </div>
        </div>
        @endif

        {{-- معلومات آخر إقفال --}}
        @if($lastBalanceTime != 'بداية اليوم')
        <div class="ui-status-info-bg border ui-status-info-border rounded-lg p-4">
            <div class="flex items-center gap-3">
                <div class="ui-status-info-bg ui-status-info p-2 rounded-lg ui-text-caption">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div class="flex flex-1 justify-between items-center">
                    <span class="ui-text-soft text-sm">آخر إقفال يدوي تم بواسطة <strong>{{ $lastBalanceAccountant }}</strong></span>
                    <span class="ui-status-info font-mono ui-text-caption">{{ $lastBalanceTime }}</span>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>
@endif

{{-- جدول آخر العمليات --}}
<div class="mt-6 ui-card p-6 shadow-lg">
    <h2 class="text-xl font-bold ui-title mb-6 flex items-center gap-2">
        <span class="ui-status-warning">🕘</span>
        آخر العمليات
        {{-- سجل مستقل عن الشفت/اليوم المرجع؛ ترتيبه حسب التاريخ ثم وقت الإدخال داخل نفس اليوم. --}}
        <button type="button" data-ui-help-title="آخر العمليات" data-ui-help-body="يعرض آخر 10 عمليات على المتجر حسب التاريخ، ولا يتأثر بتفعيل يوم مرجع أو الشفت المفتوح." class="ui-help-btn" aria-label="توضيح"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
    </h2>

    <div class="overflow-x-auto">
        <table class="ui-table ui-responsive-table w-full text-right text-sm">
            <thead class="ui-table-head">
                <tr>
                    <th class="p-3 ui-text-soft font-medium text-sm">الوقت</th>
                    <th class="p-3 ui-text-soft font-medium text-sm">النوع</th>
                    <th class="p-3 ui-text-soft font-medium text-sm">الموظف</th>
                    <th class="p-3 ui-text-soft font-medium text-sm">الوصف</th>
                    <th class="p-3 ui-text-soft font-medium text-sm text-left">المبلغ</th>
                </tr>
            </thead>
            <tbody class="ui-divide-border">
                @forelse($lastOperations as $op)
                <tr class="ui-hover-surface transition">
                    <td class="p-3" data-label="الوقت">
                        <div class="ui-text-soft text-sm">{{ $op->formatted_time ?? $op->created_at->format('h:i A') }}</div>
                        <div class="ui-text-muted ui-text-caption">{{ $op->formatted_date ?? $op->created_at->format('Y-m-d') }}</div>
                    </td>
                    <td class="p-3" data-label="النوع">
                        @if($op->type == 'sale')
                            <span class="px-3 py-1 ui-status-success-bg ui-status-success ui-text-caption rounded-full">بيع</span>
                        @elseif($op->type == 'expense')
                            <span class="px-3 py-1 ui-status-danger-bg ui-status-danger ui-text-caption rounded-full">مصروف</span>
                        @elseif($op->type == 'withdrawal')
                            <span class="px-3 py-1 ui-status-warning-bg ui-status-warning ui-text-caption rounded-full">سحب</span>
                        @elseif($op->type == 'collection')
                            <span class="px-3 py-1 ui-status-info-bg ui-status-info ui-text-caption rounded-full">تحصيل</span>
                        @endif
                    </td>
                    <td class="p-3" data-label="الموظف">
                        <div class="ui-text-soft text-sm">{{ $op->employee }}</div>
                    </td>
                    <td class="p-3" data-label="الوصف">
                        <div class="ui-text-soft text-sm truncate max-w-[150px]" aria-label="{{ $op->description }}">
                            {{ $op->description }}
                        </div>
                    </td>
                    <td class="p-3 text-left" data-label="المبلغ">
                        <div class="font-bold {{ in_array($op->type, ['expense', 'withdrawal']) ? 'ui-status-danger' : 'ui-status-success' }}">
                            {{ number_format($op->amount, 2) }} <span class="ui-text-caption ui-text-soft">ريال</span>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="p-8 text-center">
                        <div class="ui-text-muted text-sm">لا توجد عمليات مسجلة</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>

@if(session('wa_url'))
{{-- تمرير آمن للرابط إلى وحدة إشعار الإقفال بدل تضمين سكربت خاص بالجلسة. --}}
<div class="hidden" data-accountant-whatsapp-url="{{ session('wa_url') }}" aria-hidden="true"></div>
@endif

@endsection
