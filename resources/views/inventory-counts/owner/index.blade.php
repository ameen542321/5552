@extends('dashboard.app')
@section('title', 'جلسات الجرد')
@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2"><h1 class="ui-title text-2xl font-bold">جلسات جرد {{ $store->name }}</h1><x-ui.help title="جلسات الجرد" body="من هنا تنشئ جلسة الجرد، وتتابع ما أرسله المحاسب، ثم تعتمد النتائج أو تعيد المنتجات التي تحتاج إلى عد جديد." /></div>
        <div class="flex flex-wrap gap-2"><a class="ui-btn ui-btn-secondary" href="{{ route('user.stores.show', $store) }}"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> رجوع للمتجر</a><a class="ui-btn ui-btn-primary" href="{{ route('user.stores.inventory-counts.create', $store) }}">إنشاء جلسة جرد</a></div>
    </div>
    <div class="ui-alert ui-alert-warning flex items-center gap-2" role="status">
        <strong>اختبار مؤقت: منتج واحد مسموح.</strong>
        <x-ui.help variant="warning" title="تنبيه الاختبار المؤقت" body="شرط الخمسة منتجات متوقف خلال التجربة الواقعية فقط، ويجب إعادة الحد الأدنى إلى خمسة منتجات بعد انتهائها." />
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
