@extends('dashboard.app')

@section('title', 'البحث الشامل - ' . $store->name)

@section('content')
@php
    $money = fn ($value) => number_format((float) $value, 2);
    $searchLabel = $search !== '' ? $search : 'كل العمليات في الفترة';
    $scopeOptions = [
        'all' => 'كل العمليات',
        'sales' => 'عمليات البيع',
        'withdrawals' => 'السحوبات',
        'debts' => 'المديونيات',
        'credit_sales' => 'بيع آجل',
        'debt_collections' => 'تحصيلات مديونية',
        'credit_collections' => 'تحصيلات آجل',
        'expenses' => 'مصروفات',
        'absences' => 'غيابات',
        'internal' => 'استهلاك داخلي',
        'purchases' => 'مشتريات مالك',
        'products' => 'منتجات',
    ];
    $scopeResultDescriptions = [
        'sales' => 'تظهر نتائج عمليات البيع فقط.',
        'withdrawals' => 'تظهر نتائج السحوبات فقط.',
        'debts' => 'تظهر نتائج المديونيات فقط.',
        'credit_sales' => 'تظهر نتائج البيع الآجل فقط.',
        'debt_collections' => 'تظهر نتائج تحصيلات المديونية فقط.',
        'credit_collections' => 'تظهر نتائج تحصيلات الأجل فقط.',
        'expenses' => 'تظهر نتائج المصروفات فقط.',
        'absences' => 'تظهر نتائج الغيابات فقط.',
        'internal' => 'تظهر نتائج استهلاك المحاسب فقط.',
        'purchases' => 'تظهر نتائج مشتريات المالك فقط.',
        'products' => 'تظهر نتائج المنتجات فقط.',
    ];
    $summaryCards = [
        ['scope' => 'sales', 'label' => 'مبيعات', 'total' => $summary['sales_total'], 'count' => $summary['sales_count'], 'icon' => 'fa-cart-shopping'],
        ['scope' => 'withdrawals', 'label' => 'سحوبات', 'total' => $summary['withdrawals_total'], 'count' => $summary['withdrawals_count'], 'icon' => 'fa-money-bill-transfer'],
        ['scope' => 'debts', 'label' => 'مديونيات', 'total' => $summary['debts_total'], 'count' => $summary['debts_count'], 'icon' => 'fa-file-invoice-dollar'],
        ['scope' => 'credit_sales', 'label' => 'بيع آجل', 'total' => $summary['credit_sales_total'], 'count' => $summary['credit_sales_count'], 'icon' => 'fa-clock'],
        ['scope' => 'debt_collections', 'label' => 'تحصيل مديونية', 'total' => $summary['debt_collections_total'], 'count' => $summary['debt_collections_count'], 'icon' => 'fa-hand-holding-dollar'],
        ['scope' => 'credit_collections', 'label' => 'تحصيل آجل', 'total' => $summary['credit_collections_total'], 'count' => $summary['credit_collections_count'], 'icon' => 'fa-sack-dollar'],
        ['scope' => 'expenses', 'label' => 'مصروفات', 'total' => $summary['expenses_total'], 'count' => $summary['expenses_count'], 'icon' => 'fa-receipt'],
        ['scope' => 'absences', 'label' => 'غيابات', 'total' => $summary['absences_total'], 'count' => $summary['absences_count'], 'icon' => 'fa-user-clock'],
        ['scope' => 'internal', 'label' => 'استهلاك داخلي', 'total' => $summary['internal_total'], 'count' => $summary['internal_count'], 'icon' => 'fa-box-open'],
        ['scope' => 'purchases', 'label' => 'مشتريات مالك', 'total' => $summary['owner_purchases_total'], 'count' => $summary['owner_purchases_count'], 'icon' => 'fa-basket-shopping'],
        ['scope' => 'products', 'label' => 'منتجات', 'total' => $summary['products_total'], 'count' => $summary['products_count'], 'icon' => 'fa-boxes-stacked'],
    ];
@endphp

<div class="max-w-7xl mx-auto px-3 py-5 sm:px-4 sm:py-6 text-right" dir="rtl" x-data="{ open: false, summaryOpen: false, modal: {}, show(data) { this.modal = data; this.open = true }, close() { this.open = false } }">
    <div class="mb-5 rounded-3xl border ui-border ui-card p-4 shadow-2xl sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-start gap-3">
                <a href="{{ route('user.stores.reports.index', $store->id) }}" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border ui-border ui-surface-strong-bg ui-text-soft transition ui-title">
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
                <div class="flex min-w-0 items-center gap-2">
                    <h1 class="text-center text-2xl font-black ui-title sm:text-right">البحث الشامل</h1>
                    <x-ui.help title="نطاق البحث الشامل" body="بحث دقيق داخل البيع، شغل اليد والملاحظات، السحوبات، المصاريف، الغياب، المديونيات، التحصيلات، استهلاك المحاسب، مشتريات المالك والمنتجات." />
                </div>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('user.stores.reports.search', $store->id) }}" class="mb-5 rounded-3xl border ui-border ui-surface-strong-bg p-4 sm:p-5">
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <label for="q" class="mb-2 block ui-text-caption font-bold ui-text-muted">كلمة البحث</label>
                <input id="q" name="q" value="{{ $search }}" type="text" autofocus placeholder="مثال: احمر، احممر، رش بويا، اسم منتج، ملاحظة..." class="w-full rounded-2xl border ui-border ui-surface-muted-bg px-4 py-3 ui-title transition   ">
            </div>
            <div class="lg:col-span-3">
                <label for="scope" class="mb-2 block ui-text-caption font-bold ui-text-muted">نوع العملية</label>
                <select id="scope" name="scope" class="w-full rounded-2xl border ui-border ui-surface-muted-bg px-4 py-3 ui-title transition   ">
                    @foreach($scopeOptions as $scopeValue => $scopeLabel)
                        <option value="{{ $scopeValue }}" @selected($scope === $scopeValue)>{{ $scopeLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="lg:col-span-2">
                <label for="from" class="mb-2 block ui-text-caption font-bold ui-text-muted">من</label>
                <input id="from" name="from" value="{{ $from }}" type="date" class="w-full rounded-2xl border ui-border ui-surface-muted-bg px-4 py-3 ui-title transition   ">
            </div>
            <div class="lg:col-span-2">
                <label for="to" class="mb-2 block ui-text-caption font-bold ui-text-muted">إلى</label>
                <input id="to" name="to" value="{{ $to }}" type="date" class="w-full rounded-2xl border ui-border ui-surface-muted-bg px-4 py-3 ui-title transition   ">
            </div>
        </div>
        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl ui-btn ui-btn-primary px-6 py-3 font-bold ui-title transition ui-hover-info-bg sm:w-auto"><i class="fa-solid fa-search"></i>بحث</button>
            <a href="{{ route('user.stores.reports.search', $store->id) }}" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl ui-surface-muted-bg px-6 py-3 font-bold ui-text-soft transition ui-hover-info-bg sm:w-auto"><i class="fa-solid fa-rotate-right"></i>مسح</a>
        </div>
    </form>

    <section class="mb-5 overflow-hidden rounded-3xl border ui-border ui-surface-strong-bg">
        <button type="button" @click="summaryOpen = !summaryOpen" class="flex w-full flex-col gap-3 p-4 text-right transition ui-surface-muted-bg sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div>
                <h2 class="text-lg font-black ui-title">ملخص البحث</h2>
                <p class="mt-1 text-sm ui-text-muted">{{ $searchLabel }} — {{ $scopeOptions[$scope] ?? 'كل العمليات' }} — من {{ $from }} إلى {{ $to }}</p>
            </div>
            <div class="flex max-w-full flex-wrap items-center gap-2 self-start sm:self-auto">
                <span class="inline-flex items-center gap-2 rounded-full border ui-border ui-status-info-bg px-4 py-2 text-sm font-bold ui-status-info">{{ number_format($summary['selected_operations_count']) }} نتيجة</span>
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full border ui-border ui-surface-muted-bg ui-status-info" title="معاينة البطاقات">
                    <i class="fa-solid fa-eye" x-show="!summaryOpen"></i>
                    <i class="fa-solid fa-eye-slash" x-show="summaryOpen" x-cloak></i>
                </span>
            </div>
        </button>
        <div x-show="summaryOpen" class="grid grid-cols-2 gap-2 border-t ui-border p-4 md:grid-cols-3 xl:grid-cols-6 2xl:grid-cols-11">
            @foreach($summaryCards as $card)
                @if($scope === 'all' || $scope === $card['scope'])
                    <div class="rounded-2xl border ui-border ui-surface-muted-bg p-3">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <p class="ui-text-caption ui-text-muted">{{ $card['label'] }}</p>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl ui-surface-strong-bg ui-status-info"><i class="fa-solid {{ $card['icon'] }} ui-text-caption"></i></span>
                        </div>
                        <p class="truncate text-sm font-black ui-title">{{ $money($card['total']) }}</p>
                        <p class="mt-1 ui-text-caption ui-text-muted">{{ number_format($card['count']) }} عملية</p>
                    </div>
                @endif
            @endforeach
        </div>
    </section>

    <section class="overflow-hidden rounded-3xl border ui-border ui-surface-strong-bg shadow-xl">
        <div class="border-b ui-border p-4">
            <h2 class="text-lg font-black ui-title">النتائج</h2>
            <p class="mt-1 ui-text-caption ui-text-muted">{{ $scope === 'all' ? 'العرض مرتب: مبيعات، سحوبات، مديونيات، بيع آجل، تحصيلات، مصروفات، غيابات، استهلاك المحاسب، مشتريات المالك، منتجات.' : ($scopeResultDescriptions[$scope] ?? 'تظهر نتائج نوع العملية المحدد فقط.') }}</p>
        </div>

        <div class="grid gap-3 p-3 md:hidden">
            @forelse($unifiedOperations as $operation)
                <article class="rounded-2xl border ui-border ui-surface-muted-bg p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="inline-flex rounded-full border px-3 py-1 ui-text-caption font-bold {{ $operation['badge_class'] }}">{{ $operation['type_label'] }}</span>
                            <h3 class="mt-2 break-words text-base font-bold ui-title">{{ $operation['title'] }}</h3>
                            <p class="mt-1 ui-text-caption ui-text-muted">#{{ $operation['id'] }} — {{ $operation['display_date'] }}</p>
                        </div>
                        <button type="button" @click="show({type: @js($operation['type_label']), id: @js($operation['id']), title: @js($operation['title']), details: @js($operation['details'] ?: 'لا يوجد تفاصيل'), meta: @js($operation['meta']), amount: @js($money($operation['amount'])), date: @js($operation['display_date']), actor: @js($operation['actor'] ?? 'غير محدد')})" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border ui-border ui-status-info-bg ui-status-info transition ui-hover-info-bg"><i class="fa-solid fa-eye"></i></button>
                    </div>
                    <div class="mt-3 flex items-center justify-between rounded-xl ui-surface-strong-bg px-3 py-2">
                        <span class="ui-text-caption ui-text-muted">القيمة</span>
                        <span class="font-black ui-title">{{ $money($operation['amount']) }}</span>
                    </div>
                </article>
            @empty
                <div class="p-10 text-center ui-text-muted"><i class="fa-solid fa-magnifying-glass mb-3 text-3xl ui-text-muted"></i><p class="font-bold ui-title">لا توجد نتائج</p><p class="mt-1 text-sm">جرّب كلمة أخرى أو فترة مختلفة.</p></div>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="w-full min-w-[820px] text-sm">
                <thead class="ui-surface-muted-bg ui-text-soft">
                    <tr>
                        <th class="px-4 py-3 text-right">النوع</th>
                        <th class="px-4 py-3 text-right">الوقت</th>
                        <th class="px-4 py-3 text-right">العملية / الوصف</th>
                        <th class="px-4 py-3 text-right">القيمة</th>
                        <th class="px-4 py-3 text-center">التفاصيل</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ui-border">
                    @forelse($unifiedOperations as $operation)
                        <tr class="align-top transition ui-surface-muted-bg">
                            <td class="px-4 py-3"><span class="inline-flex rounded-full border px-3 py-1 ui-text-caption font-bold {{ $operation['badge_class'] }}">{{ $operation['type_label'] }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3 ui-text-soft">{{ $operation['display_date'] }}</td>
                            <td class="px-4 py-3"><p class="break-words font-bold ui-title">{{ $operation['title'] }}</p><p class="mt-1 ui-text-caption ui-text-muted">#{{ $operation['id'] }}</p></td>
                            <td class="whitespace-nowrap px-4 py-3 font-black ui-title">{{ $money($operation['amount']) }}</td>
                            <td class="px-4 py-3 text-center"><button type="button" @click="show({type: @js($operation['type_label']), id: @js($operation['id']), title: @js($operation['title']), details: @js($operation['details'] ?: 'لا يوجد تفاصيل'), meta: @js($operation['meta']), amount: @js($money($operation['amount'])), date: @js($operation['display_date']), actor: @js($operation['actor'] ?? 'غير محدد')})" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border ui-border ui-status-info-bg ui-status-info transition ui-hover-info-bg"><i class="fa-solid fa-eye"></i></button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-10 text-center ui-text-muted"><i class="fa-solid fa-magnifying-glass mb-3 text-3xl ui-text-muted"></i><p class="font-bold ui-title">لا توجد نتائج</p><p class="mt-1 text-sm">جرّب كلمة أخرى أو فترة مختلفة.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div x-show="open" x-cloak @keydown.escape.window="close()" class="ui-modal-backdrop">
        <div class="ui-modal-dismiss-layer" @click="close()"></div>
        <div x-show="open" x-transition class="ui-modal-panel ui-modal-panel-wide relative">
            <div class="ui-modal-header">
                <div><p class="ui-text-caption ui-text-muted" x-text="modal.type + ' #' + modal.id"></p><h3 class="mt-1 text-lg font-black ui-title" x-text="modal.title"></h3></div>
                <button type="button" @click="close()" class="ui-btn ui-btn-danger inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full ui-title" aria-label="إغلاق نافذة تفاصيل نتيجة البحث"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="space-y-3 p-4 text-sm">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl ui-surface-strong-bg p-3"><p class="ui-text-caption ui-text-muted">اسم العملية</p><p class="mt-1 font-bold ui-title" x-text="modal.type"></p></div>
                    <div class="rounded-2xl ui-surface-strong-bg p-3"><p class="ui-text-caption ui-text-muted">القيمة</p><p class="mt-1 font-black ui-status-info" x-text="modal.amount"></p></div>
                    <div class="rounded-2xl ui-surface-strong-bg p-3 sm:col-span-2"><p class="ui-text-caption ui-text-muted">المنفذ ووقت العملية</p><p class="mt-1 font-bold ui-text-soft"><span x-text="modal.actor"></span> — <span x-text="modal.date"></span></p></div>
                </div>
                <div class="rounded-2xl ui-surface-strong-bg p-3"><p class="mb-1 ui-text-caption ui-text-muted">التفاصيل</p><p class="whitespace-pre-line break-words leading-7 ui-text-soft" x-text="modal.details || 'لا يوجد تفاصيل'"></p></div>
                <div class="rounded-2xl ui-surface-strong-bg p-3"><p class="mb-1 ui-text-caption ui-text-muted">معلومات إضافية</p><p class="break-words leading-7 ui-text-soft" x-text="modal.meta || 'لا يوجد تفاصيل'"></p></div>
            </div>
        </div>
    </div>
</div>
@endsection
