@extends('dashboard.app')

@section('title', 'تقرير الموظفين الشهري - ' . $store->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 text-right" dir="rtl" x-data="{ openEmployee: null }">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0 text-center sm:text-right">
            <h1 class="text-2xl font-bold ui-title">تقرير الموظفين الشهري</h1>
            <p class="mt-1 break-words text-sm ui-text-muted">{{ $store->name }} - تقرير واحد لجميع الموظفين</p>
        </div>
        <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:items-center">
            <a href="{{ route('user.stores.reports.employees.monthly.pdf', ['store' => $store->id, 'month' => $month]) }}" class="ui-btn ui-btn-danger ui-title inline-flex items-center justify-center rounded-lg px-3 py-2 text-center text-sm sm:px-4">
                تصدير PDF
            </a>
            <a href="{{ route('user.stores.reports.index', $store->id) }}" class="ui-btn ui-btn-secondary ui-title inline-flex items-center justify-center rounded-lg px-3 py-2 text-center text-sm sm:px-4">
                العودة لمركز التقارير
            </a>
        </div>
    </div>

    <form method="GET" class="mb-4 grid grid-cols-1 gap-3 sm:flex sm:items-end">
        <div class="w-full sm:w-auto">
            <label class="block ui-text-caption ui-text-muted mb-1">الشهر</label>
            <input type="month" name="month" value="{{ $month }}" class="ui-surface-muted-bg ui-border w-full rounded-lg px-3 py-2 ui-title text-sm sm:w-auto">
        </div>
        <button class="ui-btn ui-btn-primary ui-title w-full rounded-lg px-4 py-2 text-sm sm:w-auto">عرض</button>
    </form>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-4 text-sm">
        <div class="ui-surface-strong-bg border ui-border rounded-xl p-3"><p class="ui-text-muted">إجمالي الرواتب</p><p class="ui-status-info font-bold">{{ number_format($totals['salary'], 2) }} ر.س</p></div>
        <div class="ui-surface-strong-bg border ui-border rounded-xl p-3"><p class="ui-text-muted">إجمالي السحوبات</p><p class="ui-status-warning font-bold">{{ number_format($totals['withdrawals'], 2) }} ر.س</p></div>
        @if(($totals['credit_sales'] ?? 0) > 0 || ($totals['credit_collections'] ?? 0) > 0)
            <div class="ui-surface-strong-bg border ui-border rounded-xl p-3"><p class="ui-text-muted">بيع آجل / تحصيلات أجل</p><p class="ui-status-warning font-bold">{{ number_format($totals['credit_sales'], 2) }} / {{ number_format($totals['credit_collections'], 2) }} ر.س</p></div>
        @endif
        @if(($totals['debt_collections'] ?? 0) > 0)
            <div class="ui-surface-strong-bg border ui-border rounded-xl p-3"><p class="ui-text-muted">تحصيلات مديونية</p><p class="ui-status-info font-bold">{{ number_format($totals['debt_collections'], 2) }} ر.س</p></div>
        @endif
    </div>

    <div class="space-y-3">
        @forelse($rows as $index => $row)
            @php
                $isActive = in_array($row['status'] ?? '', ['active', 'نشط', 'فعال'], true);
                $employeeKey = 'employee-' . $index;
            @endphp
            <div class="rounded-2xl border ui-border ui-surface-strong-bg overflow-hidden">
                <button type="button" @click="openEmployee = openEmployee === '{{ $employeeKey }}' ? null : '{{ $employeeKey }}'" class="w-full p-4 flex items-center justify-between gap-3 text-right ui-surface-strong-bg transition">
                    <div class="min-w-0 flex items-center gap-3">
                        <span class="inline-flex h-3 w-3 rounded-full {{ $isActive ? 'ui-status-success-bg' : 'ui-status-danger-bg' }} shrink-0" title="{{ $isActive ? 'فعال' : 'موقوف' }}"></span>
                        <div class="min-w-0">
                            <p class="ui-title font-bold truncate">{{ $index + 1 }}. {{ $row['name'] }}</p>
                            <p class="ui-text-muted ui-text-caption">{{ $isActive ? 'فعال' : 'موقوف' }}</p>
                        </div>
                    </div>
                    <div class="text-left shrink-0">
                        <p class="ui-text-muted ui-text-caption">الراتب</p>
                        <p class="ui-status-info font-bold">{{ number_format($row['salary'], 2) }} ر.س</p>
                    </div>
                </button>

                <div x-show="openEmployee === '{{ $employeeKey }}'" class="border-t ui-border p-4">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                        <div class="rounded-xl ui-surface-muted-bg border ui-border p-3"><p class="ui-text-muted ui-text-caption">السحوبات</p><p class="ui-status-warning font-bold">{{ number_format($row['withdrawals'], 2) }} ر.س</p></div>
                        @if(($row['debts'] ?? 0) > 0)<div class="rounded-xl ui-surface-muted-bg border ui-border p-3"><p class="ui-text-muted ui-text-caption">المديونيات</p><p class="ui-status-info font-bold">{{ number_format($row['debts'], 2) }} ر.س</p></div>@endif
                        @if(($row['credit_sales'] ?? 0) > 0)<div class="rounded-xl ui-surface-muted-bg border ui-border p-3"><p class="ui-text-muted ui-text-caption">بيع آجل</p><p class="ui-status-warning font-bold">{{ number_format($row['credit_sales'] ?? 0, 2) }} ر.س</p></div>@endif
                        @if(($row['debt_collections'] ?? 0) > 0)<div class="rounded-xl ui-surface-muted-bg border ui-border p-3"><p class="ui-text-muted ui-text-caption">تحصيلات مديونية</p><p class="ui-status-info font-bold">{{ number_format($row['debt_collections'] ?? 0, 2) }} ر.س</p></div>@endif
                        @if(($row['credit_collections'] ?? 0) > 0)<div class="rounded-xl ui-surface-muted-bg border ui-border p-3"><p class="ui-text-muted ui-text-caption">تحصيلات أجل</p><p class="ui-status-info font-bold">{{ number_format($row['credit_collections'] ?? 0, 2) }} ر.س</p></div>@endif
                        <div class="rounded-xl ui-surface-muted-bg border ui-border p-3"><p class="ui-text-muted ui-text-caption">الغياب</p><p class="ui-status-danger font-bold">{{ number_format($row['absences_count'] ?? 0) }} يوم / {{ number_format($row['absence_penalty'] ?? 0, 2) }} ر.س</p></div>
                        <div class="rounded-xl ui-surface-muted-bg border ui-border p-3"><p class="ui-text-muted ui-text-caption">أيام العمل</p><p class="ui-text-soft font-bold">{{ number_format($row['worked_days'] ?? 0) }}</p></div>
                        <div class="rounded-xl ui-surface-muted-bg border ui-border p-3"><p class="ui-text-muted ui-text-caption">الباقي من الراتب</p><p class="ui-status-success font-bold">{{ number_format($row['net_salary'], 2) }} ر.س</p></div>
                        <div class="rounded-xl ui-surface-muted-bg border ui-border p-3"><p class="ui-text-muted ui-text-caption">نقل / تغيير راتب</p><p class="ui-text-soft font-bold">{{ number_format($row['transfers_count'] ?? 0) }} / {{ number_format($row['salary_changes_count'] ?? 0) }}</p></div>
                    </div>
                    @if(!empty($row['changes_summary']))
                        <p class="mt-3 ui-text-caption ui-text-muted ui-surface-muted-bg border ui-border rounded-xl p-3">{{ $row['changes_summary'] }}</p>
                    @endif

                    @if(!empty($row['debt_collection_rows']))
                        <div class="mt-4 rounded-xl border ui-border ui-status-info-bg overflow-hidden">
                            <div class="px-4 py-3 border-b ui-border">
                                <h3 class="text-sm font-bold ui-status-info">تفاصيل تحصيلات المديونية</h3>
                                <p class="ui-text-caption ui-text-muted mt-1">تظهر هنا التحصيلات بتاريخها ومبلغها ومن نفذ التحصيل. عند وصول أصل المديونية إلى صفر تختفي من قوائم المديونيات القائمة بدون حذف فعلي.</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full ui-text-caption">
                                    <thead class="ui-surface-muted-bg ui-text-muted">
                                        <tr>
                                            <th class="px-3 py-2 text-right">رقم التحصيل</th>
                                            <th class="px-3 py-2 text-right">أصل المديونية</th>
                                            <th class="px-3 py-2 text-right">التاريخ</th>
                                            <th class="px-3 py-2 text-right">المبلغ المحصل</th>
                                            <th class="px-3 py-2 text-right">من حصلها</th>
                                            <th class="px-3 py-2 text-right">طريقة التحصيل</th>
                                            <th class="px-3 py-2 text-right">الوصف</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-ui-border ui-text-soft">
                                        @foreach($row['debt_collection_rows'] as $collection)
                                            <tr>
                                                <td class="px-3 py-2 font-mono">#{{ $collection['id'] }}</td>
                                                <td class="px-3 py-2 font-mono">{{ $collection['parent_id'] ? '#' . $collection['parent_id'] : '—' }}</td>
                                                <td class="px-3 py-2">{{ $collection['date'] ?? '—' }}</td>
                                                <td class="px-3 py-2 ui-status-info font-bold">{{ number_format($collection['amount'] ?? 0, 2) }} ر.س</td>
                                                <td class="px-3 py-2">{{ $collection['collector'] ?? 'غير محدد' }}</td>
                                                <td class="px-3 py-2">{{ $collection['payment_method_label'] ?? 'كاش' }} <span class="ui-text-muted">({{ number_format($collection['cash_amount'] ?? 0, 2) }} كاش / {{ number_format($collection['card_amount'] ?? 0, 2) }} شبكة)</span></td>
                                                <td class="px-3 py-2">{{ $collection['description'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-6 text-center ui-text-muted ui-surface-strong-bg border ui-border rounded-2xl">لا يوجد موظفون أو بيانات لهذا الشهر.</div>
        @endforelse
    </div>
</div>
@endsection
