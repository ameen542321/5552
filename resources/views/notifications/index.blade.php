@extends('dashboard.app')
@section('title', 'الإشعارات')
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

    $unreadCount = $notifications->getCollection()->filter(fn ($notification) => !$notification->isReadBy($currentUser->id))->count();
@endphp

<div class="max-w-6xl mx-auto px-4 py-6 space-y-5">

    <div class="ui-card p-5">
        <div class="flex flex-col md:flex-row gap-4 md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-black ui-title">مركز الإشعارات</h1>
                <p class="ui-text-muted text-sm mt-1">تابع كل التنبيهات الداخلية وقم بإدارة حالتها بسهولة.</p>
            </div>

            <div class="flex gap-2 flex-wrap">
                <span class="px-3 py-1 rounded-full ui-status-info-bg ui-status-info ui-border ui-text-caption font-bold">
                    الإجمالي: {{ $notifications->total() }}
                </span>
                <span class="px-3 py-1 rounded-full ui-status-warning-bg ui-status-warning ui-border ui-text-caption font-bold">
                    غير المقروء: {{ $unreadCount }}
                </span>
            </div>
        </div>
    </div>

    <div class="ui-card p-4">
        <div class="flex flex-col md:flex-row gap-3 md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 text-sm ui-text-soft cursor-pointer">
                    <input type="checkbox" id="select-all" data-notification-select-all class="w-4 h-4 rounded ui-border ui-surface-muted-bg">
                    تحديد الكل
                </label>

                <form method="POST" action="{{ $notifRoute('markAll') }}">
                    @csrf
                    <button class="ui-btn ui-btn-secondary px-3 py-2 ui-text-caption">
                        تعليم الكل كمقروء
                    </button>
                </form>
            </div>

            <form id="bulk-form" method="POST" action="{{ $notifRoute('markSelected') }}">
                @csrf
                <button class="px-4 py-2 rounded-lg ui-btn ui-btn-primary ui-title text-sm font-bold transition">
                    تحديد المحدد كمقروء
                </button>
            </form>
        </div>
    </div>

    <div class="space-y-3">
        @forelse($notifications as $n)
            @php $isRead = $n->isReadBy($currentUser->id); @endphp
            <div class="ui-card {{ $isRead ? 'ui-border' : 'ui-border' }} rounded-2xl p-4 shadow-sm">
                <div class="flex flex-col lg:flex-row gap-4 lg:items-start lg:justify-between">
                    <div class="flex items-start gap-3 min-w-0">
                        <input type="checkbox"
                               form="bulk-form"
                               name="selected[]"
                               value="{{ $n->id }}"
                               data-notification-item-checkbox class="item-checkbox mt-1 w-4 h-4 rounded ui-border ui-surface-muted-bg">

                        <div class="min-w-0">
                            <a href="{{ $notifRoute('show', $n->id) }}"
                               class="text-base font-bold {{ $isRead ? 'ui-text-soft' : 'ui-status-info' }} ui-hover-info transition">
                                {{ $n->title }}
                            </a>
                            <p class="text-sm ui-text-soft mt-1 break-words">{{ $n->message }}</p>
                            <div class="mt-2 flex items-center gap-2 ui-text-caption ui-text-muted">
                                <i class="fa-regular fa-clock"></i>
                                {{ $n->created_at->format('Y-m-d H:i') }}
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-row lg:flex-col gap-2 shrink-0">
                        <form method="POST" action="{{ $notifRoute('toggle', $n->id) }}">
                            @csrf
                            <button class="ui-btn ui-btn-secondary px-3 py-2 ui-text-caption w-full">
                                {{ $isRead ? 'وضع كغير مقروء' : 'وضع كمقروء' }}
                            </button>
                        </form>

                        {{-- إصلاح مطبق: تأكيد حذف الإشعار يستخدم الحوار المركزي دون تعديل نطاق الحذف أو مساره. --}}
                        <form method="POST" action="{{ $notifRoute('delete', $n->id) }}"
                              data-ui-confirm="هل تريد حذف هذا الإشعار؟"
                              data-ui-confirm-title="تأكيد حذف الإشعار">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                            <button class="ui-btn ui-btn-danger px-3 py-2 ui-text-caption w-full">
                                حذف
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center ui-text-muted py-12 ui-card">
                لا توجد إشعارات حالياً.
            </div>
        @endforelse
    </div>
    {{ $notifications->links() }}
</div>


@endsection
