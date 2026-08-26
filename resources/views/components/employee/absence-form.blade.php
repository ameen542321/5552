@props(['employee', 'modalId' => 'absenceModal'])

<div id="{{ $modalId }}"
     class="ui-modal-backdrop hidden">

    <div class="w-full max-w-3xl">
        <div class="ui-modal-panel rounded-2xl shadow-2xl p-4 sm:p-6 md:p-8 max-h-[90vh] overflow-y-auto">

            {{-- العنوان + زر الإغلاق (نفس ستايل إضافة محاسب) --}}
            <div class="ui-modal-header mb-6">
                <h2 class="text-xl sm:text-2xl font-bold ui-title">
                    تسجيل غياب — {{ $employee->name }}
                </h2>

                {{-- إصلاح مطبق: إغلاق المودال ومزامنة التاريخ يستخدمان عقود الإجراءات المشتركة. --}}
                <button type="button"
                        data-ui-hide="{{ $modalId }}"
                        class="ui-modal-close-danger flex items-center justify-center" aria-label="إغلاق">×</button>
            </div>

            {{-- الفورم بنفس المسافات والستايل --}}
            <form method="POST"
                  action="{{ route('user.employees.absence.store', $employee->id) }}"
                  class="space-y-6">

                @csrf

                {{-- التاريخ --}}
                <div>
                    <label class="block ui-text-soft font-medium mb-1">تاريخ الغياب</label>

                    <div class="relative">
                        <input type="text"
                               name="date"
                               id="dateInput-{{ $modalId }}"
                               value="{{ now()->toDateString() }}"
                               required
                               class="ui-input w-full rounded-xl px-10 py-2 cursor-pointer">

                        <input type="date"
                               id="hiddenDate-{{ $modalId }}"
                               class="absolute inset-0 opacity-0 cursor-pointer"
                               value="{{ now()->toDateString() }}"
                               data-ui-sync-value="dateInput-{{ $modalId }}">

                        <i class="fa-solid fa-calendar ui-text-muted absolute left-3 top-1/2 -translate-y-1/2"></i>
                    </div>
                </div>

                {{-- الوصف --}}
                <div>
                    <label class="block ui-text-soft font-medium mb-1">ملاحظة (اختياري)</label>
                    <div class="relative">
                        <input type="text" name="description"
                               class="ui-input w-full rounded-xl px-10 py-2">
                        <i class="fa-solid fa-align-right ui-text-muted absolute left-3 top-1/2 -translate-y-1/2"></i>
                    </div>
                </div>

                {{-- زر الحفظ --}}
                <div class="pt-2">
                    <button class="ui-btn ui-btn-danger w-full py-2.5 font-semibold justify-center">
                        <i class="fa-solid fa-check"></i>
                        تسجيل الغياب
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>
