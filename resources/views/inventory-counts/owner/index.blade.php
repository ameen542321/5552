@extends('dashboard.app')
@section('title', 'جلسات الجرد')
@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div><h1 class="ui-title text-2xl font-bold">جلسات جرد {{ $store->name }}</h1><p class="ui-text-soft mt-1">أنشئ الجرد وتابع ما أرسله المحاسب واعتمد النتائج.</p></div>
        <a class="ui-btn ui-btn-primary" href="{{ route('user.stores.inventory-counts.create', $store) }}">إنشاء جلسة جرد</a>
    </div>
    <x-ui.card>
        @forelse($sessions as $session)
            <a href="{{ route('user.stores.inventory-counts.show', [$store, $session]) }}" class="ui-frame-row flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div><strong class="ui-title">{{ $session->referenceCode() }}</strong><p class="ui-text-soft">{{ $session->items_count }} منتجات — {{ $session->accountant?->name ?: 'لم يحدد محاسب' }}</p></div>
                <x-ui.badge :variant="$session->status === 'approved' ? 'success' : ($session->status === 'cancelled' ? 'danger' : 'info')">{{ $session->statusLabel() }}</x-ui.badge>
            </a>
        @empty
            <div class="ui-empty-state">لا توجد جلسات جرد حتى الآن.</div>
        @endforelse
        <div class="mt-4">{{ $sessions->links() }}</div>
    </x-ui.card>
</div>
@endsection
