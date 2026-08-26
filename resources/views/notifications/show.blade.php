@extends('dashboard.app')
@section('title', 'تفاصيل الإشعار')

@section('content')
@php
    $currentUser = auth('accountant')->check() ? auth('accountant')->user() : auth('web')->user();

    if (auth('accountant')->check()) {
        $routePrefix = 'accountant.notifications.';
    } elseif (auth('web')->check() && auth('web')->user()->role === 'admin') {
        $routePrefix = 'admin.notifications.';
    } else {
        $routePrefix = 'user.notifications.';
    }

    $notifRoute = fn ($name, $id = null) => $id
        ? route($routePrefix . $name, $id)
        : route($routePrefix . $name);

    $isRead = $notification->isReadBy($currentUser->id);
@endphp

<div class="max-w-4xl mx-auto px-4 py-6 space-y-5">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black ui-title">تفاصيل الإشعار</h1>
            <p class="ui-text-muted text-sm mt-1">عرض كامل لمحتوى الإشعار وإدارته.</p>
        </div>

        <div class="flex gap-2 flex-wrap">
            <a href="{{ $notifRoute('index') }}"
               class="px-4 py-2 rounded-lg ui-surface-muted-bg ui-hover-info-bg ui-text-soft text-sm font-bold transition">
                <i class="fa-solid fa-arrow-right ml-1"></i> رجوع
            </a>

            <form method="POST" action="{{ $notifRoute('toggle', $notification->id) }}">
                @csrf
                <button class="px-4 py-2 rounded-lg ui-btn ui-btn-primary ui-title text-sm font-bold transition">
                    {{ $isRead ? 'وضع كغير مقروء' : 'وضع كمقروء' }}
                </button>
            </form>

            {{-- إصلاح مطبق: صفحة التفاصيل تعتمد عقد التأكيد المركزي نفسه المستخدم في قائمة الإشعارات. --}}
            <form method="POST" action="{{ $notifRoute('delete', $notification->id) }}"
                  data-ui-confirm="هل تريد حذف هذا الإشعار؟"
                  data-ui-confirm-title="تأكيد حذف الإشعار">
                @csrf
                @method('DELETE')
                <input type="hidden" name="redirect_to" value="{{ $notifRoute('index') }}">
                <button class="px-4 py-2 rounded-lg ui-btn ui-btn-danger ui-title text-sm font-bold transition">
                    حذف
                </button>
            </form>
        </div>
    </div>

    <div class="ui-card p-6 shadow-sm">
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <span class="px-3 py-1 rounded-full ui-text-caption font-bold {{ $isRead ? 'ui-status-success-bg ui-status-success border ui-border' : 'ui-status-warning-bg ui-status-warning border ui-status-warning-border' }}">
                {{ $isRead ? 'مقروء' : 'غير مقروء' }}
            </span>
            <span class="px-3 py-1 rounded-full ui-text-caption font-bold ui-surface-muted-bg ui-text-soft border ui-border">
                {{ $notification->created_at->format('Y-m-d H:i') }}
            </span>
        </div>

        <h2 class="text-xl font-black ui-status-info mb-3">{{ $notification->title }}</h2>

        <div class="mb-4 text-sm ui-text-soft flex items-center gap-2">
            <i class="fa-solid fa-paper-plane ui-text-muted"></i>
            <span class="ui-text-muted">المرسل:</span>
            @switch($notification->sender_type)
                @case('admin')
                    <span>المدير العام</span>
                    @break
                @case('user')
                    <span>المالك</span>
                    @break
                @case('accountant')
                    <span>محاسب</span>
                    @break
                @case('CARLED')
                    <span class="px-2 py-0.5 rounded-full ui-btn ui-btn-primary ui-title ui-text-caption">CARLED</span>
                    @break
                @default
                    <span>غير معروف</span>
            @endswitch
        </div>

        <div class="ui-surface-muted-bg border ui-border rounded-xl p-4 ui-text-soft leading-7 whitespace-pre-line">
            {{ $notification->message }}
        </div>

        @if(isset($notification->data['url']) && $notification->data['url'])
            <div class="mt-4">
                <a href="{{ $notification->data['url'] }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-lg ui-btn ui-btn-primary ui-title text-sm font-bold transition">
                    <i class="fa-solid fa-link"></i>
                    فتح الرابط المرتبط
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
