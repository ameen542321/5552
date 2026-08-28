@extends('dashboard.app')
@section('title', 'مهام الجرد')
@section('content')
<div class="max-w-5xl mx-auto space-y-5"><div class="flex items-center gap-2"><h1 class="ui-title text-2xl font-bold">مهام الجرد</h1><x-ui.help title="مهام الجرد" body="افتح الجلسة وأدخل الكمية الفعلية لكل منتج. لا يعرض النظام رصيده الحالي حتى لا يؤثر على نتيجة العد." /></div><x-ui.card>
@forelse($sessions as $session)<a class="ui-frame-row flex items-center justify-between" href="{{ in_array($session->status, ['sent_to_accountant','counting','returned_to_accountant']) ? route('accountant.inventory-counts.show', $session) : '#' }}"><span><strong class="ui-title">{{ $session->referenceCode() }}</strong><span class="block ui-text-soft">{{ $session->store?->name }} — {{ $session->items_count }} منتجات</span></span><x-ui.badge variant="info">{{ $session->statusLabel() }}</x-ui.badge></a>@empty<div class="ui-empty-state">لا توجد مهام جرد.</div>@endforelse
<div class="mt-4">{{ $sessions->links() }}</div></x-ui.card></div>
@endsection
