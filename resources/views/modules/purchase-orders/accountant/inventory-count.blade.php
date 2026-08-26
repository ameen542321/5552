@extends('dashboard.app')
@section('title', 'جرد ' . $order->displayName())
@section('content')
@php
    $countableItems = $order->items->filter(fn ($item) => $item->inventory_count_required);
@endphp
<div class="max-w-7xl mx-auto p-4 md:p-6 space-y-6" dir="rtl">
    <div class="ui-card p-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="flex items-center gap-2"><h1 class="ui-title text-2xl font-black">جرد {{ $order->displayName() }}</h1><x-ui.help title="إدخال الجرد" body="عُدّ الكمية الموجودة في المخزن من المنتج، ثم اكتب العدد في الحقل المخصص." /></div>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('accountant.purchase-orders.show', $order->id) }}" class="ui-btn ui-btn-secondary">رجوع</a>
            <a href="{{ route('accountant.purchase-orders.inventory-count.pdf', $order->id) }}" class="ui-btn ui-btn-info">تحميل PDF الجرد</a>
        </div>
    </div>

    @if($order->inventory_review_note)
        <div class="ui-alert ui-alert-info">
            <strong class="ui-title">ملاحظة {{ $store->user?->name ?: 'صاحب المتجر' }}:</strong>
            <span>{{ $order->inventory_review_note }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border ui-border ui-status-danger-bg p-4 ui-status-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('accountant.purchase-orders.inventory-count.save', $order->id) }}" class="ui-card p-5 space-y-5">
        @csrf
        <div class="grid gap-4 lg:grid-cols-2">
            @forelse($countableItems as $item)
                @php
                    $countProduct = $item->product ?: $item->matchedProduct;
                    $defaultCountUnit = old('items.' . $item->id . '.inventory_count_unit', $item->inventory_count_unit ?: $countProduct?->quick_sale_default_unit ?: $item->unit_type ?: 'unit');
                    $countUnits = (($countProduct?->product_type ?? null) === 'fractional' || (float) ($countProduct?->roll_length ?? 0) > 0)
                        ? ['roll' => 'رول', 'meter' => 'متر']
                        : ($countProduct?->is_splittable ? ['kit' => 'طقم', 'piece' => 'حبة'] : ['unit' => 'وحدة']);
                    $hasCountUnitChoices = count($countUnits) > 1;
                    $countUnitHint = null;
                    if (($countProduct?->product_type ?? null) === 'fractional' || (float) ($countProduct?->roll_length ?? 0) > 0) {
                        $rollLength = (float) ($countProduct?->roll_length ?? 0);
                        $countUnitHint = $rollLength > 0 ? 'الرول = '.number_format($rollLength, 2).' متر' : null;
                    } elseif ($countProduct?->is_splittable) {
                        $countUnitHint = 'الطقم = '.max(1, (int) $countProduct->items_per_unit).' حبة';
                    }
                @endphp
                <article class="ui-card-muted p-4 space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div><span class="ui-badge ui-badge-neutral">{{ $loop->iteration }}</span><h2 class="ui-title font-black mt-2">{{ $item->productName() }}</h2></div>
                        <span class="ui-text-soft">المطلوب: <strong class="ui-title">{{ number_format((float) $item->quantity_requested, 2) }}</strong></span>
                    </div>
                    @if($item->inventory_count_attempt)
                        <p class="ui-text-soft">الجرد السابق: <strong class="ui-title">{{ number_format((float) $item->inventory_count_quantity, 2) }}</strong></p>
                    @endif
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="ui-label">الكمية الموجودة لديك الآن
                            <input type="number" inputmode="decimal" step="0.01" min="0" required name="items[{{ $item->id }}][inventory_count_quantity]" value="{{ old('items.' . $item->id . '.inventory_count_quantity', $item->inventory_count_quantity) }}" class="ui-input" placeholder="اكتب العدد">
                        </label>
                        @if($hasCountUnitChoices)
                            <label class="ui-label">وحدة الجرد
                                <select name="items[{{ $item->id }}][inventory_count_unit]" class="ui-input">
                                    @foreach($countUnits as $unitValue => $unitLabel)
                                        <option value="{{ $unitValue }}" @selected($defaultCountUnit === $unitValue)>{{ $unitLabel }}</option>
                                    @endforeach
                                </select>
                                @if($countUnitHint)
                                    <span class="flex items-center gap-1 ui-text-caption ui-text-soft">
                                        <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                                        {{ $countUnitHint }}
                                    </span>
                                @endif
                            </label>
                        @else
                            <input type="hidden" name="items[{{ $item->id }}][inventory_count_unit]" value="unit">
                        @endif
                    </div>
                    <label class="ui-label">ملاحظة الجرد
                        <input maxlength="40" name="items[{{ $item->id }}][inventory_count_note]" value="{{ old('items.' . $item->id . '.inventory_count_note', $item->inventory_count_note) }}" class="ui-input" placeholder="ملاحظة اختيارية حتى 40 حرفًا">
                    </label>
                    @if($item->inventory_count_attempt >= 2)
                        <div class="ui-alert ui-alert-warning"><span class="ui-alert-body">هذه المحاولة الثالثة والأخيرة لهذا المنتج. إذا لم يعتمد {{ $store->user?->name ?: 'مالك المتجر' }} الجرد، فسيتم حذف المنتج من الطلبية.</span></div>
                    @endif
                </article>
            @empty
                <div class="ui-card-muted p-6 text-center ui-text-muted">لا توجد منتجات مطلوبة للجرد.</div>
            @endforelse
        </div>
        <div class="flex justify-end gap-3">
            <button type="submit" name="action" value="save" class="ui-btn ui-btn-secondary">حفظ مؤقت</button>
            <button type="submit" name="action" value="submit" class="ui-btn ui-btn-success">إعادة لـ{{ $store->user?->name ?: 'مالك المتجر' }} بعد الجرد</button>
        </div>
    </form>
</div>
@endsection
