@extends('dashboard.app')

@section('content')

<div class="container mx-auto px-4">
    {{-- عنوان الصفحة --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <h1 class="text-2xl font-semibold">تجديد الاشتراك</h1>
        <div class="flex flex-wrap gap-2">
            <a href="" class="ui-surface-muted-bg px-4 py-2 rounded ui-title text-sm transition">
                <i class="fas fa-history ml-2"></i>
                سجل الاشتراكات
            </a>
            <a href="" class="ui-surface-muted-bg px-4 py-2 rounded ui-title text-sm transition">
                <i class="fas fa-arrow-right ml-2"></i>
                العودة للرئيسية
            </a>
        </div>
    </div>

    {{-- رسالة النجاح --}}
    @if(session('subscription_success'))
        <div class="ui-status-success-bg ui-title p-4 rounded-lg mb-6 flex items-center animate-pulse">
            <i class="fas fa-check-circle ml-2 text-xl"></i>
            {{ session('subscription_success') }}
        </div>
    @endif

    {{-- معلومات المستخدم والاشتراك الحالي --}}
    <div class="ui-card   border ui-border rounded-xl p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center">
                <div class="w-14 h-14 ui-card   rounded-full flex items-center justify-center ml-4 shadow-lg">
                    <span class="text-xl font-bold">{{ substr($user->name, 0, 1) }}</span>
                </div>
                <div>
                    <h2 class="text-xl font-bold ui-title">{{ $user->name }}</h2>
                    <p class="ui-text-muted text-sm flex items-center">
                        <i class="fas fa-envelope ml-1 ui-text-caption"></i>
                        {{ $user->email }}
                    </p>
                </div>
            </div>
            <span class="px-4 py-2 rounded-full text-sm font-semibold flex items-center @if($user->status === 'نشط') ui-status-success-bg ui-surface-muted-bg ui-status-success border ui-border @else ui-status-danger-bg ui-status-danger @endif">
                <i class="fas fa-circle ui-text-caption ml-2"></i>
                {{ $user->status ?? 'غير نشط' }}
            </span>
        </div>

       @if($currentSubscription)
<div class="ui-card p-6 mb-8">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-xl font-bold ui-title">الاشتراك الحالي</h3>
        <span class="px-3 py-1 rounded-full text-sm ui-status-success-bg ui-surface-muted-bg ui-status-success">
            <i class="fas fa-circle ui-text-caption ml-1"></i>
            نشط
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <p class="text-sm ui-text-muted">الخطة</p>
            <p class="ui-title font-semibold">
                @switch($currentSubscription->type)
                    @case('basic') الخطة العادية @break
                    @case('silver') الخطة الفضية @break
                    @case('gold') الخطة الذهبية @break
                    @default {{ $currentSubscription->type }}
                @endswitch
            </p>
        </div>
        <div>
            <p class="text-sm ui-text-muted">تاريخ الانتهاء</p>
            <p class="ui-title">{{ \Carbon\Carbon::parse($currentSubscription->end_at)->format('Y-m-d') }}</p>
        </div>
        <div>
            <p class="text-sm ui-text-muted">المدة المتبقية</p>
            <p class="ui-status-info font-semibold">
                @php
                    $daysLeft = \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($currentSubscription->end_at), false);
                @endphp
                @if($daysLeft > 0)
                    {{ floor($daysLeft) }} يوم
                @else
                    منتهي
                @endif
            </p>
        </div>
    </div>
</div>
@endif
    </div>

    {{-- عنوان اختيار الخطة --}}
    <div class="mb-6">
        <h2 class="text-xl font-semibold ui-title mb-2">اختر باقة التجديد</h2>
        <p class="ui-text-muted flex items-center">
            <i class="fas fa-gem ml-2 ui-status-warning"></i>
            جميع الباقات تأتي بمدة 6 أشهر مع إمكانية الترقية لاحقاً
        </p>
    </div>

    {{-- بطاقات الخطط --}}
    <form method="POST" action="{{ route('subscription.processRenew') }}" id="renewForm">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            @foreach($plans as $key => $plan)
            <div class="ui-surface-muted-bg border-2 ui-border rounded-xl p-6 transition-all duration-300 cursor-pointer plan-card relative {{ $key === 'silver' ? 'transform scale-105 shadow-2xl z-10' : '' }}" data-plan="{{ $key }}">

                @if($key === 'silver')
                <div class="absolute -top-4 right-4 ui-card   ui-title text-sm font-bold px-4 py-1 rounded-full shadow-lg flex items-center">
                    <i class="fas fa-star ml-1 ui-text-caption"></i>
                    الأكثر طلباً
                </div>
                @endif

                @if($key === 'gold')
                <div class="absolute top-4 left-4">
                    <span class="ui-status-info-bg ui-title ui-text-caption font-bold px-3 py-1 rounded-full flex items-center">
                        <i class="fas fa-crown ml-1 ui-text-caption"></i>
                        VIP
                    </span>
                </div>
                @endif

                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-2xl font-bold ui-title">{{ $plan['name'] }}</h3>
                    <div class="relative">
                        <input type="radio" name="plan" value="{{ $key }}" class="w-5 h-5 ui-status-info accent-blue-600" {{ $key === 'silver' ? 'checked' : '' }}>
                    </div>
                </div>

                <div class="mb-4">
                    <span class="text-4xl font-bold ui-status-info">{{ number_format($plan['price']) }}</span>
                    <span class="ui-text-muted mr-1">ريال</span>
                    <span class="block text-sm ui-text-muted mt-1">
                        <i class="far fa-clock ml-1"></i>
                        لمدة 6 أشهر
                    </span>
                </div>

                <ul class="space-y-3 ui-text-muted mb-6">
                    @foreach($plan['features'] as $feature)
                    <li class="flex items-center">
                        <i class="fas fa-check-circle ui-status-success ml-2 text-sm"></i>
                        <span>{{ $feature }}</span>
                    </li>
                    @endforeach
                </ul>

                <div class="text-center ui-section-divider">
                    @php
                        $discount = $key === 'gold' ? 30 : ($key === 'silver' ? 20 : 10);
                    @endphp
                    <span class="text-sm ui-status-info-bg ui-surface-muted-bg ui-status-info px-3 py-1 rounded-full">
                        <i class="fas fa-gift ml-1"></i>
                        وفر {{ $discount }}% مقارنة بالاشتراك الشهري
                    </span>
                </div>
            </div>
            @endforeach
        </div>

        {{-- ملخص الطلب --}}
        <div class="ui-card   border ui-border rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold ui-title mb-4 flex items-center">
                <i class="fas fa-file-invoice ml-2 ui-status-info"></i>
                ملخص الطلب
            </h3>

            <div class="space-y-3">
                <div class="flex justify-between items-center ui-text-muted">
                    <span>قيمة الخطة</span>
                    <span id="planPrice" class="font-semibold">1,400 ريال</span>
                </div>
                <div class="flex justify-between items-center ui-text-muted">
                    <span>الضريبة (15%)</span>
                    <span id="taxAmount" class="font-semibold">210 ريال</span>
                </div>
                <div class="ui-section-divider ui-section-divider-sm"></div>
                <div class="flex justify-between items-center ui-title">
                    <span class="text-lg font-bold">الإجمالي النهائي</span>
                    <span class="text-3xl font-bold ui-status-info" id="totalPrice">1,610 <span class="text-sm ui-text-muted">ريال</span></span>
                </div>
            </div>
        </div>

        {{-- شروط وأحكام --}}
        <div class="mb-6">
            <label class="flex items-center cursor-pointer group">
                <input type="checkbox" class="form-checkbox h-5 w-5 ui-status-info rounded ui-surface-muted-bg ui-border " required>
                <span class="mr-2 ui-text-muted text-sm group-ui-hover-info transition">
                    أوافق على <a href="#" class="ui-status-info hover:underline font-semibold">شروط وأحكام</a> التجديد، وأقر بأن المبلغ غير قابل للاسترداد.
                </span>
            </label>
        </div>

        {{-- أزرار التحكم --}}
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
            <a href="" class="ui-btn ui-btn-danger w-full sm:w-auto px-8 py-3">
                <i class="fas fa-times ml-2"></i>
                إلغاء
            </a>
            <button type="submit" class="ui-btn ui-btn-primary w-full sm:w-auto px-10 py-3" id="submitBtn">
                <i class="fas fa-check ml-2"></i>
                تأكيد التجديد
            </button>
        </div>
    </form>

    {{-- عرض آخر 3 اشتراكات سابقة --}}
    @if($allSubscriptions->count() > 0)
    <div class="mt-12">
        <h3 class="text-lg font-semibold ui-title mb-4 flex items-center">
            <i class="fas fa-history ml-2 ui-text-muted"></i>
            آخر الاشتراكات السابقة
        </h3>
        <div class="ui-card overflow-hidden">
            <table class="w-full">
                <thead class="ui-surface-muted-bg">
                    <tr>
                        <th class="text-right py-3 px-4 ui-text-muted text-sm">النوع</th>
                        <th class="text-right py-3 px-4 ui-text-muted text-sm">المبلغ</th>
                        <th class="text-right py-3 px-4 ui-text-muted text-sm">تاريخ البداية</th>
                        <th class="text-right py-3 px-4 ui-text-muted text-sm">تاريخ النهاية</th>
                        <th class="text-right py-3 px-4 ui-text-muted text-sm">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allSubscriptions->take(3) as $sub)
                    <tr class="border-t ui-border ui-surface-muted-bg">
                        <td class="py-3 px-4 ui-text-muted">
                            @switch($sub->type)
                                @case('monthly') شهري @break
                                @case('yearly') سنوي @break
                                @case('trial') تجريبي @break
                                @default {{ $sub->type }}
                            @endswitch
                        </td>
                        <td class="py-3 px-4 ui-text-muted">{{ number_format($sub->price) }} ريال</td>
                        <td class="py-3 px-4 ui-text-muted">{{ \Carbon\Carbon::parse($sub->start_at)->format('Y-m-d') }}</td>
                        <td class="py-3 px-4 ui-text-muted">{{ \Carbon\Carbon::parse($sub->end_at)->format('Y-m-d') }}</td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-1 rounded ui-text-caption @if($sub->status === 'نشط') ui-status-success-bg ui-surface-muted-bg ui-status-success @elseif($sub->status === 'ملغي') ui-status-danger-bg ui-status-danger @else ui-text-muted @endif">
                                {{ $sub->status }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if($allSubscriptions->count() > 3)
            <div class="p-4 ui-section-divider ui-section-divider-flush text-center">
                <a href="{{ route('subscription.history') }}" class="ui-status-info text-sm">
                    عرض جميع الاشتراكات ({{ $allSubscriptions->count() }})
                    <i class="fas fa-arrow-left mr-1 ui-text-caption"></i>
                </a>
            </div>
            @endif
        </div>
    </div>
    @endif
</div>

{-- علامة تفعيل لوحدة الواجهة المستخرجة دون تغيير العملية. --}
<div class="hidden" data-subscription-renew-interface aria-hidden="true"></div>
@endsection

<!-- @push('scripts') -->

<!-- @endpush -->
