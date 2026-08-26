@props(['employee', 'modalId' => 'debtModal'])
@php
    // تحديد الموديل (المحاسب أو الموظف)
    $model = $person ?? $employee;
    $finalModalId = $modalId ?? "debtOperationsModal-{$model->id}";

    // التصحيح: جلب مديونيات هذا الشخص فقط!
    // نستخدم $model->debts() لضمان أننا نبحث فقط في العمليات المرتبطة به
    $operations = $model ? $model->debts()
        ->where('amount', '>', 0)
        ->orderByDesc('date')
        ->get() : collect();
@endphp

<div id="{{ $finalModalId }}"
     x-data="{ activeOpId: null, partialDebtAmount: null }"
     class="ui-modal-backdrop hidden">

    <div class="w-full max-w-2xl px-4">
        <div class="ui-modal-panel shadow-2xl rounded-2xl overflow-hidden">

            {{-- 1. الهيدر --}}
            <div class="ui-modal-header">
                <h2 class="text-xl font-bold ui-title flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice-dollar ui-status-danger"></i>
                    مديونيات: {{ $model->name }}
                </h2>
                {{-- إصلاح مطبق: إغلاق مودال المديونية يعتمد إجراء الإخفاء المشترك. --}}
                <button type="button" data-ui-hide="{{ $finalModalId }}" class="ui-modal-close-danger flex items-center justify-center" aria-label="إغلاق">&times;</button>
            </div>

            <div class="p-4 sm:p-6 space-y-6 sm:space-y-8">

                {{-- 2. قسم الإضافة (التسجيل الجديد) --}}
                <section>
                    <h3 class="ui-text-caption font-bold ui-text-muted uppercase tracking-widest mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 ui-dot-danger rounded-full"></span>
                        تسجيل مديونية جديدة
                    </h3>
                    <form method="POST" action="{{ route('user.employees.debt.store', $model->id) }}" class="grid grid-cols-1 gap-4 ui-surface-muted-bg p-4 rounded-xl border ui-border">
                        @csrf
                        <div>
                            <label class="block ui-text-caption ui-text-soft mb-1">المبلغ</label>
                            <input type="number" name="amount" step="0.01" required class="ui-input w-full rounded-xl px-3 py-2">
                        </div>
                        <div>
                            <label class="block ui-text-caption ui-text-soft mb-1">التاريخ</label>
                            <input type="date" name="date" value="{{ now()->toDateString() }}" class="ui-input w-full rounded-xl px-3 py-2">
                        </div>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input type="text" name="description" placeholder="اسم العملية / الملاحظة (مثلاً: سلفة، عجز عهدة...)" class="ui-input flex-1 rounded-xl px-3 py-2 text-sm">
                            <button class="ui-btn ui-btn-primary px-6 py-2 rounded-xl font-bold transition">حفظ</button>
                        </div>
                    </form>
                </section>

                <div class="ui-section-divider ui-section-divider-sm"></div>

                {{-- 3. قسم التحصيل (السداد) --}}
                <section>
                    <h3 class="ui-text-caption font-bold ui-text-muted uppercase tracking-widest mb-4 flex items-center gap-2">
                        <span class="w-2 h-2 ui-dot-success rounded-full"></span>
                        المديونيات القائمة (للتحصيل)
                    </h3>

                    <div class="space-y-3 max-h-64 overflow-y-auto pr-2 custom-scrollbar">
                        @forelse($operations as $op)
                            <div class="ui-card-muted rounded-xl p-4">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <div class="ui-title font-bold">{{ number_format($op->amount, 2) }} ر.س</div>
                                        <div class="ui-text-caption ui-text-muted">{{ $op->date }}</div>
                                        @if(filled($op->description))
                                            <div class="ui-text-caption ui-text-soft mt-0.5">{{ $op->description }}</div>
                                        @endif
                                    </div>
                                    <button @click="activeOpId = (activeOpId === {{ $op->id }} ? null : {{ $op->id }})"
                                            class="ui-btn ui-btn-secondary ui-status-success px-4 py-1.5 rounded-xl ui-text-caption font-bold ui-hover-success-bg transition">
                                        تحصيل
                                    </button>
                                </div>

                                {{-- خيارات التحصيل الجزئي والكامل (Alpine) --}}
                                <div x-show="activeOpId === {{ $op->id }}" x-transition class="ui-section-divider space-y-3" x-cloak>
                                    <button type="button" data-employee-debt-collect
                                            data-action="{{ route('user.employees.debt.collect.full', $op->id) }}"
                                            data-amount="{{ (float) $op->amount }}" data-full="true"
                                            data-confirm-title="تأكيد سداد المديونية كاملة؟"
                                            data-confirm-text="سيتم تصفير هذه المديونية وتسجيل التحصيل الكامل." class="ui-btn ui-btn-success w-full justify-center py-2 rounded-xl ui-text-caption font-bold">سداد كامل</button>

                                    <button type="button" data-employee-debt-collect
                                            data-action="{{ route('user.employees.debt.collect.partial', $op->id) }}"
                                            data-amount="{{ (float) $op->amount }}" data-full="false"
                                            data-confirm-title="تأكيد التحصيل الجزئي؟"
                                            data-confirm-text="سيتم تسجيل التحصيل حسب طريقة الدفع المختارة." class="ui-btn ui-btn-warning w-full justify-center px-4 py-2 rounded-xl ui-text-caption font-bold">تحصيل جزئي</button>

                                    @if(!$op->collections()->exists())
                                        {{-- إصلاح مطبق: تأكيد تعديل المديونية يمر عبر عقد الحوارات المشترك بدل JavaScript داخل Blade. --}}
                                        <form method="POST" action="{{ route('user.employees.debt.update', $op->id) }}" class="grid grid-cols-1 gap-2 rounded-xl ui-border ui-status-info-bg p-3"
                                              data-ui-confirm="تعديل المديونية متاح للمالك فقط قبل تسجيل أي تحصيل. هل تريد الحفظ؟"
                                              data-ui-confirm-title="تأكيد تعديل المديونية">
                                            @csrf
                                            @method('PATCH')
                                            <div class="rounded-lg ui-border ui-surface-muted-bg p-2 ui-text-caption leading-5 ui-status-info">
                                                بطاقة تعديل المديونية مخصصة لتصحيح خطأ إدخال قبل أي تحصيل فقط، مثل تعديل 40 أو 60 إلى 50. بعد تسجيل أي تحصيل تتحول المديونية إلى سجل تراكمي ولا يتم تعديل أصلها.
                                            </div>
                                            <input type="number" name="amount" min="0.01" step="0.01" value="{{ $op->amount }}" class="ui-input ui-text-caption rounded-xl px-3 py-2 outline-none" required>
                                            <input type="date" name="date" value="{{ optional($op->date)->toDateString() }}" class="ui-input ui-text-caption rounded-xl px-3 py-2 outline-none" required>
                                            <input type="text" name="description" value="{{ $op->description }}" placeholder="اسم العملية / الملاحظة" class="ui-input ui-text-caption rounded-xl px-3 py-2 outline-none">
                                            <button type="submit" class="ui-btn ui-btn-primary justify-center py-2 rounded-xl ui-text-caption font-bold transition">حفظ تعديل المديونية</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-6 ui-text-muted italic text-sm">لا توجد مديونيات حالية</div>
                        @endforelse
                    </div>
                </section>

            </div>
        </div>
    </div>
</div>
{{-- علامة تفعيل لوحدة مديونية الموظف؛ الحقول والمسارات تبقى صادرة من الخادم كما هي. --}}
<div class="hidden" data-employee-debt-interface aria-hidden="true"></div>
