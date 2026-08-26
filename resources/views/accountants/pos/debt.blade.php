@extends('dashboard.app')
@section('title', 'إضافة / تحصيل مديونية')

@section('content')
<div class="max-w-7xl mx-auto px-3 sm:px-4 py-4 sm:py-6" dir="rtl">
    <x-employee.operation-page-header
        title="إضافة / تحصيل مديونية"
        subtitle="إضافة مديونية جديدة أو تحصيل مديونية قائمة من واجهة واحدة"
        :subtitle-as-help="true"
        icon="د"
        accent="danger"
    />

    <div class="space-y-4">

        {{-- قسم اختيار الموظف --}}
        <div>
            <div class="ui-card p-4">

                {{-- شريط البحث والعنوان --}}
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 sm:gap-3 mb-2 sm:mb-3">
                    <h2 class="text-sm sm:text-base font-bold ui-title flex items-center gap-1">
                        <span class="w-1.5 h-4 sm:w-2 sm:h-5 ui-dot-danger rounded-full"></span>
                        الموظفين
                    </h2>
                    <div class="relative w-full sm:w-48">
                        {{-- إصلاح مطبق: البحث في الموظفين وملاحظات المديونية يستخدم فلتر ui-actions المشترك. --}}
                        <input type="text" id="employeeSearch" data-ui-filter-input="employeesList"
                               placeholder="ابحث باسم الموظف أو الملاحظة..."
                               class="ui-input ui-text-caption sm:text-sm py-1.5">
                    </div>
                </div>

                {{-- كروت الموظفين --}}
                <div class="grid grid-cols-1 gap-1.5 sm:gap-2 max-h-[500px] overflow-y-auto custom-scrollbar pr-1" id="employeesList">
                    @foreach($people as $emp)
                    @php
                        $hasDebt = ($emp->active_debt_count ?? 0) > 0;
                        $totalDebt = $emp->active_debt_total ?? 0;
                    @endphp
                    <div class="employee-card ui-card-muted p-2 ui-hover-surface transition-all"
                         data-search="{{ $emp->name }} {{ $emp->debts->pluck('description')->filter()->implode(' ') }}"
                         data-ui-filter-value="{{ $emp->name }} {{ $emp->debts->pluck('description')->filter()->implode(' ') }}">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5 sm:gap-2 min-w-0 flex-1">
                                <div class="relative flex-shrink-0">
                                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full ui-surface-strong-bg border ui-border flex items-center justify-center ui-title font-bold ui-text-caption sm:text-sm">
                                        {{ mb_substr($emp->name, 0, 1) }}
                                    </div>
                                    @if($hasDebt)
                                        <div class="absolute -top-1.5 -right-1.5 ui-count-badge" aria-label="{{ $emp->active_debt_count }} مديونية قائمة">
                                            {{ $emp->active_debt_count }}
                                        </div>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="ui-title ui-text-caption sm:text-sm font-medium truncate">{{ $emp->name }}</h3>
                                    <div class="flex items-center gap-1 mt-0.5">
                                        @if($hasDebt)
                                            <span class="ui-text-caption px-1 py-0.5 rounded-md ui-status-danger-bg ui-status-danger">
                                                {{ number_format($totalDebt, 0) }}ر.س
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            {{-- يمرر العقد هوية الموظف وحالة الدين فقط، ولا يغيّر أصل المديونية أو طريقة حسابها. --}}
                            <button type="button" data-sensitive-action="debt.open" data-employee-id="{{ $emp->id }}" data-employee-name="{{ $emp->name }}" data-has-debt="{{ $hasDebt ? 'true' : 'false' }}"
                                    class="ui-btn {{ $hasDebt ? 'ui-btn-secondary' : 'ui-btn-primary' }} px-3 py-2 text-sm flex-shrink-0">
                                {{ $hasDebt ? 'إدارة' : 'إضافة' }}
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- قسم السجل الجانبي --}}
        <div>
            <div class="ui-card rounded-2xl p-4 shadow-sm">
                <h2 class="text-sm sm:text-base font-bold ui-title mb-2 sm:mb-3 flex items-center gap-1">
                    <span class="ui-status-warning text-base sm:text-lg">🕒</span>
                    آخر 10 عمليات
                </h2>

                <div class="space-y-2 max-h-[400px] overflow-y-auto custom-scrollbar pr-1">
                    @forelse($lastDebts as $op)
                    <div class="group relative rounded-xl border ui-border p-3 {{ $op->amount > 0 ? 'ui-hover-surface' : '' }} transition-colors">
                        <div class="flex justify-between items-start">
                            <h4 class="ui-text-soft ui-text-caption sm:text-sm font-medium">{{ $op->person->name ?? '—' }}</h4>
                            <span class="{{ $op->amount > 0 ? 'ui-status-danger' : 'ui-status-info' }} font-bold ui-text-caption sm:text-sm">
                                {{ number_format(abs($op->amount), 0) }} ﷼
                            </span>
                        </div>
                        <div class="flex items-center justify-between mt-0.5">
                            {{-- تاريخ العملية هو الأساس، وتاريخ الإدخال بديل للسجلات القديمة فقط. --}}
                            <p class="ui-text-soft ui-text-caption">التاريخ: {{ optional($op->date ?? $op->created_at)->format('Y-m-d') }}</p>
                            <span class="ui-text-caption px-1 py-0.5 rounded {{ $op->amount > 0 ? 'ui-status-danger-bg ui-status-danger' : 'ui-status-info-bg ui-status-info' }}">
                                {{ $op->amount > 0 ? 'إضافة' : 'تحصيل' }}
                            </span>
                        </div>
                        @if(!empty($op->description))
                            <p class="mt-2 rounded-lg ui-surface-strong-bg p-2 ui-text-caption ui-text-soft">{{ Str::limit($op->description, 80) }}</p>
                        @endif
                    </div>
                    @empty
                    <div class="text-center py-6 opacity-40">
                        <p class="ui-text-soft ui-text-caption sm:text-sm">لا توجد عمليات</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============================= --}}
{{-- مودال المديونية الرئيسي المدمج --}}
{{-- ============================= --}}
<div id="debtModal"
     class="ui-modal-backdrop hidden">

    <div class="fixed inset-0 ui-scrim backdrop-blur-sm transition-opacity"
         data-sensitive-action="debt.close"></div>

    <div class="relative ui-modal-panel border ui-border rounded-xl sm:rounded-2xl shadow-2xl w-full max-w-full sm:max-w-md
                max-h-[90vh] overflow-y-auto custom-scrollbar transform transition-all mx-2 sm:mx-0">

        {{-- رأس المودال المدمج --}}
        <div class="ui-modal-header">
            <div class="flex items-center gap-1.5 sm:gap-2 min-w-0">
                <span class="w-1.5 h-4 sm:w-2 sm:h-5 ui-dot-danger rounded-full"></span>
                <h3 class="text-sm sm:text-base font-bold ui-title flex-shrink-0" id="modalTitle">إضافة مديونية</h3>
                <span class="ui-text-soft ui-text-caption sm:text-sm mr-1 truncate max-w-[220px]" id="empNameDisplay"></span>
            </div>
            <button type="button" data-sensitive-action="debt.close"
                    class="ui-modal-close-danger flex-shrink-0" aria-label="إغلاق">×</button>
        </div>

        {{-- محتوى الفورم --}}
        <form id="debtForm" method="POST" class="p-3 sm:p-4 space-y-2.5 sm:space-y-3">
            @csrf

            {{-- حقل المبلغ --}}
            <div class="space-y-1 sm:space-y-1.5">
                <label class="block ui-text-caption sm:text-sm font-medium ui-text-soft">المبلغ</label>
                <div class="relative">
                    <input type="number" name="amount" step="0.01" min="0.1" required
                           class="ui-input text-base sm:text-lg font-bold pl-8 sm:pl-10"
                           placeholder="0.00">
                    <span class="absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 ui-text-muted ui-text-caption sm:text-sm">﷼</span>
                </div>
            </div>

            {{-- حقل التاريخ --}}
            <div class="space-y-1 sm:space-y-1.5">
                <label class="block ui-text-caption sm:text-sm font-medium ui-text-soft">التاريخ</label>
                <input type="date" name="date"
                       value="{{ date('Y-m-d') }}"
                       required
                       class="ui-input ui-date-input text-sm sm:text-base">
            </div>

            {{-- حقل الوصف --}}
            <div class="space-y-1 sm:space-y-1.5">
                <label class="block ui-text-caption sm:text-sm font-medium ui-text-soft">الوصف (اختياري)</label>
                <textarea name="description" rows="2"
                          class="ui-input resize-none text-sm sm:text-base"
                          placeholder="تفاصيل إضافية..."></textarea>
            </div>

            {{-- أزرار متعددة - تتغير حسب حالة الموظف --}}
            <div id="debtActions" class="hidden space-y-2">
                <button type="submit"
                        class="ui-btn ui-btn-primary w-full py-2.5 sm:py-3 text-sm sm:text-base">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    إضافة مديونية
                </button>

                <button type="button" data-sensitive-action="debt.collect-open"
                        class="ui-btn ui-btn-secondary w-full py-2.5 sm:py-3 text-sm sm:text-base">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    تحصيل
                </button>
            </div>

            {{-- أزرار الإضافة فقط --}}
            <div id="addOnly" class="hidden">
                <button type="submit"
                        class="ui-btn ui-btn-primary w-full py-2.5 sm:py-3 text-sm sm:text-base">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    حفظ المديونية
                </button>
            </div>

            {{-- زر الإلغاء --}}
            <button type="button" data-sensitive-action="debt.close"
                    class="ui-btn ui-btn-danger w-full py-2.5 sm:py-3 text-sm sm:text-base">
                إلغاء
            </button>
        </form>
    </div>
</div>

{{-- ============================= --}}
{{-- مودال التحصيل المدمج --}}
{{-- ============================= --}}
<div id="collectModal"
     class="ui-modal-backdrop hidden">

    <div class="fixed inset-0 ui-scrim backdrop-blur-sm transition-opacity"
         data-sensitive-action="debt.collect-close"></div>

    <div class="relative ui-modal-panel border ui-border rounded-xl sm:rounded-2xl shadow-2xl w-full max-w-full sm:max-w-lg
                max-h-[90vh] overflow-y-auto custom-scrollbar transform transition-all mx-2 sm:mx-0">

        {{-- رأس المودال --}}
        <div class="ui-modal-header">
            <div class="flex items-center gap-1.5 sm:gap-2 min-w-0">
                <span class="w-1.5 h-4 sm:w-2 sm:h-5 ui-dot-info rounded-full"></span>
                <h3 class="text-sm sm:text-base font-bold ui-title flex-shrink-0">تحصيل المديونيات</h3>
                <span class="ui-text-soft ui-text-caption sm:text-sm mr-1 truncate max-w-[220px]" id="collectEmpName"></span>
            </div>
            <button type="button" data-sensitive-action="debt.collect-close"
                    class="ui-modal-close-danger flex-shrink-0" aria-label="إغلاق">×</button>
        </div>

        {{-- قائمة المديونيات --}}
        <div id="debtsList" class="p-3 sm:p-4 space-y-2 sm:space-y-3">
            <div class="flex items-center justify-center py-6">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 ui-status-info-border"></div>
                <span class="mr-2 ui-text-soft ui-text-caption sm:text-sm">جاري التحميل...</span>
            </div>
        </div>

        <div class="p-3 sm:p-4 pt-0">
            <button type="button" data-sensitive-action="debt.collect-close"
                    class="ui-btn ui-btn-danger w-full py-2.5 sm:py-3 text-sm sm:text-base">
                إغلاق
            </button>
        </div>
    </div>
</div>


{{-- عقد إعداد آمن لوحدة المديونيات؛ لا يغيّر أصل الدين أو حقول التحصيل أو مسارات الحفظ. --}}
<div class="hidden"
     data-debt-operations-config="{{ json_encode([
         'debtStoreRouteTemplate' => route('accountant.pos.debt.store', ['employee' => 'ID']),
         'debtListRouteTemplate' => route('accountant.debts.list', ['id' => 'EMP_ID']),
         'fullCollectionUrl' => url('accountant/pos/debt/collect/full'),
         'partialCollectionUrl' => url('accountant/pos/debt/collect/partial'),
     ], JSON_HEX_APOS | JSON_HEX_QUOT) }}"
     aria-hidden="true"></div>
@endsection
