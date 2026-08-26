@extends('dashboard.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6" dir="rtl">
    <x-admin.health-overview
        title="Health Check لنظام المديونية"
        description="واجهة قراءة فقط للدعم التقني تعرض نتائج فحص المديونيات والتحصيلات مثل صفحة فحص الأجل، بدون تعديل أو حذف أي بيانات."
        :total-issues="$totalIssues"
        :issues="$issues"
        :summary="$summary">
        <div class="rounded-2xl border ui-border ui-status-info-bg p-4 text-sm ui-status-info">
            <div class="font-bold">تنبيه أمان الاختبار</div>
            <p class="mt-1">هذه الصفحة تعرض نتائج استعلامات فحص فقط. لا يوجد بها أي زر إصلاح تلقائي، ولا تستخدم أوامر حذف أو تعديل على قاعدة البيانات.</p>
        </div>
    </x-admin.health-overview>

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
                    {{ number_format(count($rows)) }} نتيجة
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] divide-y divide-ui-border text-sm">
                    <thead class="ui-surface-muted-bg ui-text-muted">
                        <tr>
                            <th class="px-4 py-3 text-right">#</th>
                            <th class="px-4 py-3 text-right">المتجر</th>
                            <th class="px-4 py-3 text-right">الموظف</th>
                            <th class="px-4 py-3 text-right">أصل المديونية</th>
                            <th class="px-4 py-3 text-right">المبلغ</th>
                            <th class="px-4 py-3 text-right">الوصف</th>
                            <th class="px-4 py-3 text-right">تفاصيل</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ui-border ui-text-muted">
                        @forelse($rows as $row)
                            <tr class="ui-surface-muted-bg">
                                <td class="px-4 py-3 font-mono ui-text-caption ui-text-muted">
                                    @if(($row['type'] ?? null) === 'duplicate_collection')
                                        تكرار #{{ $row['id'] }}
                                    @elseif(($row['type'] ?? null) === 'collection')
                                        تحصيل #{{ $row['id'] }}
                                    @else
                                        مديونية #{{ $row['id'] }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $row['store'] }}</td>
                                <td class="px-4 py-3">{{ $row['employee'] }}</td>
                                <td class="px-4 py-3 font-mono ui-text-caption">{{ $row['debt_parent_id'] ? '#' . $row['debt_parent_id'] : '—' }}</td>
                                <td class="px-4 py-3 {{ ($row['amount'] ?? 0) < 0 ? 'ui-status-success' : 'ui-text-muted' }}">
                                    {{ number_format($row['amount'] ?? 0, 2) }} ريال
                                </td>
                                <td class="px-4 py-3">{{ $row['description'] ?: '—' }}</td>
                                <td class="px-4 py-3 ui-text-caption ui-text-muted">
                                    <div>الحالة: {{ $row['status'] ?? '—' }}</div>
                                    <div>تاريخ العملية: {{ $row['date'] ?? '—' }}</div>
                                    @isset($row['duplicate_count'])
                                        <div class="ui-status-warning">عدد السجلات المتكررة: {{ number_format($row['duplicate_count']) }}</div>
                                        <div>أول سجل: #{{ $row['first_id'] }}</div>
                                    @endisset
                                    @isset($row['parent_amount'])
                                        <div class="ui-status-danger">مبلغ الأصل الحالي: {{ number_format($row['parent_amount'], 2) }} ريال</div>
                                        <div>حالة الأصل: {{ $row['parent_status'] ?? '—' }}</div>
                                    @endisset
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
