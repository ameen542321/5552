@extends('dashboard.app')
@section('title', 'تحصيل البيع الآجل')
@section('content')

<div class="max-w-7xl mx-auto px-3 sm:px-4 py-4 sm:py-6" dir="rtl">
    <x-employee.operation-page-header
        title="تحصيل البيع الآجل"
        subtitle="اختر الموظف لعرض عمليات الآجل وتحصيلها"
        :subtitle-as-help="true"
        icon="أ"
        accent="success"
    />

    <div class="space-y-4">

        {{-- قسم اختيار الموظف --}}
        <div>
            <div class="ui-card p-4 shadow-sm"> {{-- تصغير padding --}}

                {{-- شريط البحث والعنوان المدمج --}}
                <div class="flex items-center justify-between gap-2 mb-2">
                    <h2 class="text-sm font-bold ui-title flex items-center gap-1">
                        <span class="w-1.5 h-4 ui-dot-success rounded-full"></span>
                        الموظفين
                    </h2>
                    <div class="relative w-48">
                        {{-- إصلاح مطبق: البحث النصي في الموظفين وعمليات الآجل يستخدم فلتر ui-actions المشترك. --}}
                        <input type="text" id="employeeSearch" data-ui-filter-input="employeesList"
                               placeholder="ابحث باسم الموظف أو عملية الآجل..."
                               class="ui-input ui-text-caption py-1.5 px-3">
                    </div>
                </div>

                {{-- كروت الموظفين المدمجة --}}
                <div id="employeesList" class="space-y-1.5 max-h-[500px] overflow-y-auto custom-scrollbar pr-1">
                    @foreach($people as $emp)
                    @php
                        $pendingCount = $emp->pending_credit_sales->count();
                        $totalPending = $emp->pending_credit_sales->sum('remaining_amount');
                    @endphp
                    <div class="employee-card ui-card-muted rounded-lg p-2 ui-hover-surface transition-all"
                         data-search="{{ $emp->name }} {{ $emp->pending_credit_sales->pluck('credit_note')->merge($emp->pending_credit_sales->pluck('description'))->filter()->implode(' ') }}"
                         data-ui-filter-value="{{ $emp->name }} {{ $emp->pending_credit_sales->pluck('credit_note')->merge($emp->pending_credit_sales->pluck('description'))->filter()->implode(' ') }}">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                <div class="relative flex-shrink-0">
                                    <div class="w-7 h-7 rounded-full ui-surface-strong-bg border ui-border flex items-center justify-center ui-title font-bold ui-text-caption">
                                        {{ mb_substr($emp->name, 0, 1) }}
                                    </div>
                                    @if($pendingCount > 0)
                                        <div class="absolute -top-1.5 -right-1.5 ui-count-badge">
                                            {{ $pendingCount }}
                                        </div>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1">
                                        <h3 class="ui-title text-base font-bold truncate">{{ $emp->name }}</h3>
                                    </div>
                                    @if($pendingCount > 0)
                                        <span class="ui-text-caption px-1 py-0.5 rounded-md ui-status-danger-bg ui-status-danger">
                                            {{ number_format($totalPending, 0) }} ر.س
                                        </span>
                                    @endif
                                </div>
                            </div>
                            {{-- عقد واجهة فقط؛ تنفيذ التحصيل ومعادلاته يبقيان في الدوال الحالية دون تغيير. --}}
                            <button data-sensitive-action="collection.open" data-employee-id="{{ $emp->id }}" data-employee-name="{{ $emp->name }}"
                                    class="ui-btn ui-btn-success px-3 py-2 text-sm flex-shrink-0">
                                تحصيل
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- قسم السجل الجانبي المدمج --}}
        <div>
            <div class="ui-card rounded-2xl p-4 shadow-sm">
                <h2 class="text-sm font-bold ui-title mb-2 flex items-center gap-1">
                    <span class="ui-status-warning">🕒</span>
                    آخر 10 تحصيلات
                </h2>

                <div class="space-y-2 max-h-[400px] overflow-y-auto custom-scrollbar pr-1">
                    @foreach ($lastCollections as $log)
                    <div class="group relative ui-frame-row ui-hover-surface transition-colors">
                        <div class="flex justify-between items-start">
                            <h4 class="ui-title ui-text-caption font-medium">{{ $log->person->name ?? '—' }}</h4>
                            <span class="{{ $log->action_name === 'credit_sale_deducted' ? 'ui-status-success' : 'ui-status-info' }} font-bold ui-text-caption">
                                {{ $log->amount ?? 0 }} ﷼
                            </span>
                        </div>
                        <div class="flex items-center justify-between mt-0.5">
                            {{-- تاريخ التحصيل المسجل مقدم على تاريخ إدخال سجل النشاط. --}}
                            <p class="ui-text-muted ui-text-caption">{{ data_get($log->meta, 'operation_date') ?? optional($log->created_at)->format('Y-m-d') }}</p>
                            <span class="ui-text-caption px-1 py-0.5 rounded {{ $log->action_name === 'credit_sale_deducted' ? 'ui-status-success-bg ui-status-success' : 'ui-status-info-bg ui-status-info' }}">
                                {{ $log->action_name === 'credit_sale_deducted' ? 'كامل' : 'جزئي' }}
                            </span>
                        </div>
                        @if(!empty($log->description))
                            <p class="mt-2 rounded-lg ui-surface-strong-bg p-2 ui-text-caption ui-text-soft">{{ Str::limit($log->description, 80) }}</p>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============================= --}}
{{-- مودال التحصيل المدمج --}}
{{-- ============================= --}}
<div id="collectionModal"
     class="ui-modal-backdrop hidden">

    <div class="ui-modal-panel rounded-xl shadow-2xl w-full max-w-md
                max-h-[80vh] overflow-y-auto custom-scrollbar mx-2">

        {{-- رأس المودال المدمج --}}
        <div class="ui-modal-header">
            <div class="flex items-center gap-1.5">
                <span class="w-1.5 h-4 ui-dot-success rounded-full"></span>
                <h3 class="text-sm font-bold ui-title">تحصيل</h3>
                <span class="ui-text-soft ui-text-caption mr-1" id="empName"></span>
            </div>
            <button type="button" data-sensitive-action="collection.close"
                    class="ui-modal-close-danger flex items-center justify-center" aria-label="إغلاق">×</button>
        </div>

        {{-- قائمة العمليات --}}
        <div id="creditSalesList" class="p-3 space-y-2"></div>

        {{-- زر الإغلاق --}}
        <div class="p-3 pt-0">
            <button type="button" data-sensitive-action="collection.close"
                    class="ui-btn ui-btn-danger w-full py-2 text-sm">
                إغلاق
            </button>
        </div>
    </div>
</div>

{{-- ============================= --}}
{{-- مودال معاينة عملية الأجل --}}
{{-- ============================= --}}
<div id="previewModal"
     class="ui-modal-backdrop hidden">
    <div class="ui-modal-panel rounded-xl shadow-2xl w-full max-w-2xl max-h-[85vh] overflow-y-auto custom-scrollbar mx-2">
        <div class="ui-modal-header">
            <div class="flex items-center gap-1.5">
                <span class="w-1.5 h-4 ui-dot-info rounded-full"></span>
                <h3 class="text-sm font-bold ui-title">معاينة العملية</h3>
            </div>
            <button type="button" data-sensitive-action="collection.preview-close" class="ui-modal-close-danger flex items-center justify-center" aria-label="إغلاق">×</button>
        </div>
        <div id="previewContent" class="p-3 space-y-3"></div>
    </div>
</div>

{{-- ============================= --}}
{{-- مودال التحصيل الجزئي المدمج --}}
{{-- ============================= --}}
<div id="partialModal"
     class="ui-modal-backdrop hidden">

    <div class="ui-modal-panel rounded-xl shadow-2xl w-full max-w-lg mx-2">

        {{-- رأس المودال المدمج --}}
        <div class="ui-modal-header">
            <div class="flex items-center gap-1.5">
                <span class="w-1.5 h-4 ui-dot-info rounded-full"></span>
                <h3 class="text-sm font-bold ui-title">نوع التحصيل</h3>
            </div>
            <button type="button" data-sensitive-action="collection.partial-close"
                    class="ui-modal-close-danger flex items-center justify-center" aria-label="إغلاق">×</button>
        </div>

        {{-- محتوى المودال المدمج --}}
        <form id="partialForm" class="p-4 space-y-4">
            <div class="grid grid-cols-3 gap-2" role="group" aria-label="طريقة التحصيل الجزئي">
                <button type="button" data-partial-payment-method="cash" class="ui-payment-option is-active flex flex-col items-center justify-center gap-1 border transition">
                    <span class="ui-payment-option-icon"><i class="fa-solid fa-money-bill-wave text-sm"></i></span>
                    <span class="text-sm font-bold">كاش</span>
                    <span class="ui-text-caption opacity-70">يسلم للمالك</span>
                </button>
                <button type="button" data-partial-payment-method="card" class="ui-payment-option flex flex-col items-center justify-center gap-1 border transition">
                    <span class="ui-payment-option-icon"><i class="fa-solid fa-credit-card text-sm"></i></span>
                    <span class="text-sm font-bold">شبكة</span>
                    <span class="ui-text-caption opacity-70">يدخل شبكة</span>
                </button>
                <button type="button" data-partial-payment-method="mixed" class="ui-payment-option flex flex-col items-center justify-center gap-1 border transition">
                    <span class="ui-payment-option-icon"><i class="fa-solid fa-money-bill-transfer text-sm"></i></span>
                    <span class="text-sm font-bold">ميكس</span>
                    <span class="ui-text-caption opacity-70">كاش + شبكة</span>
                </button>
            </div>
            <input id="partialPaymentMethod" type="hidden" value="cash">

            <div class="rounded-2xl border ui-border ui-input-bg p-3">
                <label id="partialAmountLabel" class="mb-1 block ui-text-caption ui-text-soft">المبلغ المحصل كاش</label>
                <div class="relative">
                    <input id="partialAmount" type="number" min="0.01" max="0" step="0.01" inputmode="decimal"
                           class="ui-input w-full rounded-xl p-2 pl-12 text-sm disabled:cursor-not-allowed disabled:opacity-70"
                           placeholder="لا يتجاوز المتبقي">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 ui-text-caption ui-text-muted">ريال</span>
                </div>
                <p id="partialAmountLimit" class="mt-1 ui-text-caption ui-text-muted"></p>
            </div>

            <div id="partialSplitWrapper" class="hidden grid grid-cols-2 gap-2">
                <div class="rounded-2xl border ui-status-success-border ui-status-success-bg p-3">
                    <label class="mb-1 block ui-text-caption ui-status-success"><i class="fa-solid fa-money-bill-wave ml-1"></i> مبلغ الكاش</label>
                    <input id="partialCashAmount" type="number" step="0.01" min="0" class="ui-input w-full rounded-xl p-2 text-sm">
                </div>
                <div class="rounded-2xl border ui-status-info-border ui-status-info-bg p-3">
                    <label class="mb-1 block ui-text-caption ui-status-info"><i class="fa-solid fa-credit-card ml-1"></i> مبلغ الشبكة</label>
                    <input id="partialCardAmount" type="number" step="0.01" min="0" class="ui-input w-full rounded-xl p-2 text-sm">
                </div>
            </div>
            <p id="partialPaymentHint" class="ui-text-caption ui-text-soft">الكاش يدخل في تسليم المحاسب للمالك، والشبكة تظهر كتحصيل شبكة ضمن الحسابات.</p>

            <div class="flex gap-2 pt-1">
                <button type="button" data-sensitive-action="collection.partial-close" class="ui-btn ui-btn-danger flex-1 py-2 text-sm">إلغاء</button>
                <button type="submit" class="ui-btn ui-btn-success flex-1 py-2 text-sm">تأكيد</button>
            </div>
        </form>
    </div>
</div>


{{-- عقد إعداد آمن لوحدة تحصيل الآجل؛ لا يغيّر أسماء الحقول أو payload أو مسارات الحفظ. --}}
<div class="hidden"
     data-credit-collection-config="{{ json_encode([
         'sales' => $people->mapWithKeys(fn ($emp) => [$emp->id => $emp->pending_credit_sales]),
         'accountantEmployeeId' => auth('accountant')->user()->employee_id,
         'collectionRouteTemplate' => route('accountant.pos.collection.store', ['sale' => 'SALE']),
         'csrfToken' => csrf_token(),
     ], JSON_HEX_APOS | JSON_HEX_QUOT) }}"
     aria-hidden="true"></div>

@endsection
