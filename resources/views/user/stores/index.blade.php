@extends('dashboard.app')
@section('title', 'متاجري')

@section('content')

<div data-store-management>

<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-2xl font-bold ui-title">متاجري</h1>
        <p class="ui-text-muted text-sm mt-2">إدارة متاجرك ومتابعة أدائها</p>
    </div>

    {{-- التحقق من إمكانية الإضافة بناءً على الخطة --}}
    @php
        $user = auth()->user();
        $currentCount = $stores->count();
        $totalUsedFromPlan = $totalCountWithTrashed ?? $currentCount;
        $allowedStores = $user->plan->allowed_stores ?? $user->allowed_stores ?? 1;
        $canAdd = $totalUsedFromPlan < $allowedStores;
    @endphp

    @if($canAdd)
        <a href="{{ route('user.stores.create') }}"
           class="flex items-center gap-2 ui-card ui-title px-5 py-2.5 rounded-xl text-sm font-medium transition-all shadow-lg active:scale-[0.98]">
            <i class="fa-solid fa-plus text-sm"></i>
            <span>متجر جديد</span>
        </a>
    @else
        <div class="relative group">
            <button disabled
                    class="flex items-center gap-2 ui-surface-muted-bg ui-text-muted px-5 py-2.5 rounded-xl text-sm font-medium border ui-border cursor-not-allowed">
                <i class="fa-solid fa-lock text-sm"></i>
                <span>متجر جديد</span>
            </button>
            {{-- التنبيه أسفل ويمين الزر --}}
            <div class="absolute top-full right-0 mt-2 px-3 py-2 ui-surface-muted-bg ui-text-muted ui-text-caption rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none border ui-border shadow-xl z-50">
                <div class="absolute bottom-full right-4 -mb-1 border-4 border-transparent border-b-gray-900"></div>
                وصلت للحد الأقصى ({{ $allowedStores }})
                @if(($trashedCount ?? 0) > 0)
                <br><span class="ui-status-info">لديك متاجر في السلة تشغل مساحة</span>
                @endif
            </div>
        </div>
    @endif
</div>

{{-- بطاقة الإحصائيات السريعة --}}
@if($stores->count() > 0 || ($trashedCount ?? 0) > 0)
<div class="ui-card border ui-border rounded-2xl p-5 mb-6 shadow-xl shadow-black/20">
    @php
        $activeStores = $stores->where('status', 'active')->count();
        $suspendedStoresCount = $stores->where('status', 'suspended')->count();
        $trashedStoresCount = $trashedCount ?? 0;
        $usagePercent = $allowedStores > 0 ? min(100, round(($totalUsedFromPlan / $allowedStores) * 100)) : 0;
    @endphp

    <div class="mb-4">
        <div class="flex items-center justify-between ui-text-caption ui-text-muted mb-2">
            <span>استخدام الخطة</span>
            <span>{{ $usagePercent }}%</span>
        </div>
        <progress class="ui-progress" value="{{ $usagePercent }}" max="100" aria-label="نسبة استخدام الخطة">{{ $usagePercent }}%</progress>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="group relative overflow-hidden ui-surface-muted-bg backdrop-blur-sm border ui-border rounded-xl p-4 transition-all">
            <div class="absolute -right-8 -top-8 w-24 h-24 ui-status-info-bg rounded-full blur-xl"></div>
            <div class="relative flex items-start justify-between">
                <div>
                    <p class="ui-text-muted ui-text-caption mb-1">المساحة المستخدمة</p>
                    <h3 class="text-2xl font-bold ui-title">{{ $totalUsedFromPlan }} / {{ $allowedStores }}</h3>
                    <span class="ui-text-caption ui-status-info">الحد المسموح بالخطة</span>
                </div>
                <div class="w-10 h-10 ui-status-info-bg rounded-lg flex items-center justify-center border ui-border">
                    <i class="fa-solid fa-store ui-status-info"></i>
                </div>
            </div>
        </div>

        <div class="group relative overflow-hidden ui-surface-muted-bg backdrop-blur-sm border ui-border rounded-xl p-4 transition-all">
            <div class="absolute -right-8 -top-8 w-24 h-24 ui-status-success-bg rounded-full blur-xl"></div>
            <div class="relative flex items-start justify-between">
                <div>
                    <p class="ui-text-muted ui-text-caption mb-1">متاجر نشطة</p>
                    <h3 class="text-2xl font-bold ui-title">{{ $activeStores }}</h3>
                    <span class="ui-text-caption ui-status-success">تعمل بشكل طبيعي</span>
                </div>
                <div class="w-10 h-10 ui-status-success-bg rounded-lg flex items-center justify-center border ui-border">
                    <i class="fa-solid fa-check-circle ui-status-success"></i>
                </div>
            </div>
        </div>

        <div class="group relative overflow-hidden ui-surface-muted-bg backdrop-blur-sm border ui-border rounded-xl p-4 transition-all">
            <div class="absolute -right-8 -top-8 w-24 h-24 ui-status-warning-bg rounded-full blur-xl"></div>
            <div class="relative flex items-start justify-between">
                <div>
                    <p class="ui-text-muted ui-text-caption mb-1">متاجر معطّلة</p>
                    <h3 class="text-2xl font-bold ui-title">{{ $suspendedStoresCount }}</h3>
                    <span class="ui-text-caption ui-status-warning">تحتاج مراجعة سريعة</span>
                </div>
                <div class="w-10 h-10 ui-status-warning-bg rounded-lg flex items-center justify-center border ui-border">
                    <i class="fa-solid fa-pause-circle ui-status-warning"></i>
                </div>
            </div>
        </div>

        <div class="group relative overflow-hidden ui-surface-muted-bg backdrop-blur-sm border ui-border rounded-xl p-4 transition-all">
            <div class="absolute -right-8 -top-8 w-24 h-24 ui-status-info-bg rounded-full blur-xl"></div>
            <div class="relative flex items-start justify-between">
                <div>
                    <p class="ui-text-muted ui-text-caption mb-1">المحذوفات</p>
                    <h3 class="text-2xl font-bold ui-title">{{ $trashedStoresCount }}</h3>
                    <a href="{{ route('user.stores.trash') }}" class="ui-text-caption ui-status-info transition">
                        عرض سلة المحذوفات
                    </a>
                </div>
                <div class="w-10 h-10 ui-status-info-bg rounded-lg flex items-center justify-center border ui-border">
                    <i class="fa-solid fa-trash ui-status-info"></i>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- إذا لا يوجد متاجر نشطة --}}
@if($stores->count() === 0)
    <div class="ui-card border ui-border rounded-2xl p-8 md:p-12 text-center">
        <div class="w-20 h-20 ui-card ui-status-info flex items-center justify-center rounded-2xl mx-auto mb-6 border ui-border">
            <i class="fa-solid fa-store text-3xl"></i>
        </div>
        <h2 class="text-xl font-bold ui-title mb-3">ابدأ رحلتك التجارية</h2>
        <p class="ui-text-muted text-sm mb-8 max-w-md mx-auto leading-relaxed">
            لم تقم بإنشاء أي متجر حتى الآن. ابدأ بمتجرك الأول وأطلق العنان لإمكانيات عملك
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            @if($canAdd)
            <a href="{{ route('user.stores.create') }}"
               class="inline-flex items-center justify-center gap-2 ui-card ui-title px-8 py-3 rounded-xl text-sm font-medium transition-all shadow-lg">
                <i class="fa-solid fa-plus"></i>
                إنشاء متجري الأول
            </a>
            @endif
            <a href="{{ route('user.dashboard') }}"
               class="inline-flex items-center justify-center gap-2 ui-surface-muted-bg ui-text-muted ui-hover-info px-8 py-3 rounded-xl text-sm font-medium transition-all border ui-border">
                <i class="fa-solid fa-lightbulb"></i>
                كيف أبدأ؟
            </a>
        </div>
    </div>
@else
    {{-- عرض المتاجر --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($stores as $store)
            @include('user.stores.includes.store-card', ['store' => $store])
        @endforeach
    </div>

    {{-- تحسينات للأجهزة الصغيرة --}}
    <div class="block md:hidden mt-6">
        <div class="ui-card p-4">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-mobile-screen ui-status-info text-lg"></i>
                <div>
                    <p class="ui-title text-sm font-medium">تصفح أسهل على الجوال</p>
                    <p class="ui-text-muted ui-text-caption">اسحب لليمين لعرض المزيد من الخيارات</p>
                </div>
            </div>
        </div>
    </div>

    {{-- التوجيه السريع --}}
    @if($canAdd)
    <div class="mt-8 text-center">
        <div class="inline-flex items-center gap-4 px-6 py-4 ui-card border ui-border rounded-xl">
            <div class="text-left">
                <p class="ui-title text-sm font-medium mb-1">لا تزال لديك مساحة</p>
                <p class="ui-text-muted ui-text-caption">
                    يمكنك إضافة {{ $allowedStores - $totalUsedFromPlan }} متاجر أخرى
                </p>
            </div>
            <a href="{{ route('user.stores.create') }}"
               class="ui-status-info-bg ui-title px-4 py-2 rounded-lg text-sm font-medium transition whitespace-nowrap">
                <i class="fa-solid fa-plus mr-2"></i>
                إضافة متجر
            </a>
        </div>
    </div>
    @endif
@endif

{{-- رابط سلة المحذوفات --}}
@if(($trashedCount ?? 0) > 0)
<div class="ui-section-divider ui-section-divider-lg">
    <a href="{{ route('user.stores.trash') }}"
       class="inline-flex items-center gap-2 ui-text-muted ui-hover-info transition group">
        <i class="fa-solid fa-trash-can text-sm"></i>
        <span class="text-sm">سلة المحذوفات ({{ $trashedCount }})</span>
        <i class="fa-solid fa-arrow-left ui-text-caption opacity-0 group-hover:opacity-100 transition-opacity"></i>
    </a>
</div>
@endif

{{-- معلومات الخطة --}}
<div class="mt-8 ui-card border ui-border rounded-xl p-4">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 ui-card rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-crown ui-status-info"></i>
            </div>
            <div>
                <h4 class="ui-title font-medium">{{ $user->plan->name ?? 'الخطة الأساسية' }}</h4>
                <p class="ui-text-muted ui-text-caption">
                    {{ $allowedStores }} متجر مسموح به
                    • {{ $user->plan->allowed_accountants ?? $user->allowed_accountants ?? 1 }} محاسب
                </p>
            </div>
        </div>
        @if(!$canAdd && $allowedStores > 0)
        <a href="{{ route('user.subscription.renew') }}"
           class="ui-card ui-title px-4 py-2 rounded-lg text-sm font-medium transition whitespace-nowrap">
            <i class="fa-solid fa-arrow-up mr-2"></i>
            ترقية الخطة
        </a>
        @endif
    </div>
</div>
</div>

@endsection
