@extends('dashboard.app')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold ui-title">لوحة الدعم التقني</h1>
    <p class="ui-text-soft text-sm mt-1">نظرة عامة على النظام (بيانات حقيقية)</p>
</div>

{{-- البطاقات الديناميكية --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

    {{-- عدد المستخدمين (أصحاب المتاجر) --}}
    <div class="ui-card p-5 transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="ui-text-soft text-sm">عدد المستخدمين</p>
                <h3 class="text-2xl font-bold ui-title mt-1">{{ number_format($stats['users_count']) }}</h3>
            </div>
            <div class="ui-stat-icon">
                <i class="fa-solid fa-users text-xl"></i>
            </div>
        </div>
    </div>

    {{-- عدد المحاسبين --}}
    <div class="ui-card p-5 transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="ui-text-soft text-sm">عدد المحاسبين</p>
                <h3 class="text-2xl font-bold ui-title mt-1">{{ number_format($stats['accountants_count']) }}</h3>
            </div>
            <div class="ui-stat-icon">
                <i class="fa-solid fa-user-tie text-xl"></i>
            </div>
        </div>
    </div>

    {{-- عدد المتاجر --}}
    <div class="ui-card p-5 transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="ui-text-soft text-sm">عدد المتاجر</p>
                <h3 class="text-2xl font-bold ui-title mt-1">{{ number_format($stats['stores_count']) }}</h3>
            </div>
            <div class="ui-stat-icon">
                <i class="fa-solid fa-store text-xl"></i>
            </div>
        </div>
    </div>

    {{-- الإشعارات --}}
    <div class="ui-card p-5 transition">
        <div class="flex items-center justify-between">
            <div>
                <p class="ui-text-soft text-sm">الإشعارات الجديدة</p>
                <h3 class="text-2xl font-bold ui-title mt-1">{{ number_format($stats['notifications_count']) }}</h3>
            </div>
            <div class="ui-stat-icon">
                <i class="fa-solid fa-bell text-xl"></i>
            </div>
        </div>
    </div>

</div>

{{-- آخر الأنشطة الديناميكية --}}
<div class="mt-10 ui-card p-6">
    <h2 class="text-xl font-semibold ui-title mb-4">آخر الأنشطة</h2>

    <ul class="space-y-3">
        @forelse($recent_activities as $log)
            <li class="ui-list-row pb-3">
                <span class="ui-text-soft">{{ $log->description }}</span>
                <span class="ui-text-muted text-sm">{{ \Carbon\Carbon::parse($log->created_at)->diffForHumans() }}</span>
            </li>
        @empty
            <li class="ui-text-muted text-center py-4">لا توجد أنشطة مسجلة حالياً</li>
        @endforelse
    </ul>
</div>

@endsection
