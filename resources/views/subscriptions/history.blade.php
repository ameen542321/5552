@extends('dashboard.app')

@section('content')
<div class="container mx-auto px-4">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-semibold">سجل الاشتراكات</h1>
            <p class="ui-text-muted text-sm mt-1">عرض جميع الاشتراكات السابقة والحالية</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('subscription.renew') }}" class="ui-btn ui-btn-primary transition flex items-center">
                <i class="fas fa-plus ml-2"></i>
                تجديد اشتراك
            </a>
            <a href="{{ route('dashboard') }}" class="ui-btn ui-btn-secondary transition">
                <i class="fas fa-arrow-right ml-2"></i>
                العودة
            </a>
        </div>
    </div>

    <div class="ui-card p-6">
        @if($subscriptions->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b ui-border">
                            <th class="text-right py-3 px-4 ui-text-muted">#</th>
                            <th class="text-right py-3 px-4 ui-text-muted">نوع الاشتراك</th>
                            <th class="text-right py-3 px-4 ui-text-muted">المبلغ</th>
                            <th class="text-right py-3 px-4 ui-text-muted">تاريخ البداية</th>
                            <th class="text-right py-3 px-4 ui-text-muted">تاريخ النهاية</th>
                            <th class="text-right py-3 px-4 ui-text-muted">تاريخ الإنشاء</th>
                            <th class="text-right py-3 px-4 ui-text-muted">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($subscriptions as $index => $sub)
                        <tr class="border-b ui-border ui-hover-info-bg transition">
                            <td class="py-3 px-4 ui-text-soft">{{ $index + 1 }}</td>
                            <td class="py-3 px-4">
                                <span class="flex items-center">
                                    @if($sub->type === 'trial')
                                        <i class="fas fa-gift ui-status-warning ml-2"></i>
                                    @elseif($sub->type === 'yearly')
                                        <i class="fas fa-calendar-alt ui-status-success ml-2"></i>
                                    @else
                                        <i class="fas fa-calendar ui-status-info ml-2"></i>
                                    @endif
                                    @switch($sub->type)
                                        @case('monthly') اشتراك شهري @break
                                        @case('yearly') اشتراك سنوي @break
                                        @case('trial') اشتراك تجريبي @break
                                        @default {{ $sub->type }}
                                    @endswitch
                                </span>
                            </td>
                            <td class="py-3 px-4 ui-text-soft font-semibold">{{ number_format($sub->price) }} ريال</td>
                            <td class="py-3 px-4 ui-text-soft">{{ \Carbon\Carbon::parse($sub->start_at)->format('Y-m-d') }}</td>
                            <td class="py-3 px-4 ui-text-soft">{{ \Carbon\Carbon::parse($sub->end_at)->format('Y-m-d') }}</td>
                            <td class="py-3 px-4 ui-text-soft">{{ \Carbon\Carbon::parse($sub->created_at)->format('Y-m-d') }}</td>
                            <td class="py-3 px-4">
                                <span class="px-3 py-1 rounded-full ui-text-caption font-semibold flex items-center w-fit @if($sub->status === 'نشط') ui-status-success-bg ui-status-success border ui-border @elseif($sub->status === 'ملغي') ui-status-danger-bg ui-status-danger @else ui-surface-muted-bg ui-text-muted @endif">
                                    <i class="fas fa-circle ui-text-caption ml-2"></i>
                                    {{ $sub->status }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- إحصائيات سريعة --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6 pt-6 border-t ui-border">
                <div class="ui-card p-4 text-center">
                    <p class="ui-text-muted text-sm mb-1">إجمالي الاشتراكات</p>
                    <p class="text-2xl font-bold ui-title">{{ $subscriptions->count() }}</p>
                </div>
                <div class="ui-card p-4 text-center">
                    <p class="ui-text-muted text-sm mb-1">إجمالي المبلغ المدفوع</p>
                    <p class="text-2xl font-bold ui-status-success">{{ number_format($subscriptions->sum('price')) }} ريال</p>
                </div>
                <div class="ui-card p-4 text-center">
                    <p class="ui-text-muted text-sm mb-1">الاشتراكات النشطة</p>
                    <p class="text-2xl font-bold ui-brand-text">{{ $subscriptions->where('status', 'نشط')->count() }}</p>
                </div>
                <div class="ui-card p-4 text-center">
                    <p class="ui-text-muted text-sm mb-1">آخر اشتراك</p>
                    <p class="text-sm font-bold ui-title">{{ $subscriptions->first() ? \Carbon\Carbon::parse($subscriptions->first()->created_at)->format('Y-m-d') : '-' }}</p>
                </div>
            </div>
        @else
            <div class="text-center py-16">
                <div class="w-24 h-24 ui-btn ui-btn-secondary rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-history text-4xl ui-text-muted"></i>
                </div>
                <h3 class="text-xl font-semibold ui-title mb-2">لا يوجد سجل اشتراكات</h3>
                <p class="ui-text-muted mb-6">لم تقم بأي اشتراك سابق. ابدأ رحلتك معنا الآن!</p>
                <a href="{{ route('subscription.renew') }}" class="inline-block ui-btn ui-btn-primary px-8 py-3 rounded ui-title font-semibold transition transform hover:scale-105">
                    <i class="fas fa-rocket ml-2"></i>
                    ابدأ اشتراكك الآن
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
