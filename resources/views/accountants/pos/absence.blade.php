@extends('dashboard.app')
@section('title', 'تسجيل غياب')

@section('content')
<div class="max-w-7xl mx-auto px-3 sm:px-4 py-4 sm:py-6" dir="rtl">
    <x-employee.operation-page-header
        title="تسجيل غياب"
        subtitle="إدارة غياب الموظفين من واجهة موحدة"
        :subtitle-as-help="true"
        icon="غ"
        accent="warning"
    />

    <div class="space-y-4">

        {{-- قسم اختيار الموظف --}}
        <div class="w-full">
            <div class="ui-card p-4 shadow-sm">

                {{-- شريط البحث --}}
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 mb-3">
                    <h2 class="text-base font-bold ui-title flex items-center gap-2">
                        <span class="w-2 h-5 ui-dot-danger rounded-full"></span>
                        قائمة الموظفين
                    </h2>
                    <div class="relative w-full sm:w-64">
                        <input type="text" id="employeeSearch" data-ui-filter-input="employeesList"
                               placeholder="بحث عن موظف..."
                               class="ui-input text-sm py-2 px-4">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4" id="employeesList">
                    @foreach($people as $emp)
                    <div class="employee-card ui-card-muted rounded-2xl p-4 ui-hover-surface transition-all duration-300 group"
                         data-name="{{ $emp->name }}" data-ui-filter-value="{{ $emp->name }}">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-11 h-11 rounded-full ui-surface-strong-bg border ui-border flex items-center justify-center ui-status-danger font-bold shadow-inner">
                                    {{ mb_substr($emp->name, 0, 1) }}
                                </div>
                                <div class="min-w-0">
                                    <h3 class="ui-title font-semibold truncate transition-colors">{{ $emp->name }}</h3>
                                </div>
                            </div>
                            {{-- إصلاح مطبق: اختيار الموظف وتجهيز المودال والبحث والإرسال الأحادي تستخدم ui-actions. --}}
                            <button type="button" data-ui-edit-form="absenceForm" data-ui-show="absenceModal" data-ui-scroll-lock
                                    data-id="{{ $emp->id }}" data-name="{{ $emp->name }}"
                                    class="ui-btn ui-btn-danger px-4 py-2 ui-text-caption">
                                تسجيل غياب
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- قسم السجل الجانبي --}}
        <div class="w-full">
            <div class="ui-card rounded-2xl p-4 sticky top-4 shadow-sm">
                <h2 class="text-lg font-bold ui-title mb-5 flex items-center gap-2">
                    <span class="ui-status-danger text-xl">🕒</span>
                    آخر 10 تسجيلات غياب
                </h2>

                <div class="space-y-4 max-h-[600px] overflow-y-auto custom-scrollbar">
                    @forelse($lastAbsences as $a)
                    <div class="group relative pr-4 border-r-2 ui-border ui-hover-surface transition-colors">
                        <div class="flex justify-between items-start">
                            <h4 class="ui-title font-medium text-sm transition-colors">{{ $a->person->name ?? '—' }}</h4>
                            <span class="ui-status-danger-bg ui-status-danger font-bold ui-text-caption px-2 py-0.5 rounded">غائب</span>
                        </div>
                        {{-- الغياب يستخدم تاريخ العملية، وتاريخ الإدخال بديل للسجلات القديمة فقط. --}}
                        <p class="ui-text-muted ui-text-caption mt-1">{{ optional($a->date ?? $a->created_at)->format('Y-m-d') }}</p>
                        @if(!empty($a->description))
                            <p class="ui-text-soft ui-text-caption ui-surface-strong-bg p-2 rounded-lg mt-2 italic">
                                {{ Str::limit($a->description, 50) }}
                            </p>
                        @endif
                    </div>
                    @empty
                    <div class="text-center py-10 opacity-40">
                        <p class="ui-text-soft text-sm italic">لا توجد سجلات غياب</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- المودال المحسّن للغياب --}}
<div id="absenceModal"
     class="ui-modal-backdrop hidden">

    <div class="fixed inset-0 ui-scrim backdrop-blur-md transition-opacity"
         data-ui-hide="absenceModal" data-ui-reset-form="absenceForm" data-ui-scroll-unlock></div>

    <div class="relative ui-modal-panel rounded-[2rem] shadow-2xl w-full max-w-lg
                max-h-[90vh] overflow-y-auto custom-scrollbar transform transition-all duration-300">

        <div class="ui-modal-header">
            <div>
                <h3 class="text-xl font-bold ui-title flex items-center gap-2">
                    <span class="w-2 h-6 ui-dot-danger rounded-full"></span>
                    تأكيد غياب موظف
                </h3>
                <p class="ui-text-soft ui-text-caption mt-0.5 uppercase tracking-widest font-medium" id="empNameDisplay" data-ui-fill-text="name" data-ui-prefix="للموظف: "></p>
            </div>
            <button type="button" data-ui-hide="absenceModal" data-ui-reset-form="absenceForm" data-ui-scroll-unlock
                    class="ui-modal-close-danger flex items-center justify-center" aria-label="إغلاق">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form id="absenceForm" method="POST" data-ui-action-template="{{ route('accountant.pos.absence.store', ['employee' => '__ID__']) }}"
              data-ui-single-submit data-ui-busy-text="جاري الحفظ..." class="p-6 sm:p-8 space-y-6">
            @csrf


           {{-- حقل التاريخ المطور والمتجاوب --}}
<div class="space-y-2 text-right">
    <label class="block text-sm font-semibold ui-text-soft mr-1">تاريخ الغياب</label>
    <div class="relative group">
        {{-- الحقل الفعلي --}}
        <input type="date" name="date"
               value="{{ date('Y-m-d') }}"
               max="{{ date('Y-m-d') }}"
               required
               class="ui-input ui-date-input w-full rounded-2xl p-4 pl-12 font-bold cursor-pointer relative z-10 appearance-none shadow-inner">

        {{-- الأيقونة المخصصة (توضع في جهة اليسار) --}}
        <div class="absolute left-4 top-1/2 -translate-y-1/2 flex items-center gap-2 pointer-events-none z-20">
            <span class="h-6 w-[1px] ui-surface-strong-bg mr-2"></span>
            <svg class="w-6 h-6 ui-status-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
        </div>
    </div>
</div>
            {{-- حقل الملاحظات --}}
            <div class="space-y-2">
                <label class="block text-sm font-semibold ui-text-soft mr-1">سبب الغياب (اختياري)</label>
                <textarea name="description" rows="3"
                          class="ui-input w-full rounded-2xl p-4 resize-none shadow-inner"
                          placeholder="مثال: ظرف طارئ، مرض، بدون إذن..."></textarea>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 pt-4">
                <button type="submit" id="submitBtn"
                        class="ui-btn ui-btn-danger flex-[2] py-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    تأكيد تسجيل الغياب
                </button>
                <button type="button" data-ui-hide="absenceModal" data-ui-reset-form="absenceForm" data-ui-scroll-unlock
                        class="ui-btn ui-btn-danger flex-1 py-4">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
