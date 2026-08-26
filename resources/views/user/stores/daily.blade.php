@extends('dashboard.app')
@section('title', 'المبيعات - ' . $store->name)
@section('content')
<div class="daily-sales-page max-w-7xl mx-auto px-4 py-6 text-right" dir="rtl">

    {{-- رسائل أخطاء تعديل العمليات تظهر داخل الصفحة حتى لا يبدو أن الإجراء لم ينفذ. --}}
    @if($errors->any() && !session('edit_sale_modal'))
        <div class="mb-4 rounded-xl border ui-border ui-status-danger-bg px-4 py-3 text-sm ui-status-danger">
            <div class="font-bold mb-1"><i class="fa-solid fa-circle-exclamation ml-1"></i>تعذر تنفيذ التعديل</div>
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ===== شريط العنوان والبحث المتقدم ===== --}}
    <div class="mb-6 ui-surface-muted-bg p-4 rounded-2xl border ui-border shadow-xl">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold ui-title flex items-center gap-2">
                    <i class="fas fa-chart-line ui-status-success"></i>
                    @if(request('date') || request('search'))
                        نتائج البحث
                    @else
                        مبيعات الشفت اليومية
                    @endif
                </h1>
                <p class="ui-text-muted text-sm mt-1">{{ $store->name }}</p>
            </div>

            <form id="daily-sales-filter-form" method="GET" action="{{ route('user.stores.daily', $store->id) }}" class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                <div class="relative">
                    <input id="daily-sales-date-input" type="date" name="date" value="{{ request('date', \Carbon\Carbon::today()->format('Y-m-d')) }}"
                           class="ui-card py-2.5 px-4 text-sm ui-title w-full sm:w-auto"
                           max="{{ \Carbon\Carbon::today()->format('Y-m-d') }}">
                </div>

                <div class="relative flex-grow">
                    <input id="daily-sales-search-input" type="text" name="search" value="{{ request('search') }}"
                           placeholder="🔍 بحث برقم العملية أو اسم المنتج..."
                           class="ui-card py-2.5 px-4 pr-10 text-sm ui-title w-full min-w-[250px]">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 ui-text-muted"></i>
                </div>

                <button type="submit" class="hidden">بحث</button>

                @if(request('search') || request('date'))
                    <a href="{{ route('user.stores.daily', $store->id) }}"
                       class="ui-surface-muted-bg ui-title px-4 py-2.5 rounded-xl transition flex items-center gap-2 justify-center">
                        <i class="fas fa-times"></i>
                        <span>إلغاء</span>
                    </a>
                @endif
            </form>
        </div>

        <div class="mt-3 text-sm ui-text-muted ui-surface-muted-bg border ui-border p-2 rounded-lg flex flex-col gap-1">
            <div class="ui-text-caption ui-status-success-bg border ui-border rounded-md px-2 py-1">
                ✅ عرض التقرير يعتمد على الفترة المحددة (اليوم أو التاريخ المختار).
            </div>
            <div>
                <i class="fas fa-clock ml-1 ui-status-info"></i>
                فترة التقرير:
                <span class="ui-text-muted">{{ $startTime->format('Y-m-d h:i A') }}</span>
                <span class="mx-1">→</span>
                <span class="ui-text-muted">{{ $endTime->format('Y-m-d h:i A') }}</span>
                @if($selectedShift)
                    <span class="mr-2 ui-text-caption ui-status-success">(حسب الفترة المعتمدة)</span>
                    <span class="mr-2 ui-text-caption ui-status-info">عدد الفترات المعروضة: {{ $stats['shift_count'] }}</span>
                @else
                    <span class="mr-2 ui-text-caption ui-status-warning">(تم اعتماد الفترة اليومية المحددة)</span>
                @endif
            </div>
            @if(request('search') || request('date'))
            <div>
                <i class="fas fa-filter ml-1 ui-status-success"></i>
                @if(request('date'))
                    <span class="ml-3">📅 التاريخ: {{ request('date') }}</span>
                @endif
                @if(request('search'))
                    <span>🔍 البحث: "{{ request('search') }}"</span>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- ===== كروت الإحصائيات السريعة (نسخة مصغرة) ===== --}}
    @php
        $collectedTotal = (float) ($stats['collected_total'] ?? $stats['total'] ?? 0);
        $hasCostSummary = ((float) ($stats['total_cost'] ?? 0) != 0.0)
            || ((float) ($stats['total'] ?? 0) != 0.0)
            || ((float) ($stats['total_profit'] ?? 0) != 0.0);
        $hasFinanceSummary = ((float) ($stats['expenses'] ?? 0) != 0.0)
            || ((float) ($stats['withdrawals'] ?? 0) != 0.0)
            || ((float) ($stats['debts'] ?? 0) != 0.0)
            || ((float) ($stats['absences'] ?? 0) != 0.0);
        $hasCollectionsBreakdown = ((float) ($stats['credit_collections'] ?? 0) > 0)
            || ((float) ($stats['debt_collections'] ?? 0) > 0);
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-2 mb-4 ui-text-caption">
        @if($collectedTotal > 0)
        <div class="ui-surface-muted-bg p-2 rounded-lg border ui-border">
            <p class="ui-text-muted">إجمالي التحصيل (الفترة)</p>
            <p class="ui-status-success font-bold">{{ number_format($collectedTotal, 2) }} ر.س</p>
            <p class="ui-text-caption ui-text-muted">مجموع المبيعات والتحصيلات المحصلة</p>
            @if($hasCollectionsBreakdown)
                @if(($stats['credit_collections'] ?? 0) > 0)
                    <p class="ui-text-muted font-bold">تحصيلات آجل: {{ number_format($stats['credit_collections'], 2) }} ر.س</p>
                    <p class="ui-text-caption ui-text-muted">كاش {{ number_format($stats['credit_collections_cash'] ?? 0, 2) }} / شبكة {{ number_format($stats['credit_collections_card'] ?? 0, 2) }}</p>
                @endif
                @if(($stats['debt_collections'] ?? 0) > 0)
                    <p class="ui-text-muted font-bold">تحصيلات مديونية: {{ number_format($stats['debt_collections'], 2) }} ر.س</p>
                    <p class="ui-text-caption ui-text-muted">كاش {{ number_format($stats['debt_collections_cash'] ?? 0, 2) }} / شبكة {{ number_format($stats['debt_collections_card'] ?? 0, 2) }}</p>
                @endif
            @endif
        </div>
        @endif
        @if($hasCostSummary)
        <div class="ui-surface-muted-bg p-2 rounded-lg border ui-border">
            <p class="ui-text-muted">التكلفة / إجمالي المستلم</p>
            @if(($stats['total_cost'] ?? 0) != 0)
                <p class="ui-status-warning font-bold">التكلفة: {{ number_format($stats['total_cost'], 2) }}</p>
            @endif
            @if(($stats['total'] ?? 0) != 0)
                <p class="ui-status-success font-bold">إجمالي المستلم: {{ number_format($stats['total'], 2) }}</p>
            @endif
            @if(($stats['total_profit'] ?? 0) != 0)
                <p class="ui-status-info font-bold">ربح: {{ number_format($stats['total_profit'], 2) }}</p>
            @endif
        </div>
        @endif
        <div class="ui-surface-muted-bg p-2 rounded-lg border ui-border">
            <p class="ui-text-muted">المحصل كاش / شبكة</p>
            <p class="ui-status-success font-bold">كاش: {{ number_format($stats['cash_sales'], 2) }}</p>
            <p class="ui-status-info font-bold">شبكة: {{ number_format($stats['card_sales'], 2) }}</p>
        </div>
        @if($hasFinanceSummary)
        <div class="ui-surface-muted-bg p-2 rounded-lg border ui-border">
            <p class="ui-text-muted">حركات مالية مرتبطة</p>
            @if(($stats['expenses'] ?? 0) != 0)
                <p class="ui-status-danger font-bold">مصروفات: {{ number_format($stats['expenses'], 2) }}</p>
            @endif
            @if(($stats['withdrawals'] ?? 0) != 0)
                <p class="ui-status-warning font-bold">سحوبات: {{ number_format($stats['withdrawals'], 2) }}</p>
            @endif
            @if(($stats['debts'] ?? 0) != 0)
                <p class="ui-text-muted font-bold">مديونيات: {{ number_format($stats['debts'], 2) }}</p>
            @endif
            @if(($stats['absences'] ?? 0) != 0)
                <p class="ui-status-info font-bold">غيابات: {{ number_format($stats['absences']) }}</p>
            @endif
        </div>
        @endif
        @if(($stats['shift_count'] ?? 0) >= 2)
        <div class="ui-surface-muted-bg p-2 rounded-lg border ui-border">
            <p class="ui-text-muted">عدد الفترات المعروضة</p>
            <p class="ui-status-info font-bold">{{ number_format($stats['shift_count']) }}</p>
        </div>
        @endif
    </div>

    @if(($stats['deferred_profit'] ?? 0) > 0)
    <div class="mb-3 ui-text-caption ui-status-warning-bg border ui-border rounded-lg p-2">
        ⚠️ يوجد ربح مؤجل غير محتسب داخل هذه الصفحة بقيمة {{ number_format($stats['deferred_profit'], 2) }} ر.س حتى يكتمل تحصيل العمليات الآجلة.
    </div>
    @endif

    @if(($shiftSummaries ?? collect())->count() > 0)
    <div class="mb-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2">
        @foreach($shiftSummaries as $shift)
        <div class="ui-surface-muted-bg border ui-border rounded-lg p-3 ui-text-caption">
            @php
                $isClosedShift = \Illuminate\Support\Str::startsWith((string) ($shift['key'] ?? ''), 'shift_');
            @endphp
            <div class="flex justify-between items-center mb-2">
                <div class="flex flex-col">
                    <span class="ui-title font-bold">{{ $shift['label'] }}</span>
                    @if($isClosedShift)
                        <span class="ui-text-caption ui-text-muted tracking-wider uppercase">ref: shf-{{ str_replace('shift_', '', (string) $shift['key']) }}</span>
                    @endif
                </div>
                @if(empty($shift['hide_period']))
                    <span class="ui-text-muted">{{ $shift['start']->format('h:i A') }} → {{ $shift['end']->format('h:i A') }}</span>
                @else
                    <span class="ui-text-muted">تاريخ مرجع</span>
                @endif
            </div>
            {{-- ملاحظة توضيحية: نعرض ملاحظة الإغلاق فقط إذا كانت موجودة فعليًا في DailyBalance. --}}
            @if(!empty($shift['notes']))
            <div class="mb-2 ui-text-caption ui-status-warning-bg border ui-border rounded-md px-2 py-1">
                <span class="ui-status-warning font-semibold">ملاحظة الإغلاق:</span>
                <span>{{ $shift['notes'] }}</span>
            </div>
            @endif
            <div class="grid grid-cols-2 gap-1">
                <span class="ui-text-muted">إجمالي المستلم:</span><span class="ui-status-success font-bold">{{ number_format($shift['stats']['total'], 2) }}</span>
                <span class="ui-text-muted">التكلفة:</span><span class="ui-status-warning font-bold">{{ number_format($shift['stats']['total_cost'], 2) }}</span>
                <span class="ui-text-muted">ربح محتسب:</span><span class="ui-status-info font-bold">{{ number_format($shift['stats']['total_profit'], 2) }}</span>
                <div class="col-span-2 grid grid-cols-2 rounded-lg ui-status-success-bg px-2 py-1.5">
                    <span class="ui-status-success font-bold">كاش المبيعات:</span><span class="ui-status-success font-black">{{ number_format($shift['stats']['cash_sales'], 2) }}</span>
                </div>
                <div class="col-span-2 grid grid-cols-2 rounded-lg ui-status-info-bg px-2 py-1.5">
                    <span class="ui-status-info font-bold">شبكة المبيعات:</span><span class="ui-status-info font-black">{{ number_format($shift['stats']['card_sales'], 2) }}</span>
                </div>
                @if(($shift['stats']['credit_operations_count'] ?? 0) > 0)
                <span class="ui-text-muted">عمليات أجل:</span><span class="ui-text-muted font-bold">{{ number_format($shift['stats']['credit_operations_count'] ?? 0) }} عملية</span>
                @endif
                @if(($shift['stats']['credit_collections'] ?? 0) > 0)
                <span class="ui-text-muted">تحصيلات الآجل:</span><span class="ui-status-warning font-bold">{{ number_format($shift['stats']['credit_collections'] ?? 0, 2) }} <span class="ui-text-caption ui-text-muted">كاش {{ number_format($shift['stats']['credit_collections_cash'] ?? 0, 2) }} / شبكة {{ number_format($shift['stats']['credit_collections_card'] ?? 0, 2) }}</span></span>
                @endif
                @if(($shift['stats']['debt_collections'] ?? 0) > 0)
                <span class="ui-text-muted">تحصيلات المديونية:</span><span class="ui-status-success font-bold">{{ number_format($shift['stats']['debt_collections'] ?? 0, 2) }} <span class="ui-text-caption ui-text-muted">كاش {{ number_format($shift['stats']['debt_collections_cash'] ?? 0, 2) }} / شبكة {{ number_format($shift['stats']['debt_collections_card'] ?? 0, 2) }}</span></span>
                @endif
                @if(($shift['stats']['tadlil_count'] ?? 0) > 0)
                <span class="ui-text-muted">سجل التضليل (خصم المنتجات):</span><span class="ui-status-info font-bold">{{ number_format($shift['stats']['tadlil_count'] ?? 0) }} عملية</span>
                @endif
                @if(($shift['stats']['tadlil_total'] ?? 0) > 0)
                <span class="ui-text-muted">إجمالي التضليل (خصم المنتجات):</span><span class="ui-status-success font-bold">{{ number_format($shift['stats']['tadlil_total'] ?? 0, 2) }} ر.س</span>
                @endif
                @if(($shift['stats']['expenses'] ?? 0) > 0)
                <span class="ui-text-muted">المصروفات:</span><span class="ui-status-danger font-bold">{{ number_format($shift['stats']['expenses'], 2) }}</span>
                @endif
                @if(($shift['stats']['withdrawals'] ?? 0) > 0)
                <span class="ui-text-muted">السحوبات:</span><span class="ui-status-warning font-bold">{{ number_format($shift['stats']['withdrawals'], 2) }}</span>
                @endif
                @if(($shift['stats']['debts'] ?? 0) > 0)
                <span class="ui-text-muted">المديونيات:</span><span class="ui-text-muted font-bold">{{ number_format($shift['stats']['debts'] ?? 0, 2) }}</span>
                @endif
                @if(($shift['stats']['absences'] ?? 0) > 0)
                <span class="ui-text-muted">الغيابات:</span><span class="ui-status-info font-bold">{{ number_format($shift['stats']['absences'] ?? 0) }}</span>
                @endif
                <span class="ui-text-muted">عمليات:</span><span class="ui-status-info font-bold">{{ number_format($shift['stats']['count']) }}</span>
                @if(($shift['stats']['deferred_profit'] ?? 0) > 0)
                <span class="ui-text-muted">ربح مؤجل:</span><span class="ui-status-warning font-bold">{{ number_format($shift['stats']['deferred_profit'], 2) }}</span>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- ===== بطاقات العمليات (مقسمة حسب الشفت) ===== --}}
    @php
        $groupedSales = $sales->groupBy('shift_key');
        $displayShiftSummaries = $shiftSummaries ?? collect();
        $financialOperationsCount = 0;
        foreach ($displayShiftSummaries as $summaryForCount) {
            $financialOperationsCount += collect($summaryForCount['financial_operations'] ?? [])->count();
        }
        $hasDailyRows = $sales->count() > 0 || $financialOperationsCount > 0;
    @endphp

    <div class="space-y-6">
        @if(!$hasDailyRows)
        <div class="text-center py-16 ui-surface-muted-bg rounded-2xl border ui-border">
            <i class="fas fa-chart-line text-5xl ui-text-muted mb-4"></i>
            <p class="ui-text-muted text-lg">لا توجد مبيعات</p>
            @if(request('date') || request('search'))
            <a href="{{ route('user.stores.daily', $store->id) }}" class="mt-4 inline-block ui-surface-muted-bg ui-title px-6 py-2 rounded-xl">
                عرض مبيعات اليوم
            </a>
            @endif
        </div>
        @endif

        @foreach($displayShiftSummaries as $shift)
                @php
                    $shiftSales = $groupedSales->get($shift['key'], collect());
                    $shiftFinancialOperations = collect($shift['financial_operations'] ?? []);
                @endphp

                @if($shiftSales->count() > 0 || $shiftFinancialOperations->count() > 0)
                <div class="ui-card p-3">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        @php
                            preg_match('/\d+/', (string) ($shift['label'] ?? ''), $shiftLabelNumberMatch);
                            $shiftNumber = $shiftLabelNumberMatch[0] ?? null;
                        @endphp
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="ui-text-caption font-semibold ui-text-muted border ui-border px-2.5 py-1 rounded-full">
                                {{ $shiftNumber ? 'الشفت رقم ' . $shiftNumber : 'الشفت' }}
                            </span>
                            <div class="flex flex-col">
                                <h3 class="text-sm font-bold ui-title tracking-wide">{{ $shift['label'] }}</h3>
                                @if(\Illuminate\Support\Str::startsWith((string) ($shift['key'] ?? ''), 'shift_'))
                                    <span class="ui-text-caption ui-text-muted tracking-wider uppercase">ref: shf-{{ str_replace('shift_', '', (string) $shift['key']) }}</span>
                                @endif
                            </div>
                        </div>
                        <span class="ui-text-caption ui-text-muted ui-surface-muted-bg border ui-border px-2 py-1 rounded-md">{{ $shift['start']->format('h:i A') }} → {{ $shift['end']->format('h:i A') }}</span>
                    </div>
                    <div class="mb-3 px-2 py-1.5 border ui-border ui-surface-muted-bg rounded-lg ui-text-caption ui-text-muted">
                        <span class="font-semibold">قائمة عمليات {{ $shiftNumber ? 'الشفت رقم ' . $shiftNumber : $shift['label'] }}</span>
                        <span class="ui-text-muted ">({{ number_format($shiftSales->count()) }} عملية)</span>
                    </div>

                    @if($shiftFinancialOperations->isNotEmpty())
                    <div class="mb-3 overflow-hidden rounded-xl border ui-border ui-surface-muted-bg">
                        @foreach($shiftFinancialOperations as $financialOperation)
                        @php
                            $financialRowId = 'financial-' . $financialOperation->route_key . '-' . $financialOperation->id;
                        @endphp
                        <div class="mb-2 last:mb-0 rounded-xl border ui-border ui-surface-muted-bg shadow-inner  overflow-hidden">
                            {{-- عقد عرض فقط لفتح تفاصيل السجل المالي دون لمس بياناته. --}}
                            <button type="button" data-sensitive-action="daily.financial-toggle" data-row-id="{{ $financialRowId }}" class="w-full px-4 py-3 flex flex-wrap items-center justify-between gap-3 text-right ui-surface-muted-bg transition">
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg border {{ $financialOperation->icon_shell_class ?? $financialOperation->badge_class }}">
                                        <i class="{{ $financialOperation->icon_class ?? 'fa-solid fa-file-lines' }} text-[13px]"></i>
                                    </span>
                                    <span class="inline-flex border px-2 py-1 rounded-full ui-text-caption {{ $financialOperation->badge_class }}">{{ $financialOperation->type_label }}</span>
                                    <span class="text-sm font-bold ui-title">{{ $financialOperation->title }}</span>
                                    <span class="ui-text-caption ui-text-muted">{{ $financialOperation->date }}</span>
                                    @if(!empty($financialOperation->payment_breakdown))
                                        <span class="ui-text-caption ui-status-success border ui-border ui-status-success-bg rounded px-2 py-0.5">{{ $financialOperation->payment_breakdown }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="{{ $financialOperation->amount_class ?? 'ui-status-danger' }} font-bold">{{ number_format($financialOperation->amount, 2) }} ر.س</span>
                                    <i class="fas fa-chevron-down ui-text-muted ui-text-caption transition-transform" id="arrow-{{ $financialRowId }}"></i>
                                </div>
                            </button>
                            <div id="details-{{ $financialRowId }}" class="hidden border-t ui-border ui-surface-muted-bg p-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 ui-text-caption">
                                    <div class="rounded-lg border ui-border ui-surface-muted-bg p-3"><span class="ui-text-muted block mb-1">النوع</span><span class="ui-title font-bold">{{ $financialOperation->type_label }}</span></div>
                                    <div class="rounded-lg border ui-border ui-surface-muted-bg p-3"><span class="ui-text-muted block mb-1">المبلغ</span><span class="{{ $financialOperation->amount_class ?? 'ui-status-danger' }} font-bold">{{ number_format($financialOperation->amount, 2) }} ر.س</span></div>
                                    <div class="rounded-lg border ui-border ui-surface-muted-bg p-3"><span class="ui-text-muted block mb-1">التاريخ</span><span class="ui-text-muted font-bold">{{ $financialOperation->date }}</span></div>
                                    @if(!empty($financialOperation->actor_name))
                                        <div class="rounded-lg border ui-border ui-surface-muted-bg p-3"><span class="ui-text-muted block mb-1">من نفذ العملية</span><span class="ui-text-muted font-bold">{{ $financialOperation->actor_name }}</span></div>
                                    @endif
                                    @if(($financialOperation->operation_kind ?? null) === 'debt_collection')
                                        <div class="rounded-lg border ui-border ui-surface-muted-bg p-3"><span class="ui-text-muted block mb-1">نوع الدفع</span><span class="ui-status-success font-bold">{{ $financialOperation->payment_label ?? 'كاش' }}</span></div>
                                        <div class="rounded-lg border ui-border ui-surface-muted-bg p-3"><span class="ui-text-muted block mb-1">توزيع التحصيل</span><span class="ui-text-muted font-bold">{{ number_format($financialOperation->cash_amount ?? 0, 2) }} كاش / {{ number_format($financialOperation->card_amount ?? 0, 2) }} شبكة</span></div>
                                    @endif
                                </div>
                                @if($financialOperation->description)
                                    <p class="mt-3 ui-text-caption ui-text-muted ui-surface-muted-bg border ui-border rounded-lg px-2 py-2">{{ $financialOperation->description }}</p>
                                @endif
                                <div class="mt-3 flex justify-end gap-2">
                                    @if(($financialOperation->route_key ?? null) === 'debt')
                                        <span class="ui-text-caption ui-status-warning-bg border ui-border px-3 py-1.5 rounded-lg">المديونية/تحصيلها لا تُعدل من اليوميات؛ استخدم صفحة المديونية أو التصحيح قبل إغلاق الشفت.</span>
                                    @else
                                        <button type="button" data-sensitive-action="daily.financial-edit" data-operation="{{ json_encode($financialOperation, JSON_HEX_APOS | JSON_HEX_QUOT) }}" class="ui-text-caption ui-status-info-bg ui-status-info border ui-border px-3 py-1.5 rounded-lg transition"><i class="fas fa-pen ml-1"></i> تعديل</button>
                                        <form method="POST" action="{{ route('user.stores.daily.financial.destroy', [$store->id, $financialOperation->route_key, $financialOperation->id]) }}" data-confirm-delete="هل تريد حذف هذا السجل المالي؟">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ui-btn ui-btn-danger px-3 py-2 ui-text-caption"><i class="fas fa-trash ml-1"></i> حذف</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    <div class="space-y-3">
                        @foreach($shiftSales as $sale)
                        @php
                            $netProfit = max(0, (float) ($sale->recognized_profit ?? ((float) ($sale->paid_amount ?? 0) - (float) ($sale->total_cost ?? 0))));
                            $bgColor = $loop->iteration % 2 == 0 ? 'ui-surface-muted-bg' : 'ui-surface-muted-bg';
                            $isCollectionOperation = ($sale->operation_kind ?? null) === 'collection';
                            $productsCost = $sale->items->sum('calculated_cost');
                            $tintOperationName = $sale->tint_operation_name ?? null;
                            $productNames = $sale->items->pluck('display_name')->filter()->values();
                            $shownProductNames = $productNames->take(3)->implode(' - ');
                            $hiddenProductsCount = max($productNames->count() - 3, 0);
                            $visibleProducts = $isCollectionOperation
                                ? ($sale->employee_name ?? 'غير معروف')
                                : trim(collect([$tintOperationName, $shownProductNames])->filter()->implode(' - '));
                            $operationAmount = max((float) ($sale->final_total ?? 0), (float) (($sale->paid_amount ?? 0) + ($sale->remaining_amount ?? 0)));
                            $hasOutstandingCredit = (float) ($sale->remaining_amount ?? 0) > 0;
                            $hasCreditComponent = $sale->sale_type === 'credit' || (int) ($sale->has_partial_credit ?? 0) === 1;
                            $hasPartialCredit = (int) ($sale->has_partial_credit ?? 0) === 1 && $hasOutstandingCredit;
                            $isInternalUse = ($sale->sale_type ?? null) === 'internal_use';
                            $effectiveTimestamp = ($sale->updated_at && $sale->updated_at->ne($sale->created_at)) ? $sale->updated_at : $sale->created_at;
                            $accountingDate = \Carbon\Carbon::parse($sale->business_date ?? $sale->display_time ?? $sale->created_at)->format('Y-m-d');
                            $profitDisplay = number_format(max(0, (float) $netProfit), 2);
                            $recognizedCost = (float) ($sale->recognized_cost ?? 0);
                            $uncoveredCost = (float) ($sale->uncovered_cost ?? 0);
                            $deferredProfit = (float) ($sale->deferred_profit ?? 0);
                            $paymentBadgeColor = match($sale->payment_label) {
                                'نقداً', 'نقداً + آجل' => 'green',
                                'بطاقة', 'بطاقة + آجل' => 'blue',
                                'ميكس', 'ميكس + آجل' => 'purple',
                                'تم التحصيل', 'تحصيل', 'تحصيل كاش', 'تحصيل شبكة', 'تحصيل ميكس' => 'emerald',
                                default => 'yellow',
                            };
                            $wasEdited = $sale->updated_at && $sale->updated_at->ne($sale->created_at);
                            $shouldShowFinancialSummary = !$isInternalUse
                                && ($wasEdited || $hasCreditComponent || $sale->sale_type === 'mixed' || $isCollectionOperation);
                            $showSummaryTotal = ($isCollectionOperation ? (float) $sale->paid_amount : $operationAmount) > 0;
                            $showSummaryCollected = ($isCollectionOperation ? $recognizedCost : (float) $sale->paid_amount) > 0;
                            $showSummaryRemaining = ($isCollectionOperation ? $deferredProfit : (float) ($sale->remaining_amount ?? 0)) > 0;
                            $showSummaryRecognizedProfit = ($isCollectionOperation || $hasPartialCredit)
                                && (float) ($sale->recognized_profit ?? 0) > 0;
                            $showSummaryDeferredProfit = !$isCollectionOperation && $deferredProfit > 0;
                            $financialSummaryCardsCount = collect([
                                $showSummaryTotal,
                                $showSummaryCollected,
                                $showSummaryRemaining,
                                $showSummaryRecognizedProfit,
                                $showSummaryDeferredProfit,
                            ])->filter()->count();
                            $financialSummaryGridClass = match ($financialSummaryCardsCount) {
                                1 => 'grid-cols-1',
                                2 => 'grid-cols-1 sm:grid-cols-2',
                                3 => 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-3',
                                default => 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-4',
                            };
                            $fullOperationProfit = $operationAmount - (float) ($sale->total_cost ?? 0);
                            $creditEmployeeName = optional($sale->employee)->name ?? ($sale->employee_name ?? null);
                            $creditTypeLabel = $sale->sale_type === 'credit' ? 'أجل كامل' : 'أجل جزئي';
                            $visibleNote = $hasCreditComponent ? ($sale->description ?? null) : ($sale->description ?? null);
                        @endphp
                        <div class="{{ $bgColor }} rounded-xl border ui-border transition-all hover:shadow-lg">
                            <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-3 cursor-pointer" data-sensitive-action="daily.sale-toggle" data-sale-id="{{ $sale->id }}">
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span class="ui-title font-bold ui-surface-muted-bg w-8 h-8 rounded-lg flex items-center justify-center text-sm">#{{ $loop->iteration }}</span>
                                    <span class="px-2 py-1 rounded-full ui-text-caption {{ $isCollectionOperation ? 'ui-status-success-bg ui-status-success border ui-border' : ($sale->items->isNotEmpty() ? 'ui-status-info-bg ui-status-info' : 'ui-status-warning-bg ui-status-warning ui-border') }}">
                                        {{ $isCollectionOperation ? 'تحصيل آجل' : ($isInternalUse ? 'استهلاك' : ($tintOperationName ? 'تضليل' : ($sale->items->isNotEmpty() ? 'منتجات' : 'شغل يد'))) }}
                                    </span>
                                    @if(!$isCollectionOperation && $hasCreditComponent)
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg border ui-border ui-surface-muted-bg ui-text-muted shadow-inner " title="{{ $creditTypeLabel }}">
                                            <i class="fa-solid fa-file-invoice-dollar ui-text-caption"></i>
                                        </span>
                                    @endif
                                    @if($visibleProducts)
                                        <div class="flex flex-col gap-0.5">
                                            <span class="ui-text-caption daily-operation-name {{ $tintOperationName ? 'daily-operation-name--tint' : 'daily-operation-name--product' }}">{{ $visibleProducts }}@if(!$isCollectionOperation && $hiddenProductsCount > 0) <span class="ui-status-info font-bold">+{{ $hiddenProductsCount }}</span>@endif</span>
                                            <span class="ui-text-caption ui-text-muted">{{ $accountingDate }}</span>
                                            @if(!empty($visibleNote))
                                                <span class="ui-text-caption ui-text-muted ui-surface-muted-bg border ui-border rounded px-2 py-0.5 max-w-[260px] truncate" title="{{ $visibleNote }}">{{ $visibleNote }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="flex flex-col gap-0.5">
                                            <span class="ui-text-caption ui-text-muted">{{ $accountingDate }}</span>
                                            @if(!empty($visibleNote))
                                                <span class="ui-text-caption ui-text-muted ui-surface-muted-bg border ui-border rounded px-2 py-0.5 max-w-[260px] truncate" title="{{ $visibleNote }}">{{ $visibleNote }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <div class="flex items-center gap-4 flex-wrap">
                                    @if(!$isInternalUse)
                                    <span class="ui-text-muted text-sm">
                                        {{ $hasCreditComponent && $hasOutstandingCredit ? 'القيمة الأساسية:' : 'المستلم:' }}
                                        <span class="{{ $hasCreditComponent && $hasOutstandingCredit ? 'ui-status-warning' : 'ui-status-success' }} font-bold">{{ number_format($hasCreditComponent && $hasOutstandingCredit ? $operationAmount : $sale->paid_amount, 2) }}</span>
                                    </span>
                                    @endif
                                    @if($isCollectionOperation)
                                        @if($recognizedCost > 0)
                                            <span class="ui-text-muted text-sm">غطى تكلفة: <span class="ui-status-success font-bold">{{ number_format($recognizedCost, 2) }}</span></span>
                                        @endif
                                        @if((float) $netProfit > 0)
                                            <span class="ui-text-muted text-sm">ربح التحصيل: <span class="ui-status-info font-bold">{{ $profitDisplay }}</span></span>
                                        @endif
                                        @if($deferredProfit > 0)
                                            <span class="ui-text-muted text-sm">ربح مؤجل متبق: <span class="ui-status-warning font-bold">{{ number_format($deferredProfit, 2) }}</span></span>
                                        @endif
                                        <span class="ui-text-muted text-sm">الموظف: <span class="ui-status-success font-bold">{{ $sale->employee_name ?? 'غير معروف' }}</span></span>
                                    @else
                                        <span class="ui-text-muted text-sm">التكلفة: <span class="ui-status-warning font-bold">{{ number_format($recognizedCost, 2) }}</span></span>
                                        @if(!$isInternalUse)
                                            <span class="ui-text-muted text-sm">{{ $hasPartialCredit ? 'الربح المحتسب' : 'الربح' }}: <span class="ui-status-info font-bold">{{ $profitDisplay }}</span></span>
                                        @endif
                                        @if($deferredProfit > 0)
                                            <span class="ui-text-muted text-sm">ربح مؤجل: <span class="ui-status-warning font-bold">{{ number_format($deferredProfit, 2) }}</span></span>
                                        @endif
                                    @endif
                                    <span class="ui-status-info ui-text-caption border ui-border px-2 py-1 rounded-lg">
                                        {{ $sale->payment_label }}
                                    </span>
                                    @if($isCollectionOperation)
                                        <span class="ui-text-caption ui-text-muted">كاش {{ number_format($sale->cash_paid, 2) }} / شبكة {{ number_format($sale->card_paid, 2) }}</span>
                                    @endif
                                    <i class="fas fa-chevron-down ui-text-muted ui-text-caption transition-transform" id="arrow-{{ $sale->id }}"></i>
                                </div>
                            </div>

                            <div id="details-{{ $sale->id }}" class="hidden border-t ui-border p-4 ui-surface-muted-bg">

                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                    <div class="lg:col-span-2">
                                        @if(!$isCollectionOperation && $hasCreditComponent)
                                        <div class="mb-4 rounded-xl border ui-border ui-surface-muted-bg p-3 ui-text-caption">
                                            <div class="mb-2 flex items-center gap-2 ui-text-muted font-bold">
                                                <i class="fa-solid fa-file-invoice-dollar"></i>
                                                <span>بيانات الأجل المرتبط بالعملية</span>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                                <div class="ui-surface-muted-bg border ui-border rounded-lg p-2">
                                                    <span class="block ui-text-muted mb-1">الموظف المرتبط بالأجل</span>
                                                    <span class="ui-title font-bold">{{ $creditEmployeeName ?: 'غير محدد' }}</span>
                                                </div>
                                                <div class="ui-surface-muted-bg border ui-border rounded-lg p-2">
                                                    <span class="block ui-text-muted mb-1">نوع الأجل</span>
                                                    <span class="ui-text-muted font-bold">{{ $creditTypeLabel }}</span>
                                                </div>
                                                @if(!empty($sale->credit_note))
                                                <div class="ui-surface-muted-bg border ui-border rounded-lg p-2">
                                                    <span class="block ui-text-muted mb-1">اسم العملية</span>
                                                    <span class="ui-text-muted font-bold">{{ $sale->credit_note }}</span>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                        @endif
                                        @if($shouldShowFinancialSummary)
                                        <div class="mb-4 rounded-xl border ui-border ui-status-info-bg p-3">
                                            <div class="mb-3 flex items-center justify-between gap-2">
                                                <h4 class="text-sm font-bold ui-status-info">ملخص العملية</h4>
                                            </div>

                                            <div class="grid {{ $financialSummaryGridClass }} gap-2 ui-text-caption">
                                                @if($showSummaryTotal)
                                                <div class="ui-surface-muted-bg p-3 rounded-lg text-center border ui-border">
                                                    <span class="ui-text-muted block mb-1">{{ $isCollectionOperation ? 'مبلغ التحصيل' : 'إجمالي العملية الكامل' }}</span>
                                                    <span class="ui-title font-bold">{{ number_format($isCollectionOperation ? $sale->paid_amount : $operationAmount, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @if($showSummaryCollected)
                                                <div class="ui-surface-muted-bg p-3 rounded-lg text-center border ui-border">
                                                    <span class="ui-text-muted block mb-1">{{ $isCollectionOperation ? 'غطى من التكلفة' : 'المبلغ المحصل الآن' }}</span>
                                                    <span class="ui-status-success font-bold">{{ number_format($isCollectionOperation ? $recognizedCost : $sale->paid_amount, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @if($showSummaryRemaining)
                                                <div class="ui-surface-muted-bg p-3 rounded-lg text-center border ui-border">
                                                    <span class="ui-text-muted block mb-1">{{ $isCollectionOperation ? 'ربح مؤجل متبق' : 'المتبقي / الآجل' }}</span>
                                                    <span class="{{ (!$isCollectionOperation && $sale->remaining_amount > 0) || ($isCollectionOperation && $deferredProfit > 0) ? 'ui-status-warning' : 'ui-status-success' }} font-bold">{{ number_format($isCollectionOperation ? $deferredProfit : ($sale->remaining_amount ?? 0), 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @if($showSummaryRecognizedProfit)
                                                <div class="ui-surface-muted-bg p-3 rounded-lg text-center border ui-border">
                                                    <span class="ui-text-muted block mb-1">{{ $isCollectionOperation ? 'ربح التحصيل' : 'الربح المحتسب' }}</span>
                                                    <span class="ui-status-info font-bold">{{ number_format($sale->recognized_profit ?? 0, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @if($showSummaryDeferredProfit)
                                                <div class="ui-surface-muted-bg p-3 rounded-lg text-center border ui-border">
                                                    <span class="ui-text-muted block mb-1">ربح مؤجل</span>
                                                    <span class="ui-status-warning font-bold">{{ number_format($deferredProfit, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                        @endif

                                        @if(!$isCollectionOperation && $sale->items->isNotEmpty())
                                            <div class="space-y-4">
                                                @foreach($sale->items as $item)
                                                @php
                                                    $itemTotal = $item->total ?? ($item->price * $item->quantity);
                                                    $quantity = $item->display_quantity ?? ($item->custom_consumption ?? $item->quantity);
                                                    $productName = $item->display_name ?? $item->product_name ?? 'منتج';
                                                    $quantityDisplay = $item->display_quantity_label ?? (is_numeric($quantity)
                                                        ? rtrim(rtrim(number_format((float) $quantity, 2, '.', ''), '0'), '.') . ' حبة'
                                                        : $quantity);
                                                @endphp
                                                <div class="border-b ui-border pb-3">
                                                    <div class="flex items-center gap-2 mb-2">
                                                        <span class="ui-status-info font-bold">{{ $productName }}</span>
                                                        <span class="ui-text-muted ui-text-caption">({{ $quantityDisplay }})</span>
                                                    </div>

                                                    <div class="grid grid-cols-2 gap-2 ui-text-caption">
                                                        <div class="ui-surface-muted-bg p-2 rounded text-center">
                                                            <span class="ui-text-muted block">سعر البيع</span>
                                                            <span class="ui-status-success font-bold">{{ number_format($item->price, 2) }}</span>
                                                        </div>
                                                        <div class="ui-surface-muted-bg p-2 rounded text-center">
                                                            <span class="ui-text-muted block">التكلفة</span>
                                                            @php
                                                                $effectiveUnitCost = ((float) $quantity > 0) ? ($item->calculated_cost / (float) $quantity) : 0;
                                                            @endphp
                                                            <span class="{{ $effectiveUnitCost > 0 ? 'ui-status-warning' : 'ui-status-danger' }} font-bold">{{ number_format($effectiveUnitCost, 2) }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                @endforeach
                                            </div>
                                        @elseif($isCollectionOperation)
                                            <p class="ui-text-muted text-sm">هذه العملية تمثل تحصيلًا من أجل موظف، ولا تخصم مخزونًا جديدًا.</p>
                                        @elseif((float) ($sale->labor_total ?? 0) <= 0)
                                            <p class="ui-text-muted text-sm">لا توجد منتجات</p>
                                        @endif

                                        @if($sale->labor_total > 0)
                                        <div class="mt-3 p-3 ui-status-warning-bg rounded-lg flex justify-between items-center">
                                            <span class="ui-status-warning"><i class="fas fa-hand ml-2"></i>شغل يد</span>
                                            <span class="ui-status-warning font-bold">{{ number_format($sale->labor_total, 2) }} ر.س</span>
                                        </div>
                                        @endif
                                    </div>

                                    <div class="space-y-3">
                                        <div class="ui-card p-4 rounded-lg">
                                            <h3 class="ui-title font-bold mb-3 text-sm">{{ $isCollectionOperation ? 'ملخص التحصيل' : 'ملخص العملية' }}</h3>

                                            <div class="space-y-2">
                                                @if(!$isInternalUse && ($hasCreditComponent && $hasOutstandingCredit ? $operationAmount : (float) $sale->paid_amount) > 0)
                                                <div class="flex justify-between">
                                                    <span class="ui-text-muted text-sm">{{ $isCollectionOperation ? 'المبلغ المحصل:' : ($hasCreditComponent && $hasOutstandingCredit ? 'القيمة الأساسية للعملية:' : 'المبلغ المستلم:') }}</span>
                                                    <span class="ui-title font-bold">{{ number_format($hasCreditComponent && $hasOutstandingCredit ? $operationAmount : $sale->paid_amount, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @if((float) $sale->cash_paid > 0)
                                                <div class="flex justify-between">
                                                    <span class="ui-text-muted text-sm">{{ $isCollectionOperation ? 'كاش التحصيل' : 'كاش' }}{{ !$isCollectionOperation && $sale->sale_type === 'mixed' ? ' (ضمن الميكس)' : '' }}:</span>
                                                    <span class="ui-status-success font-bold">{{ number_format($sale->cash_paid, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @if((float) $sale->card_paid > 0)
                                                <div class="flex justify-between">
                                                    <span class="ui-text-muted text-sm">{{ $isCollectionOperation ? 'شبكة التحصيل' : 'شبكة' }}{{ !$isCollectionOperation && $sale->sale_type === 'mixed' ? ' (ضمن الميكس)' : '' }}:</span>
                                                    <span class="ui-status-info font-bold">{{ number_format($sale->card_paid, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @if(!$isCollectionOperation && (float) $sale->remaining_amount > 0)
                                                <div class="flex justify-between">
                                                    <span class="ui-text-muted text-sm">الجزء الآجل{{ $sale->sale_type === 'mixed' ? ' (ضمن الميكس)' : '' }}:</span>
                                                    <span class="ui-status-warning font-bold">{{ number_format($sale->remaining_amount, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @if($isCollectionOperation)
                                                <div class="flex justify-between">
                                                    <span class="ui-text-muted text-sm">اسم الموظف:</span>
                                                    <span class="ui-status-success">{{ $sale->employee_name ?? 'غير معروف' }}</span>
                                                </div>
                                                @elseif($productsCost > 0)
                                                <div class="flex justify-between">
                                                    <span class="ui-text-muted text-sm">تكلفة المنتجات:</span>
                                                    <span class="ui-status-warning">{{ number_format($productsCost, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @if(!$isCollectionOperation && $hasCreditComponent && $recognizedCost > 0)
                                                <div class="flex justify-between">
                                                    <span class="ui-text-muted text-sm">تكلفة مغطاة من المستلم:</span>
                                                    <span class="ui-status-success font-bold">{{ number_format($recognizedCost, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @if(!$isCollectionOperation && $hasCreditComponent && $uncoveredCost > 0)
                                                <div class="flex justify-between">
                                                    <span class="ui-text-muted text-sm">تكلفة غير مغطاة بعد:</span>
                                                    <span class="ui-status-warning font-bold">{{ number_format($uncoveredCost, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @if(!$isCollectionOperation && $hasPartialCredit && (float) ($sale->recognized_profit ?? 0) > 0)
                                                <div class="flex justify-between">
                                                    <span class="ui-text-muted text-sm">الربح المحتسب الآن:</span>
                                                    <span class="ui-status-info font-bold">{{ number_format($sale->recognized_profit ?? 0, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @if(!$isCollectionOperation && $deferredProfit > 0)
                                                <div class="flex justify-between">
                                                    <span class="ui-text-muted text-sm">الربح المؤجل:</span>
                                                    <span class="ui-status-warning font-bold">{{ number_format($deferredProfit, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @if(!$isCollectionOperation && !$isInternalUse && $fullOperationProfit > 0)
                                                <div class="flex justify-between pt-2">
                                                    <span class="ui-text-muted text-sm font-bold">ربح العملية الكامل:</span>
                                                    <span class="ui-status-info font-bold text-lg">{{ number_format($fullOperationProfit, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @if($isCollectionOperation)
                                                @if($recognizedCost > 0)
                                                <div class="flex justify-between">
                                                    <span class="ui-text-muted text-sm">غطى من تكلفة العملية الأصلية:</span>
                                                    <span class="ui-status-success font-bold">{{ number_format($recognizedCost, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @if((float) ($sale->recognized_profit ?? 0) > 0)
                                                <div class="flex justify-between">
                                                    <span class="ui-text-muted text-sm">ربح تحقق بهذا التحصيل:</span>
                                                    <span class="ui-status-info font-bold">{{ number_format($sale->recognized_profit, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @if($deferredProfit > 0)
                                                <div class="flex justify-between">
                                                    <span class="ui-text-muted text-sm">ربح مؤجل متبق بعد التحصيل:</span>
                                                    <span class="ui-status-warning font-bold">{{ number_format($deferredProfit, 2) }} ر.س</span>
                                                </div>
                                                @endif
                                                @endif
                                            </div>

                                            @if(!$isCollectionOperation)
                                            @php
                                                $hasRestorableStock = $sale->items->contains(
                                                    fn ($item) => !empty($item->product_id)
                                                        && (float) ($item->custom_consumption ?? $item->quantity ?? 0) > 0
                                                );
                                                $hasLabor = (float) ($sale->labor_total ?? 0) > 0;
                                                $deleteConfirmation = \App\Support\SaleDeletionConfirmation::message(
                                                    (int) $sale->id,
                                                    (bool) ($sale->has_linked_credit ?? false),
                                                    (bool) ($sale->has_linked_credit_collections ?? false),
                                                    $hasRestorableStock,
                                                    $hasLabor
                                                );
                                            @endphp
                                            <div class="mt-3 pt-3 flex justify-end">
                                                {{-- يمنع انتشار النقر حتى لا يفتح/يغلق صف التفاصيل أثناء فتح نافذة التعديل. --}}
                                                <button type="button"
                                                        data-sensitive-action="daily.sale-edit" data-sale-id="{{ $sale->id }}" data-stop-propagation="true"
                                                        class="ui-text-caption ui-status-info-bg ui-status-info border-0 px-3 py-1.5 rounded-lg transition">
                                                    <i class="fas fa-pen ml-1"></i> تعديل العملية
                                                </button>

                                                <form method="POST"
                                                      action="{{ route('user.stores.daily.destroy', [$store->id, $sale->id]) }}"
                                                      class="mr-2"
                                                      data-confirm-delete="{{ $deleteConfirmation }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    @if($sale->has_linked_credit ?? false)
                                                        <input type="hidden" name="confirm_credit_delete" value="1">
                                                    @endif
                                                    <button type="submit"
                                                            class="ui-btn ui-btn-danger !border-0 px-3 py-2 ui-text-caption">
                                                        <i class="fas fa-trash ml-1"></i> حذف العملية
                                                    </button>
                                                </form>
                                            </div>
                                            @endif
                                        </div>
                                        <p class="px-1 ui-text-caption ui-text-muted">{{ $effectiveTimestamp->format('Y-m-d h:i A') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @endforeach
                    </div>
                </div>
                @endif
        @endforeach
    </div>

    @php
        $editableSalesSource = collect($sales);
        if (!empty($forcedEditSaleForModal) && !$editableSalesSource->contains(fn ($sale) => (int) $sale->id === (int) $forcedEditSaleForModal->id)) {
            $editableSalesSource->push($forcedEditSaleForModal);
        }

        $editableSales = $editableSalesSource
            ->filter(fn ($sale) => ($sale->operation_kind ?? null) !== 'collection')
            ->mapWithKeys(fn ($sale) => [
                (string) $sale->id => [
                    'id' => (int) $sale->id,
                    'sale_type' => (string) $sale->sale_type,
                    'edit_sale_type' => (string) ($sale->sale_type === 'credit'
                        ? 'credit'
                        : (((float) ($sale->remaining_amount ?? 0) > 0)
                            ? (((int) ($sale->has_partial_credit ?? 0) === 1) ? 'mixed' : 'credit')
                            : $sale->sale_type)),
                    'paid_amount' => (float) ($sale->paid_amount ?? 0),
                    'operation_amount' => max(
                        (float) ($sale->final_total ?? 0),
                        (float) (($sale->paid_amount ?? 0) + ($sale->remaining_amount ?? 0))
                    ),
                    'remaining_amount' => (float) ($sale->remaining_amount ?? 0),
                    'has_partial_credit' => (bool) ($sale->has_partial_credit ?? false),
                    'cash_amount' => (float) ($sale->cash_amount ?? 0),
                    'card_amount' => (float) ($sale->card_amount ?? 0),
                    'labor_total' => (float) ($sale->labor_total ?? 0),
                    'tax_rate' => (float) ($sale->tax_rate ?? 0),
                    'employee_id' => $sale->employee_id ? (int) $sale->employee_id : null,
                    'description' => (string) ($sale->description ?? ''),
                    'credit_note' => (string) ($sale->credit_note ?? ''),
                    'operation_name' => (string) ($sale->operation_name ?? $sale->credit_note ?? ''),
                    'has_meter_product' => ($sale->items ?? collect())->contains(
                        fn ($item) => ($item->product_type ?? null) === 'fractional' || (float) ($item->custom_meters ?? 0) > 0
                    ),
                    'items' => ($sale->items ?? collect())->map(fn ($item) => [
                        'id' => (int) $item->id,
                        'product_id' => (int) ($item->product_id ?? 0),
                        'name' => (string) ($item->display_name ?? $item->product_name ?? 'منتج غير معروف'),
                        'quantity' => (float) ($item->quantity ?? 0),
                        'price' => (float) ($item->price ?? 0),
                        'total' => (float) ($item->total ?? 0),
                        'unit' => (string) ($item->display_unit ?? 'وحدة'),
                        'unit_type' => (string) ($item->unit_type ?? 'unit'),
                        'is_fractional' => ($item->product_type ?? null) === 'fractional',
                        'is_splittable' => (bool) ($item->is_splittable ?? false),
                        'items_per_unit' => (float) ($item->items_per_unit ?? 1),
                        'piece_price' => (float) ($item->piece_price ?? 0),
                        'base_price' => (float) ($item->product_price ?? $item->price ?? 0),
                        'available_quantity' => (float) ($item->product_quantity ?? 0),
                    ])->values(),
                ],
            ]);
        $failedEditSaleId = session('edit_sale_modal');
    @endphp

    {{-- نافذة تعديل السجلات المالية اليومية: مصروفات / سحوبات / مديونيات. --}}
    <div id="edit-financial-modal" class="ui-modal-backdrop hidden" data-sensitive-action="daily.financial-close">
        <div class="ui-modal-panel" data-stop-propagation="true">
            <h3 id="edit-financial-modal-title" class="ui-title font-bold text-lg mb-4">تعديل سجل مالي</h3>
            <form id="edit-financial-form" method="POST" action="" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="text-sm ui-text-muted block mb-1">المبلغ</label>
                    <input id="edit-financial-amount" type="number" step="0.01" min="0.01" name="amount" class="w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title" required>
                </div>
                <div>
                    <label class="text-sm ui-text-muted block mb-1">التاريخ</label>
                    <input id="edit-financial-date" type="date" name="date" class="w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title" required>
                </div>
                <div>
                    <label class="text-sm ui-text-muted block mb-1">الملاحظات</label>
                    <textarea id="edit-financial-description" name="description" rows="3" class="w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title"></textarea>
                </div>
                <div class="flex gap-2 justify-end">
                    <button type="button" data-sensitive-action="daily.financial-close" class="ui-btn ui-btn-danger px-4 py-2">إلغاء</button>
                    <button type="submit" class="ui-btn ui-btn-primary px-4 py-2">حفظ التعديل</button>
                </div>
            </form>
        </div>
    </div>

    {{-- نافذة تعديل واحدة يعاد تعبئتها حسب العملية المختارة، بدلاً من إنشاء نافذة لكل سطر. --}}
    <div id="edit-sale-modal" class="ui-modal-backdrop hidden">
        <div class="ui-modal-panel ui-modal-panel-wide">
            <div class="ui-modal-header">
                <h3 id="edit-sale-modal-title" class="ui-title font-bold text-lg">تعديل العملية</h3>
                <button type="button" data-sensitive-action="daily.sale-close" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border ui-border ui-status-danger-bg" aria-label="إغلاق نافذة تعديل العملية">✕</button>
            </div>

            <form id="edit-sale-form" method="POST" action="" class="flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-5">
                @csrf
                @method('PUT')

                @if($failedEditSaleId && $errors->any())
                <div class="rounded-lg border ui-border ui-status-danger-bg p-3 text-sm ui-status-danger">
                    <ul class="space-y-1 list-disc pr-4">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div id="edit-sale-items-section" class="rounded-xl border ui-border ui-surface-muted-bg p-3">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <div>
                            <h4 class="ui-title font-bold text-sm flex items-center gap-2">
                                تعديل المنتجات
                                <button type="button" data-ui-help-title="توضيح" data-ui-help-body="عند زيادة الكمية يخصم النظام الزيادة فقط من المخزون، وعند تقليلها يعيد الفرق فقط إلى المخزون. تعديل سعر البيع لا يغيّر كمية المخزون." title="تنبيه" aria-label="تنبيه تعديل المنتجات" class="daily-info-button inline-flex h-5 w-5 items-center justify-center rounded-full border ui-border ui-status-warning-bg ui-text-caption ui-status-warning cursor-help"><i class="fa-solid fa-triangle-exclamation"></i></button>
                            </h4>
                        </div>
                        <span class="ui-text-caption ui-status-warning-bg border ui-border px-2 py-1 rounded-lg">راجع الكميات قبل الحفظ</span>
                    </div>
                    <div id="edit-sale-items-list" class="space-y-3"></div>
                    <button type="button" data-sensitive-action="daily.product-add" class="mt-3 ui-btn ui-btn-primary px-3 py-2 ui-text-caption">+ إضافة منتج للعملية</button>
                    <div id="edit-meter-products-note" class="hidden mt-3 flex items-center gap-2 rounded-lg border ui-border ui-status-info-bg px-2 py-1.5 ui-text-caption ui-status-info">
                        <span>منتجات الرول والتضليل</span>
                        <button type="button" data-ui-help-title="توضيح" data-ui-help-body="يمكن تعديل سعر بيع منتج الرول أو التضليل، لكن كمية الاستهلاك بالمتر تبقى كما سُجلت لحماية المخزون والتكلفة." title="تنبيه" aria-label="تنبيه منتجات الرول والتضليل" class="daily-info-button inline-flex h-5 w-5 items-center justify-center rounded-full border ui-border ui-status-warning-bg ui-text-caption ui-status-warning cursor-help"><i class="fa-solid fa-triangle-exclamation"></i></button>
                    </div>
                </div>

                <div class="rounded-xl border ui-border ui-surface-muted-bg p-3">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h4 class="text-sm font-bold ui-title">بيانات الدفع</h4>
                        <button id="edit-mixed-payment-help" type="button" data-ui-help-title="توضيح" data-ui-help-body="تنبيه: المبلغ المدفوع يُحسب تلقائيًا من كاش + شبكة، ولا يمكن كتابته يدويًا." title="تنبيه الميكس" aria-label="تنبيه الميكس" class="hidden daily-info-button inline-flex h-6 w-6 items-center justify-center rounded-full border ui-border ui-status-warning-bg ui-text-caption ui-status-warning cursor-help"><i class="fa-solid fa-triangle-exclamation"></i></button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="text-sm ui-text-muted block mb-1">نوع البيع</label>
                            {{-- تغيير النوع يطلب المزامنة الكاملة نفسها التي كان ينفذها onchange السابق. --}}
                            <select id="edit-sale-type" name="sale_type" data-sensitive-input="daily.sale-fields-sync" class="w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title">
                                <option value="cash">نقداً</option>
                                <option value="card">بطاقة</option>
                                <option value="credit">آجل</option>
                                <option value="mixed">ميكس</option>
                            </select>
                        </div>

                        <div id="edit-paid-amount-wrapper">
                    <label class="text-sm ui-text-muted mb-1 flex items-center gap-2"><span id="edit-paid-amount-label-text">المبلغ المدفوع</span> <button id="edit-paid-amount-help-button" type="button" data-ui-help-title="توضيح المبلغ المدفوع" data-ui-help-body="في عمليات النقد أو البطاقة يمكنك تعديل المبلغ المدفوع مباشرة." title="توضيح المبلغ المدفوع" aria-label="توضيح المبلغ المدفوع" class="daily-info-button inline-flex h-5 w-5 items-center justify-center rounded-full border ui-border ui-status-info-bg ui-text-caption ui-status-info cursor-help"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button></label>
                    <input id="edit-paid-amount-input" type="number" step="0.01" min="0" name="paid_amount" data-sensitive-input="daily.payment-touch"
                           placeholder="أدخل المبلغ المدفوع" class="w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title">
                    <div id="edit-paid-price-change-warning" class="hidden mt-2 rounded-lg border ui-border ui-status-warning-bg p-2 ui-text-caption ui-status-warning"></div>
                    <div id="edit-credit-conversion-warning" class="hidden mt-2 rounded-lg border ui-border ui-status-warning-bg p-2 ui-text-caption ui-status-warning">
                        تنبيه: هذه العملية كانت آجلًا وتم تحصيل جزء منها مسبقًا؛ لذلك تم وضع <span class="font-bold">القيمة المتبقية</span> داخل خانة المبلغ المدفوع لإكمال التحويل.
                    </div>
                </div>

                        <div id="edit-mixed-wrapper" class="hidden md:col-span-2">
                    <div id="edit-mixed-conversion-warning" class="hidden mb-3 rounded-lg border ui-border ui-status-info-bg p-2 ui-text-caption ui-status-info"></div>
                    <div id="edit-mixed-price-change-warning" class="hidden mb-3 rounded-lg border ui-border ui-status-warning-bg p-2 ui-text-caption ui-status-warning"></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <div>
                            <label class="text-sm ui-text-muted block mb-1">كاش</label>
                            <input id="edit-cash-amount-input" type="number" step="0.01" min="0.01" name="cash_amount" data-sensitive-input="daily.mixed-payment"
                                   placeholder="أدخل مبلغ الكاش" class="w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title">
                        </div>
                        <div>
                            <label class="text-sm ui-text-muted block mb-1">شبكة</label>
                            <input id="edit-card-amount-input" type="number" step="0.01" min="0.01" name="card_amount" data-sensitive-input="daily.mixed-payment"
                                   placeholder="أدخل مبلغ الشبكة" class="w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title">
                        </div>
                    </div>
                </div>

                        <div id="edit-register-credit-wrapper" class="hidden rounded-lg border ui-border ui-surface-muted-bg p-3 ui-text-caption ui-text-muted md:col-span-2">
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input id="edit-register-credit-input" type="checkbox" name="record_remaining_as_credit" value="1" data-sensitive-input="daily.sale-fields"
                               class="mt-1 rounded ui-border ui-surface-muted-bg ui-text-muted ">
                        <span class="flex flex-1 items-center justify-between gap-2">
                            تسجيل المبلغ المتبقي كآجل
                            <button type="button" data-ui-help-title="توضيح" data-ui-help-body="يظهر هذا الخيار للعمليات التي لا تحتوي أجلًا حاليًا، ويمكنك من تسجيل الأجل على أحد الموظفين." title="توضيح تسجيل الأجل" aria-label="توضيح تسجيل الأجل" class="daily-info-button inline-flex h-5 w-5 items-center justify-center rounded-full border ui-border ui-status-info-bg ui-text-caption ui-status-info cursor-help"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                        </span>
                    </label>
                </div>

                        <div id="edit-debt-wrapper" class="hidden">
                    <label class="text-sm ui-text-muted mb-1 flex items-center gap-2">قيمة الأجل <button type="button" data-ui-help-title="توضيح" data-ui-help-body="للآجل الكامل يجب أن تساوي كامل العملية. وللآجل الجزئي في الميكس يجب أن يغطي مجموع الكاش والشبكة والأجل قيمة العملية على الأقل." title="تنبيه" aria-label="تنبيه قيمة الأجل" class="daily-info-button inline-flex h-5 w-5 items-center justify-center rounded-full border ui-border ui-status-warning-bg ui-text-caption ui-status-warning cursor-help"><i class="fa-solid fa-triangle-exclamation"></i></button></label>
                    <input id="edit-debt-amount-input" type="number" step="0.01" min="0" name="debt_amount" data-sensitive-input="daily.sale-fields"
                           placeholder="أدخل قيمة الأجل" class="w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title">
                </div>

                        <div id="edit-employee-wrapper" class="hidden">
                    <label class="text-sm ui-text-muted mb-1 flex items-center gap-2">الموظف المرتبط بالآجل <button type="button" data-ui-help-title="توضيح" data-ui-help-body="اختر الموظف الذي ستسجل عليه قيمة الأجل. يصبح اختيار الموظف إلزاميًا عند وجود قيمة أجل أو عند تفعيل تسجيل المتبقي كآجل." title="تنبيه" aria-label="تنبيه الموظف المرتبط بالآجل" class="daily-info-button inline-flex h-5 w-5 items-center justify-center rounded-full border ui-border ui-status-warning-bg ui-text-caption ui-status-warning cursor-help"><i class="fa-solid fa-triangle-exclamation"></i></button></label>
                    <select id="edit-employee-input" name="employee_id" class="w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title">
                        <option value="">اختر الموظف</option>
                        @foreach(($employees ?? collect()) as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                        @endforeach
                    </select>
                    <p class="ui-text-caption ui-text-muted mt-1">يجب اختيار موظف واحد على الأقل.</p>
                </div>

                    </div>
                </div>

                <div class="rounded-xl border ui-border ui-surface-muted-bg p-3">
                    <label class="text-sm ui-text-muted mb-1 flex items-center gap-2">شغل اليد <button type="button" data-ui-help-title="توضيح" data-ui-help-body="تغيير المنتج لا يغيّر شغل اليد تلقائياً؛ راجع هذا الحقل إذا كان مرتبطاً بالمنتج الجديد." title="تنبيه" aria-label="تنبيه شغل اليد" class="daily-info-button inline-flex h-5 w-5 items-center justify-center rounded-full border ui-border ui-status-warning-bg ui-text-caption ui-status-warning cursor-help"><i class="fa-solid fa-triangle-exclamation"></i></button></label>
                    <input id="edit-labor-total-input" type="number" step="0.01" min="0" name="labor_total" data-sensitive-input="daily.operation-total"
                           class="w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title">
                    <div id="edit-labor-price-change-warning" class="hidden mt-2 rounded-lg border ui-border ui-status-info-bg p-2 ui-text-caption ui-status-info"></div>
                </div>

                <div class="rounded-xl border ui-border ui-surface-muted-bg p-3">
                    <label class="text-sm ui-text-muted block mb-1">شغل اليد</label>
                    <textarea id="edit-description-input" name="description" rows="3"
                              class="w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title" placeholder="مثال: شغل يد، تضليل، تركيب، برمجة..."></textarea>
                </div>

                <div id="edit-credit-note-wrapper" class="hidden rounded-xl border ui-border ui-surface-muted-bg p-3">
                    <label class="text-sm ui-text-muted mb-1 flex items-center gap-2">اسم العملية <button type="button" data-ui-help-title="توضيح" data-ui-help-body="يجب إضافة اسم العميل أو رقم هاتفه من أجل متابعة العملية." title="توضيح اسم العملية" aria-label="توضيح اسم العملية" class="daily-info-button inline-flex h-5 w-5 items-center justify-center rounded-full border ui-border ui-status-info-bg ui-text-caption ui-status-info cursor-help"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button></label>
                    <textarea id="edit-credit-note-input" name="operation_name" rows="2"
                              class="w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title" placeholder="اسم العميل أو رقم الهاتف"></textarea>
                </div>

                <div class="ui-section-divider-actions flex-col-reverse sm:flex-row">
                    <button type="button" data-sensitive-action="daily.sale-close" class="ui-btn ui-btn-danger px-4 py-2">إلغاء</button>
                    <button type="submit" class="ui-btn ui-btn-primary px-4 py-2">حفظ التعديل</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== ملخص الصفحة ===== --}}
    @if($sales->count() > 0)
    <div class="mt-6 p-4 ui-surface-muted-bg rounded-xl border ui-border">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <p class="ui-text-muted ui-text-caption">عدد العمليات</p>
                <p class="ui-title font-bold">{{ $sales->count() }}</p>
            </div>
            <div>
                <p class="ui-text-muted ui-text-caption">إجمالي المستلم</p>
                <p class="ui-status-success font-bold">{{ number_format($stats['total'] ?? 0, 2) }} ر.س</p>
            </div>
            <div>
                <p class="ui-text-muted ui-text-caption">التكلفة</p>
                <p class="ui-status-warning font-bold">{{ number_format($stats['total_cost'] ?? 0, 2) }} ر.س</p>
            </div>
            <div>
                <p class="ui-text-muted ui-text-caption">الربح المحتسب</p>
                <p class="ui-status-info font-bold">{{ number_format($stats['total_profit'] ?? 0, 2) }} ر.س</p>
            </div>
        </div>
    </div>
    @endif

</div>

{{-- عقد إعداد آمن لوحدة المبيعات اليومية؛ يحافظ على البيانات والمسارات والقيم المعادة بعد أخطاء التحقق. --}}
<div class="hidden"
     data-daily-sales-config="{{ json_encode([
         'editableSales' => $editableSales,
         'autoEditSaleId' => request('edit_sale'),
         'returnToAfterEdit' => request('return_to'),
         'editableProducts' => ($editableProducts ?? collect())->values(),
         'editFinancialUrlTemplate' => url('/user/stores/' . $store->id . '/daily-sales/financial/__TYPE__/__ID__'),
         'editSaleUrlTemplate' => url('/user/stores/' . $store->id . '/daily-sales/__SALE_ID__'),
         'failedModalId' => session('edit_sale_modal'),
         'failedOldValues' => $failedEditSaleId ? old() : [],
     ], JSON_HEX_APOS | JSON_HEX_QUOT) }}"
     aria-hidden="true"></div>


@endsection
