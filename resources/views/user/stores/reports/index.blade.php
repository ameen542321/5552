@extends('dashboard.app')

@section('title', 'التقارير - ' . $store->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 text-right" dir="rtl">
    <div class="mb-6 text-center sm:text-right">
        <h1 class="text-2xl font-bold ui-title">مركز التقارير</h1>
        <p class="mt-1 break-words text-sm ui-text-muted">{{ $store->name }}</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="{{ route('user.stores.reports.last-ten-days', $store->id) }}"
           class="block ui-card border ui-border rounded-2xl p-5 transition">
            <div class="flex items-center gap-3 mb-3">
                <i class="fas fa-chart-line ui-status-info text-xl"></i>
                <h2 class="ui-title font-bold">تقارير مبيعات آخر 10 أيام</h2>
            </div>
            <p class="ui-text-soft text-sm">عرض ملفات تقارير الإقفال المولدة خلال آخر 10 أيام.</p>
        </a>

        <a href="{{ route('user.stores.reports.monthly', $store->id) }}"
           class="block ui-card border ui-border rounded-2xl p-5 transition">
            <div class="flex items-center gap-3 mb-3">
                <i class="fas fa-calendar-alt ui-status-success text-xl"></i>
                <h2 class="ui-title font-bold">التقرير الشهري للمتجر</h2>
            </div>
            <p class="ui-text-soft text-sm">ملخص شهري شامل: مبيعات، استهلاك، رواتب، مصروفات وصافي النتيجة.</p>
        </a>

        <a href="{{ route('user.stores.reports.search', $store->id) }}"
           class="block ui-card border ui-border rounded-2xl p-5 transition">
            <div class="flex items-center gap-3 mb-3">
                <i class="fas fa-magnifying-glass-chart ui-status-info text-xl"></i>
                <h2 class="ui-title font-bold">تقرير بحث شامل للمتجر</h2>
            </div>
            <p class="ui-text-soft text-sm">ابحث بكلمة واحدة داخل المبيعات، استهلاك المحاسب، ومشتريات المالك خلال فترة محددة.</p>
        </a>

        <a href="{{ route('user.stores.reports.employees.monthly', $store->id) }}"
           class="block ui-card border ui-border rounded-2xl p-5 transition">
            <div class="flex items-center gap-3 mb-3">
                <i class="fas fa-users ui-status-info text-xl"></i>
                <h2 class="ui-title font-bold">تقرير الموظفين</h2>
            </div>
            <p class="ui-text-soft text-sm">رواتب وسحوبات ومديونيات وغيابات الموظفين حسب الشهر.</p>
        </a>
    </div>
</div>
@endsection
