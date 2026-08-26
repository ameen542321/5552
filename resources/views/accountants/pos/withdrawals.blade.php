@extends('dashboard.app')
@section('title', 'إضافة سحب نقدي')

@section('content')
<div class="max-w-7xl mx-auto px-3 sm:px-4 py-4 sm:py-6" dir="rtl">
    <x-employee.operation-page-header
        title="سحب نقدي"
        subtitle="إدارة سلفيات ومسحوبات الموظفين من واجهة موحدة"
        :subtitle-as-help="true"
        icon="س"
        accent="warning"
    />

    <div class="space-y-4">

        {{-- قسم اختيار الموظف --}}
        <div class="w-full">
            <div class="ui-card p-4 shadow-sm"> {{-- تقليل padding --}}

                {{-- شريط البحث والعنوان --}}
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 mb-3">
                    <h2 class="text-base font-bold ui-title flex items-center gap-1">
                        <span class="w-1.5 h-4 ui-dot-info rounded-full"></span>
                        قائمة الموظفين
                    </h2>
                    <div class="relative w-full sm:w-56">
                        <input type="text" id="employeeSearch" data-ui-filter-input="employeesList"
                               placeholder="بحث..."
                               class="ui-input text-sm py-1.5 px-3">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3" id="employeesList"> {{-- تقليل المسافات بين الكروت --}}
                    @foreach($people as $emp)
                    <div class="employee-card ui-card-muted rounded-xl p-3 ui-hover-surface transition-all duration-300 group"
                         data-name="{{ $emp->name }}" data-ui-filter-value="{{ $emp->name }}">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-8 h-8 rounded-full ui-surface-strong-bg border ui-border flex items-center justify-center ui-status-info font-bold text-sm shadow-inner flex-shrink-0">
                                    {{ mb_substr($emp->name, 0, 1) }}
                                </div>
                                <div class="min-w-0">
                                    <h3 class="ui-title text-sm font-medium truncate transition-colors">{{ $emp->name }}</h3>
                                </div>
                            </div>
                            {{-- إصلاح مطبق: اختيار الموظف وتجهيز المودال والبحث والإرسال الأحادي تستخدم ui-actions. --}}
                            <button type="button" data-ui-edit-form="withdrawalForm" data-ui-show="withdrawalModal" data-ui-scroll-lock
                                    data-id="{{ $emp->id }}" data-name="{{ $emp->name }}"
                                    class="ui-btn ui-btn-warning px-3 py-2 flex-shrink-0">
                                سحب
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- قسم السجل الجانبي --}}
        <div class="w-full">
            <div class="ui-card rounded-2xl p-4 sticky top-4 shadow-sm"> {{-- تقليل padding --}}
                <h2 class="text-base font-bold ui-title mb-3 flex items-center gap-1">
                    <span class="ui-status-warning text-lg">🕒</span>
                    آخر 10 عمليات
                </h2>

                <div class="space-y-3 max-h-[500px] overflow-y-auto custom-scrollbar pr-1"> {{-- تقليل المسافات --}}
                    @forelse($lastWithdrawals as $w)
                    <div class="group relative ui-frame-row ui-hover-surface transition-colors">
                        <div class="min-w-0 w-full">
                        <div class="flex justify-between items-start">
                            <h4 class="ui-title ui-text-caption font-medium transition-colors">{{ $w->person->name ?? '—' }}</h4>
                            <span class="ui-status-success font-bold ui-text-caption">{{ number_format($w->amount) }} ﷼</span>
                        </div>
                        {{-- تاريخ التشغيل مقدم، ثم تاريخ العملية القديم، ثم تاريخ الإدخال للسجلات الناقصة. --}}
                        <p class="ui-text-muted ui-text-caption mt-0.5">{{ optional($w->business_date ?? $w->date ?? $w->created_at)->format('Y-m-d') }}</p>
                        @if(!empty($w->description))
                            <p class="ui-text-soft ui-text-caption ui-surface-strong-bg p-2 rounded-lg mt-2 italic">
                                {{ Str::limit($w->description, 40) }}
                            </p>
                        @endif
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-6 opacity-40">
                        <p class="ui-text-soft ui-text-caption italic">لا توجد سجلات</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- المودال المصغر والمضبوط --}}
<div id="withdrawalModal"
     class="ui-modal-backdrop hidden"
     >

    <div class="ui-modal-panel w-full max-w-lg max-h-[90vh] overflow-y-auto">

        {{-- رأس المودال المصغر --}}
        <div class="ui-modal-header">
            <div>
                <div class="flex items-center gap-2"><h3 class="text-lg font-bold ui-title">تسجيل سحب على موظف</h3><x-ui.help title="سحب موظف" body="اختر الموظف ثم سجل المبلغ والتاريخ والملاحظة. يحفظ النظام منفذ العملية تلقائيًا." /></div>
                <p class="ui-text-soft ui-text-caption mt-0.5" id="empNameDisplay" data-ui-fill-text="name"></p>
            </div>
            <button type="button" data-ui-hide="withdrawalModal" data-ui-reset-form="withdrawalForm" data-ui-scroll-unlock
                    class="ui-modal-close-danger flex items-center justify-center" aria-label="إغلاق">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- محتوى الفورم المصغر --}}
        <form id="withdrawalForm" method="POST" data-ui-action-template="{{ route('accountant.pos.withdrawal.store', ['employee' => '__ID__']) }}"
              data-ui-single-submit data-ui-busy-text="جاري الحفظ..." class="p-5 space-y-4">
            @csrf
            <input type="hidden" name="employee_id" id="employeeId" data-ui-fill="id">

            {{-- حقل المبلغ --}}
            <div class="space-y-1">
                <label class="block ui-text-caption font-medium ui-text-soft">المبلغ</label>
                <div class="relative">
                    <input type="number" name="amount" step="0.01" min="0.1" required
                           class="ui-input w-full p-3 pl-8 text-base font-bold"
                           placeholder="0.00">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 ui-text-muted ui-text-caption font-bold">﷼</span>
                </div>
            </div>

            {{-- حقل التاريخ المحسّن --}}
            <div class="space-y-1">
                <label class="block ui-text-caption font-medium ui-text-soft">التاريخ</label>
                <input type="date" name="date"
                       value="{{ date('Y-m-d') }}"
                       max="{{ date('Y-m-d') }}"
                       required
                       class="ui-input ui-date-input w-full p-3"
                       >
            </div>

            {{-- حقل الملاحظات --}}
            <div class="space-y-1">
                <label class="block ui-text-caption font-medium ui-text-soft">ملاحظات (اختياري)</label>
                <textarea name="description" rows="2"
                          class="ui-input w-full p-3 resize-none"
                          placeholder="تفاصيل إضافية..."></textarea>
            </div>

            {{-- الأزرار المصغرة --}}
            <div class="flex gap-2 pt-2">
                <button type="submit"
                        class="ui-btn ui-btn-warning flex-1 py-3 disabled:opacity-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    حفظ
                </button>
                <button type="button" data-ui-hide="withdrawalModal" data-ui-reset-form="withdrawalForm" data-ui-scroll-unlock
                        class="ui-btn ui-btn-danger flex-1 py-3">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
