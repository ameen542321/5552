@extends('dashboard.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6" dir="rtl">
    <x-admin.health-overview
        title="Health Check لنظام الأجل"
        description="واجهة فحص للدعم التقني تعرض مشاكل بيانات الأجل والتحصيلات التي تحتاج مراجعة قبل أن تؤثر على التقارير أو الموازنة."
        :total-issues="$totalIssues"
        :issues="$issues"
        :summary="$summary"
        summary-columns="xl:grid-cols-4" />

    @foreach($issues as $key => $issue)
        @php
            $rows = $issue['rows'];
        @endphp
        <section id="issue-{{ $key }}" class="rounded-2xl border ui-border ui-surface-muted-bg shadow-lg">
            <div class="flex flex-col gap-2 border-b ui-border p-5 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-bold ui-title">{{ $issue['title'] }}</h2>
                    <p class="mt-1 text-sm ui-text-muted">{{ $issue['hint'] }}</p>
                </div>
                <span class="w-fit rounded-full {{ $issue['severity'] === 'danger' ? 'ui-status-danger-bg ui-status-danger' : 'ui-status-warning-bg ui-status-warning' }} px-4 py-2 text-sm font-bold">
                    {{ number_format(count($rows)) }} مشكلة
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] divide-y divide-ui-border text-sm">
                    <thead class="ui-surface-muted-bg ui-text-muted">
                        <tr>
                            <th class="px-4 py-3 text-right">#</th>
                            <th class="px-4 py-3 text-right">المتجر</th>
                            <th class="px-4 py-3 text-right">الموظف</th>
                            <th class="px-4 py-3 text-right">اسم عملية الأجل</th>
                            <th class="px-4 py-3 text-right">sale_id</th>
                            <th class="px-4 py-3 text-right">المبلغ</th>
                            <th class="px-4 py-3 text-right">تفاصيل</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ui-border ui-text-muted">
                        @forelse($rows as $row)
                            <tr class="ui-surface-muted-bg">
                                <td class="px-4 py-3 font-mono ui-text-caption ui-text-muted">
                                    {{ $row['type'] === 'collection' ? 'تحصيل #' . $row['id'] : 'أجل #' . $row['id'] }}
                                </td>
                                <td class="px-4 py-3">{{ $row['store'] }}</td>
                                <td class="px-4 py-3">{{ $row['employee'] }}</td>
                                <td class="px-4 py-3">{{ $row['credit_note'] ?: '—' }}</td>
                                <td class="px-4 py-3 font-mono ui-text-caption">{{ $row['sale_id'] ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    {{ number_format($row['amount'] ?? 0, 2) }} ريال
                                </td>
                                <td class="px-4 py-3 ui-text-caption ui-text-muted">
                                    @if(($row['type'] ?? null) === 'collection')
                                        <div>أجل مرتبط: #{{ $row['credit_sale_id'] }}</div>
                                        <div>كاش: {{ number_format($row['cash_amount'] ?? 0, 2) }} | شبكة: {{ number_format($row['card_amount'] ?? 0, 2) }}</div>
                                        <div>تاريخ التحصيل: {{ $row['collection_date'] ?? '—' }}</div>
                                        @isset($row['expected_amount'])
                                            <div class="ui-status-danger">مجموع الكاش والشبكة: {{ number_format($row['expected_amount'], 2) }}</div>
                                        @endisset
                                    @else
                                        <div>المتبقي الحالي: {{ number_format($row['remaining_amount'] ?? 0, 2) }} ريال</div>
                                        <div>الحالة: {{ $row['status'] ?? '—' }}</div>
                                        <div>تاريخ الأجل: {{ $row['date'] ?? '—' }}</div>
                                        @isset($row['collected_total'])
                                            <div>مجموع التحصيلات: {{ number_format($row['collected_total'], 2) }} ريال</div>
                                            <div class="ui-status-danger">المتبقي المتوقع: {{ number_format($row['expected_remaining'], 2) }} ريال</div>
                                        @endisset
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center ui-status-success">لا توجد مشاكل في هذا الفحص.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>
@endsection
