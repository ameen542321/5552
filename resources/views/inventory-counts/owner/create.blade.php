@extends('dashboard.app')
@section('title', 'اختيار منتجات الجرد')
@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <h1 class="ui-title text-2xl font-bold">اختيار منتجات الجرد</h1>
            <x-ui.help title="اختيار منتجات الجرد" body="تظهر المنتجات التي لم تُجرد أولًا. احفظ اختيار الصفحة قبل الانتقال إلى صفحة أخرى، واختر خمسة منتجات على الأقل لإنشاء الجلسة." />
        </div>
        <a class="ui-btn ui-btn-secondary" href="{{ route('user.stores.inventory-counts.index', $store) }}"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> رجوع</a>
    </div>
    @if($errors->any())<div class="ui-alert ui-alert-danger">{{ $errors->first() }}</div>@endif
    <form method="GET" class="ui-card p-4 flex flex-col sm:flex-row gap-3">
        @if($editingSession)<input type="hidden" name="inventory_session" value="{{ $editingSession->id }}">@endif
        <input class="ui-input flex-1" name="q" value="{{ $search }}" placeholder="ابحث باسم المنتج أو وصفه">
        <button class="ui-btn ui-btn-secondary">بحث</button>
    </form>
    <form method="POST" action="{{ route('user.stores.inventory-counts.selection', $store) }}" class="ui-card p-4 space-y-4">
        @csrf
        <div class="flex items-center gap-2">
            <x-ui.badge variant="info">المحدد حاليًا: {{ count($selected) }} منتجات</x-ui.badge>
            <x-ui.help title="حفظ التحديد" body="يحفظ النظام المنتجات المحددة عند الانتقال بين صفحات النتائج أو استخدام البحث بعد الضغط على حفظ اختيارات هذه الصفحة." />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @forelse($products as $product)
                @php
                    $audit = $product->inventoryAuditStatus($store);
                    $dotClass = [
                        'red' => 'ui-dot-danger',
                        'yellow' => 'ui-dot-warning',
                        'green' => 'ui-dot-success',
                    ][$audit['color']] ?? 'ui-surface-muted-bg';
                @endphp
                <input type="hidden" name="page_product_ids[]" value="{{ $product->id }}">
                <label class="ui-card p-4 cursor-pointer">
                    <span class="flex items-start justify-between gap-3">
                        <span class="min-w-0">
                            <span class="ui-title font-bold flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full {{ $dotClass }} flex-shrink-0"></span>
                                <span class="truncate">{{ $product->name }}</span>
                            </span>
                            <span class="block ui-text-muted ui-text-caption mt-1 truncate">{{ $product->category->name ?? 'غير مصنف' }}</span>
                        </span>
                        <input type="checkbox" name="selected_ids[]" value="{{ $product->id }}" @checked(in_array($product->id, $selected)) aria-label="تحديد {{ $product->name }}">
                    </span>
                    <span class="mt-3 grid grid-cols-3 gap-2 ui-text-caption">
                        <span class="ui-surface-muted-bg border ui-border rounded-lg p-2 ui-text-muted">الكمية: <b class="ui-title">{{ number_format((float) $product->quantity, 2) }}</b></span>
                        <span class="ui-surface-muted-bg border ui-border rounded-lg p-2 ui-text-muted">البيع: <b class="ui-status-info">{{ number_format((float) $product->price, 2) }}</b></span>
                        <span class="ui-surface-muted-bg border ui-border rounded-lg p-2 ui-text-muted">التكلفة: <b class="ui-status-success">{{ number_format((float) ($product->cost_price ?? 0), 2) }}</b></span>
                    </span>
                    <span class="block ui-text-caption mt-3">{{ $product->last_audit_date ? 'آخر جرد: '.$product->last_audit_date : 'لم يُجرد من قبل' }}</span>
                </label>
            @empty<div class="md:col-span-2 xl:col-span-3 ui-empty-state">لا توجد نتائج.</div>@endforelse
        </div>
        <button class="ui-btn ui-btn-secondary">حفظ اختيارات هذه الصفحة</button>
        {{ $products->links() }}
    </form>
    @if(count($selected) >= 5)
        <form method="POST" action="{{ route('user.stores.inventory-counts.store', $store) }}" class="ui-card p-4 space-y-4">
            @csrf
            @if($editingSession)<input type="hidden" name="inventory_session" value="{{ $editingSession->id }}">@endif
            <div class="flex items-center gap-2"><h2 class="ui-title text-xl font-bold">إنشاء الجلسة من {{ count($selected) }} منتجات</h2><x-ui.help title="إنشاء الجلسة" body="اختر المحاسب الذي سينفذ العد، ويمكنك إضافة ملاحظة عامة تظهر معه في الجلسة." /></div>
            <label class="block"><span class="ui-label">المحاسب</span><select class="ui-input" name="accountant_id" required><option value="">اختر المحاسب</option>@foreach($accountants as $accountant)<option value="{{ $accountant->id }}" @selected($editingSession?->accountant_id === $accountant->id)>{{ $accountant->name }}</option>@endforeach</select></label>
            <label class="block"><span class="ui-label">ملاحظة عامة (اختياري)</span><textarea class="ui-input" name="note" rows="2">{{ $editingSession?->note }}</textarea></label>
            <button class="ui-btn ui-btn-primary">{{ $editingSession ? 'حفظ منتجات الجلسة' : 'الانتقال إلى إدارة الجلسة' }}</button>
        </form>
    @endif
</div>
@endsection
