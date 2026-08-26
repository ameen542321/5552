@extends('dashboard.app')

@section('title', 'تقارير آخر 10 أيام - ' . $store->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 text-right" dir="rtl">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0 text-center sm:text-right">
            <h1 class="text-2xl font-bold ui-title">تقارير مبيعات آخر 10 أيام</h1>
            <p class="mt-1 break-words text-sm ui-text-muted">{{ $store->name }} — من {{ $cutoffDate->format('Y-m-d') }} إلى اليوم</p>
        </div>
        <a href="{{ route('user.stores.reports.index', $store->id) }}" class="ui-btn ui-btn-secondary ui-title inline-flex w-full items-center justify-center rounded-lg px-4 py-2 text-sm sm:w-auto">
            العودة لمركز التقارير
        </a>
    </div>

    <div class="ui-status-info-bg border ui-border rounded-xl p-3 mb-4 ui-status-info text-sm">
        تنبيه: هذه الملفات قد تُحذف تلقائياً بعد مرور 90 يوماً حسب سياسة النظام.
    </div>

    <div class="ui-surface-strong-bg overflow-x-auto rounded-2xl border ui-border">
        <table class="w-full min-w-[640px] text-sm">
            <thead class="ui-surface-muted-bg ui-text-soft">
                <tr>
                    <th class="p-3 text-right">اسم التقرير</th>
                    <th class="p-3 text-right">التاريخ</th>
                    <th class="p-3 text-right">الحجم</th>
                    <th class="p-3 text-right">الإجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                    <tr class="border-t ui-border ui-text-soft">
                        <td class="p-3">{{ $report['name'] }}</td>
                        <td class="p-3">{{ $report['business_date']->format('Y-m-d') }}</td>
                        <td class="p-3">{{ number_format($report['size_kb'], 2) }} KB</td>
                        <td class="p-3">
                            <a href="{{ $report['url'] }}" target="_blank" class="ui-btn ui-btn-primary ui-title px-3 py-1.5 rounded-lg ui-text-caption">
                                فتح التقرير
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="p-6 text-center ui-text-muted">لا توجد تقارير خلال آخر 10 أيام.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
