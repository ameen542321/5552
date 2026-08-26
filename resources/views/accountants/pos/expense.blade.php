@extends('dashboard.app')
@section('title', 'المصروف')

@section('content')
@php
    $isAccountant = $isAccountant ?? auth('accountant')->check();
    $storeRoute = $isAccountant ? route('accountant.pos.expense.store') : route('user.stores.expenses.store', $storeModel->id);
    $updateRouteTemplate = $isAccountant
        ? route('accountant.pos.expense.update', ['id' => '__ID__'])
        : route('user.stores.expenses.update', ['store' => $storeModel->id, 'id' => '__ID__']);
    $destroyRouteName = $isAccountant ? 'accountant.pos.expense.destroy' : 'user.stores.expenses.destroy';
    $exportRoute = $isAccountant ? null : route('user.stores.expenses.export-pdf', ['store' => $storeModel->id, 'month' => $month, 'year' => $year]);
    $filterRoute = $isAccountant ? route('accountant.pos.expense.page') : route('user.stores.expenses.index', $storeModel->id);
    $currentDateLabel = \Carbon\Carbon::parse($selectedAccountingDate ?? $currentBusinessDate ?? now())->format('Y-m-d');
@endphp

<div class="max-w-7xl mx-auto px-4 py-6 text-right space-y-5" dir="rtl" x-data="{ infoModal: null }">
    <x-employee.operation-page-header
        title="المصروف"
        :subtitle="$storeModel->name ?? 'المتجر'"
        icon="م"
        accent="warning"
        :back-route="$isAccountant ? 'accountant.dashboard' : 'user.dashboard'"
    />

    <div class="flex flex-wrap gap-2">
        @if($exportRoute)
            <a href="{{ $exportRoute }}" class="ui-btn ui-btn-warning px-4 py-2 text-sm">
                <i class="fa-solid fa-file-pdf"></i>
                تصدير PDF
            </a>
        @endif
        {{-- إصلاح مطبق: فتح وإغلاق مودالات المصروفات البسيطة يستخدمان ui-actions. --}}
        <button type="button" data-ui-show="expenseModal" class="ui-btn ui-btn-primary px-4 py-2 text-sm">
            <i class="fa-solid fa-plus"></i>
            إضافة مصروف
        </button>
    </div>

    <form method="GET" action="{{ $filterRoute }}" class="ui-card flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
        <label class="block flex-1">
            <span class="mb-1.5 flex items-center gap-2 ui-text-caption font-bold ui-title">
                اليوم
                <x-ui.help title="فلترة المصروفات" body="يعرض المصروفات المسجلة على تاريخ العمل المحدد، حتى لو أُدخلت بعد منتصف الليل." />
            </span>
            <input type="date" name="date" value="{{ $currentDateLabel }}" class="ui-input" data-ui-submit-on-change>
        </label>
    </form>

    <div class="grid grid-cols-2 gap-3 sm:gap-4">
        <div class="ui-card p-4">
            <p class="ui-text-caption font-bold ui-text-soft">مصروف اليوم</p>
            <p class="mt-1 ui-text-caption ui-text-muted">{{ $currentDateLabel }}</p>
            <p class="mt-3 text-xl sm:text-2xl font-black ui-status-success">{{ number_format($currentTotal ?? 0, 2) }} <span class="ui-text-caption ui-text-muted">ر.س</span></p>
        </div>
        <div class="ui-card p-4">
            <p class="ui-text-caption font-bold ui-text-soft">إجمالي مصروفات الشهر</p>
            <p class="mt-1 ui-text-caption ui-text-muted">{{ str_pad((string) $month, 2, '0', STR_PAD_LEFT) }}/{{ $year }}</p>
            <p class="mt-3 text-xl sm:text-2xl font-black ui-status-warning">{{ number_format($monthTotal ?? $total ?? 0, 2) }} <span class="ui-text-caption ui-text-muted">ر.س</span></p>
        </div>
    </div>

    <div class="ui-card overflow-hidden">
        <div class="flex items-center justify-between gap-3 px-4 py-3">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold ui-title">مصروفات اليوم</h2>
                    <x-ui.help title="مصروفات اليوم" body="تعتمد النتائج على تاريخ العمل للعملية وليس وقت إدخال السجل." />
                </div>
            </div>
            <span class="rounded-full border ui-border ui-surface-strong-bg px-3 py-1 ui-text-caption ui-text-soft">{{ number_format(($expenses ?? collect())->count()) }} عملية</span>
        </div>

        <div class="space-y-2 p-2">
            @forelse(($expenses ?? collect())->values() as $index => $expense)
                @php
                    $expenseDate = optional($expense->business_date)->format('Y-m-d') ?: optional($expense->created_at)->format('Y-m-d');
                    $expenseTime = optional($expense->created_at)->format('H:i');
                    $canMutate = (bool) ($expense->can_mutate ?? false);
                    $hasNotes = trim((string) ($expense->description ?? '')) !== '';
                @endphp
                <div x-data="{ expanded: false }" class="ui-card-muted rounded-xl">
                    <div class="w-full px-4 py-3 text-right ui-hover-surface transition rounded-xl">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full ui-surface-strong-bg ui-text-caption font-mono ui-text-soft">{{ $index + 1 }}</span>
                                <span class="min-w-0 truncate text-sm font-bold ui-title">{{ $expense->type ?: 'مصروف' }}</span>
                                @if($expense->locked_note && !$canMutate)
                                    <x-ui.help
                                        variant="warning"
                                        title="تنبيه العملية"
                                        body="العملية مرتبطة بموازنة مغلقة؛ للحذف والتعديل تواصل مع الإدارة."
                                    />
                                @endif
                            </div>
                            <button type="button" @click="expanded = !expanded" class="ui-btn ui-btn-secondary shrink-0 px-3 py-2" :aria-expanded="expanded.toString()" aria-label="عرض تفاصيل المصروف">
                                <span class="text-sm font-black ui-status-warning">{{ number_format($expense->amount, 2) }} ر.س</span>
                                <i class="fa-solid fa-chevron-down ui-text-soft transition" :class="expanded ? 'rotate-180' : ''"></i>
                            </button>
                        </div>
                    </div>
                    <div x-show="expanded" class="ui-surface-strong-bg px-4 py-3" x-cloak>
                        <div class="grid grid-cols-1 gap-3 ui-text-caption">
                            <div class="ui-card-muted p-3">
                                <span class="block ui-text-muted mb-1">التاريخ والوقت</span>
                                <span class="font-mono ui-title">{{ $expenseDate }} {{ $expenseTime }}</span>
                            </div>
                            <div class="ui-card-muted p-3">
                                <span class="block ui-text-muted mb-1">أضيف بواسطة</span>
                                <span class="font-bold ui-title">{{ $expense->creator_name ?? 'غير محدد' }}</span>
                            </div>
                            @if($hasNotes)
                                <div class="ui-card-muted p-3 md:col-span-2">
                                    <span class="block ui-text-muted mb-1">ملاحظات</span>
                                    <span class="ui-title leading-6">{{ $expense->description }}</span>
                                </div>
                            @endif
                            @if($canMutate)
                                <div class="ui-card-muted p-3 {{ $hasNotes ? '' : 'md:col-span-2' }}">
                                    <span class="block ui-text-muted mb-2">إجراءات</span>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" data-ui-edit-form="editExpenseForm" data-ui-show="editExpenseModal"
                                            data-id="{{ $expense->id }}"
                                            data-type="{{ $expense->type }}"
                                            data-amount="{{ $expense->amount }}"
                                            data-description="{{ $expense->description }}"
                                            class="ui-btn ui-btn-primary px-3 py-1.5 ui-text-caption">تعديل</button>
                                        {{-- إصلاح مطبق: تأكيد حذف المصروف موحد عبر عقد الحوار دون تغيير صلاحية الحذف أو المسار. --}}
                                        <form method="POST" action="{{ $isAccountant ? route($destroyRouteName, $expense->id) : route($destroyRouteName, ['store' => $storeModel->id, 'id' => $expense->id]) }}"
                                              data-ui-confirm="الحذف متاح فقط للمالك أو للمحاسب الذي أضاف المصروف قبل إغلاق الشفت."
                                              data-ui-confirm-title="تأكيد حذف المصروف">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ui-btn ui-btn-danger px-3 py-1.5 ui-text-caption">حذف</button>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center ui-text-soft">لا توجد مصروفات في هذه الفترة.</div>
            @endforelse
        </div>
    </div>
</div>

<div id="expenseModal" class="ui-modal-backdrop hidden">
    <div class="ui-modal-panel ui-modal-panel-transfer">
        <div class="ui-modal-header">
            <h3 class="text-sm font-bold ui-title">إضافة مصروف</h3>
            <button type="button" data-ui-hide="expenseModal" class="ui-modal-close-text-danger">إغلاق</button>
        </div>

        <form action="{{ $storeRoute }}" method="POST" class="p-3 space-y-3">
            @csrf
            @if($isAccountant)
                <input type="hidden" name="business_date" value="{{ $currentBusinessDate }}">
            @else
                <div>
                    <label class="block ui-text-caption ui-title font-bold mb-1">التاريخ</label>
                    <input type="date" name="business_date" value="{{ old('business_date', $selectedAccountingDate ?? $currentBusinessDate) }}" required class="ui-input rounded-lg p-2 text-sm">
                </div>
            @endif
            <div>
                <label class="block ui-text-caption ui-text-soft mb-1">اسم المصروف</label>
                <input type="text" name="type" list="expenseTypeOptions" required class="ui-input rounded-lg p-2 text-sm" placeholder="مثال: وقود / ضيافة / صيانة / نظافة">
                <datalist id="expenseTypeOptions">
                    <option value="وقود"></option>
                    <option value="ضيافة"></option>
                    <option value="صيانة"></option>
                    <option value="نظافة"></option>
                    <option value="وجبات"></option>
                </datalist>
            </div>
            <div>
                <label class="block ui-text-caption ui-text-soft mb-1">المبلغ</label>
                <input type="number" name="amount" step="0.01" min="0.01" required class="ui-input rounded-lg p-2 text-sm" placeholder="0.00">
            </div>
            <div>
                <label class="block ui-text-caption ui-text-soft mb-1">ملاحظات اختيارية</label>
                <textarea name="description" rows="2" class="ui-input rounded-lg p-2 text-sm" placeholder="تظهر فقط عند وجودها..."></textarea>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="submit" class="ui-btn ui-btn-primary flex-1 py-2 text-sm">حفظ</button>
            </div>
        </form>
    </div>
</div>

<div id="editExpenseModal" class="ui-modal-backdrop hidden">
    <div class="ui-modal-panel ui-modal-panel-transfer">
        <div class="ui-modal-header">
            <h3 class="text-sm font-bold ui-title">تعديل مصروف</h3>
            <button type="button" data-ui-hide="editExpenseModal" class="ui-modal-close-text-danger">إغلاق</button>
        </div>
        <form id="editExpenseForm" method="POST" data-ui-action-template="{{ $updateRouteTemplate }}" class="p-3 space-y-3">
            @csrf
            @method('PUT')
            <div>
                <label class="block ui-text-caption ui-text-soft mb-1">اسم المصروف</label>
                <input type="text" id="editType" name="type" data-ui-fill="type" required class="ui-input rounded-lg p-2 text-sm">
            </div>
            <div>
                <label class="block ui-text-caption ui-text-soft mb-1">المبلغ</label>
                <input type="number" id="editAmount" name="amount" data-ui-fill="amount" step="0.01" min="0.01" required class="ui-input rounded-lg p-2 text-sm">
            </div>
            <div>
                <label class="block ui-text-caption ui-text-soft mb-1">ملاحظات اختيارية</label>
                <textarea id="editDescription" name="description" data-ui-fill="description" rows="2" class="ui-input rounded-lg p-2 text-sm"></textarea>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="submit" class="ui-btn ui-btn-primary flex-1 py-2 text-sm">تحديث</button>
            </div>
        </form>
    </div>
</div>


{{-- عقد رسائل واجهة المصروف فقط؛ لا يغير عملية المصروف أو بياناتها. --}}
<div class="hidden" data-expense-interface-config="{{ json_encode(['flashMessage' => session('success') ?: session('error'), 'flashType' => session('success') ? 'success' : (session('error') ? 'error' : null)], JSON_HEX_APOS | JSON_HEX_QUOT) }}" aria-hidden="true"></div>
@endsection
