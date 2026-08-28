@extends('dashboard.app')
@section('title', 'اختيار منتجات الجرد')
@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    <div><h1 class="ui-title text-2xl font-bold">اختيار منتجات الجرد</h1><p class="ui-text-soft mt-1">تظهر المنتجات التي لم تُجرد أولًا. احفظ اختيار الصفحة قبل الانتقال لصفحة أخرى. المطلوب خمسة منتجات على الأقل.</p></div>
    @if($errors->any())<div class="ui-alert ui-alert-danger">{{ $errors->first() }}</div>@endif
    <form method="GET" class="ui-card p-4 flex flex-col sm:flex-row gap-3">
        @if($editingSession)<input type="hidden" name="inventory_session" value="{{ $editingSession->id }}">@endif
        <input class="ui-input flex-1" name="q" value="{{ $search }}" placeholder="ابحث باسم المنتج أو وصفه">
        <button class="ui-btn ui-btn-secondary">بحث</button>
    </form>
    <form method="POST" action="{{ route('user.stores.inventory-counts.selection', $store) }}" class="ui-card p-4 space-y-4">
        @csrf
        <div class="ui-alert ui-alert-info">المحدد حاليًا: <strong>{{ count($selected) }}</strong> منتجات. يبقى الاختيار محفوظًا عند التنقل والبحث.</div>
        <div class="space-y-2">
            @forelse($products as $product)
                <input type="hidden" name="page_product_ids[]" value="{{ $product->id }}">
                <label class="ui-frame-row flex items-start gap-3">
                    <input type="checkbox" name="selected_ids[]" value="{{ $product->id }}" @checked(in_array($product->id, $selected))>
                    <span><strong class="ui-title">{{ $product->name }}</strong><span class="block ui-text-soft">{{ $product->description ?: 'لا يوجد وصف' }}</span><span class="block ui-text-caption">{{ $product->last_audit_date ? 'آخر جرد: '.$product->last_audit_date : 'لم يُجرد من قبل' }}</span></span>
                </label>
            @empty<div class="ui-empty-state">لا توجد نتائج.</div>@endforelse
        </div>
        <button class="ui-btn ui-btn-secondary">حفظ اختيارات هذه الصفحة</button>
        {{ $products->links() }}
    </form>
    @if(count($selected) >= 5)
        <form method="POST" action="{{ route('user.stores.inventory-counts.store', $store) }}" class="ui-card p-4 space-y-4">
            @csrf
            @if($editingSession)<input type="hidden" name="inventory_session" value="{{ $editingSession->id }}">@endif
            <h2 class="ui-title text-xl font-bold">إنشاء الجلسة من {{ count($selected) }} منتجات</h2>
            <label class="block"><span class="ui-label">المحاسب</span><select class="ui-input" name="accountant_id" required><option value="">اختر المحاسب</option>@foreach($accountants as $accountant)<option value="{{ $accountant->id }}" @selected($editingSession?->accountant_id === $accountant->id)>{{ $accountant->name }}</option>@endforeach</select></label>
            <label class="block"><span class="ui-label">ملاحظة عامة (اختياري)</span><textarea class="ui-input" name="note" rows="2">{{ $editingSession?->note }}</textarea></label>
            <button class="ui-btn ui-btn-primary">{{ $editingSession ? 'حفظ منتجات الجلسة' : 'الانتقال إلى إدارة الجلسة' }}</button>
        </form>
    @endif
</div>
@endsection
