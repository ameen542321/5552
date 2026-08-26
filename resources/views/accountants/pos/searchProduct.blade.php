@extends('dashboard.app')

@section('title', 'بحث المنتجات - ' . $store->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 text-right space-y-5" dir="rtl">
    <x-employee.operation-page-header
        title="بحث المنتجات"
        subtitle="{{ $store->name }} — المحاسب: {{ $accountant->name }}"
        icon="🔎"
        accent="info"
    />

    <div class="ui-card p-4 shadow-xl">
        <form method="GET" action="{{ route('accountant.pos.searchProduct') }}" class="flex flex-col gap-2 sm:flex-row">
            <div class="relative flex-1">
                <input type="search"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="ابحث باسم المنتج أو الوصف أو الباركود..."
                       class="ui-input w-full py-3 pl-4 pr-11 text-sm font-bold"
                       id="searchInput" data-accountant-product-search
                       autocomplete="off"
                       autofocus>
                <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 ui-text-muted"></i>
            </div>

            <button type="submit" class="ui-btn ui-btn-primary px-6 py-3 text-sm">
                <i class="fas fa-search ui-text-caption"></i>
                بحث
            </button>

            @if(request('search') || request('category_id'))
                <a href="{{ route('accountant.pos.searchProduct') }}"
                   class="ui-btn ui-btn-secondary px-5 py-3 text-sm">
                    <i class="fas fa-times"></i>
                    إلغاء
                </a>
            @endif
        </form>

        @if(request('search'))
            <p class="mt-3 ui-text-caption ui-text-soft">نتائج البحث عن: <span class="font-bold ui-status-info">{{ request('search') }}</span></p>
        @endif
    </div>

    <div class="ui-card overflow-hidden shadow-xl">
        <div class="hidden grid-cols-12 gap-3 ui-border-bottom ui-surface-strong-bg px-4 py-3 ui-text-caption font-bold ui-text-soft md:grid">
            <div class="col-span-1">#</div>
            <div class="col-span-5">المنتج</div>
            <div class="col-span-2 text-center">الكمية</div>
            <div class="col-span-2 text-center">سعر البيع</div>
            <div class="col-span-1 text-center">الحالة</div>
            <div class="col-span-1 text-center">تفاصيل</div>
        </div>

        <div class="divide-y divide-ui-border">
            @forelse($products as $product)
                @php
                    $isSet = (bool) $product->is_set;
                    $rawQuantity = (float) $product->quantity;
                    if ($product->product_type === 'fractional' && (float) $product->roll_length > 0) {
                        $wholeRolls = (int) floor($rawQuantity / (float) $product->roll_length);
                        $remainingMeters = $rawQuantity - ($wholeRolls * (float) $product->roll_length);
                        $displayQtyLabel = trim(($wholeRolls > 0 ? $wholeRolls . ' رول' : '') . ($remainingMeters > 0 ? ' و' . rtrim(rtrim(number_format($remainingMeters, 2, '.', ''), '0'), '.') . ' متر' : '')) ?: '0 متر';
                    } elseif ($isSet && (int) $product->items_per_unit > 0) {
                        {{-- نحول الرصيد أولًا إلى حبات صحيحة ثم نعيد توزيعه إلى أطقم كاملة وباقي حبات لتجنب كسور العرض. --}}
                        $itemsPerSet = (int) $product->items_per_unit;
                        $totalPieces = max(0, (int) round($rawQuantity * $itemsPerSet));
                        $wholeSets = intdiv($totalPieces, $itemsPerSet);
                        $remainingPieces = $totalPieces % $itemsPerSet;
                        $displayQtyLabel = trim(($wholeSets > 0 ? $wholeSets . ' طقم' : '') . ($remainingPieces > 0 ? ($wholeSets > 0 ? ' و' : '') . $remainingPieces . ' حبة' : '')) ?: '0 حبة';
                    } else {
                        $displayQtyLabel = rtrim(rtrim(number_format($rawQuantity, 2, '.', ''), '0'), '.') . ' قطعة';
                    }
                    $outOfStock = (float) $product->quantity <= 0;
                    $serialNumber = $products->firstItem() + $loop->index;
                @endphp

                <div id="product-card-{{ $product->id }}" class="{{ $outOfStock ? 'ui-status-danger-bg' : 'ui-surface-bg' }}">
                    {{-- إصلاح مطبق: تفاصيل المنتج والسهم يستخدمان عقد التبديل المشترك. --}}
                    <button type="button"
                            class="grid w-full grid-cols-1 gap-3 px-4 py-4 text-right transition ui-hover-surface md:grid-cols-12 md:items-center"
                            data-ui-toggle="details_{{ $product->id }}"
                            data-ui-toggle-class-target="arrow_{{ $product->id }}"
                            data-ui-toggle-class="rotate-180">
                        <div class="hidden text-sm font-black ui-text-muted md:block md:col-span-1">{{ $serialNumber }}</div>

                        <div class="flex min-w-0 items-center gap-3 md:col-span-5">
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ui-status-info-bg ui-status-info ui-text-caption font-black md:hidden">{{ $serialNumber }}</span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black ui-title">{{ $product->name }}</p>
                                @if($product->barcode)
                                    <p class="mt-0.5 truncate ui-text-caption ui-text-muted">{{ $product->barcode }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-2 md:col-span-2 md:justify-center">
                            <span class="ui-text-caption ui-text-muted md:hidden">الكمية</span>
                            <span class="font-bold {{ $outOfStock ? 'ui-status-danger' : 'ui-status-success' }}">{{ $displayQtyLabel }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-2 md:col-span-2 md:justify-center">
                            <span class="ui-text-caption ui-text-muted md:hidden">سعر البيع</span>
                            <span class="font-bold ui-status-info">{{ number_format($product->price, 0) }} ر.س</span>
                        </div>

                        <div class="flex items-center justify-between gap-2 md:col-span-1 md:justify-center">
                            <span class="ui-text-caption ui-text-muted md:hidden">الحالة</span>
                            <span class="rounded-full px-2.5 py-1 ui-text-caption font-bold {{ $outOfStock ? 'ui-status-danger-bg ui-status-danger border ui-status-danger-border' : 'ui-status-success-bg ui-status-success ui-status-success-border' }}">
                                {{ $outOfStock ? 'منتهي' : 'متوفر' }}
                            </span>
                        </div>

                        <div class="flex justify-end md:col-span-1 md:justify-center">
                            <i class="fas fa-chevron-down ui-text-caption ui-text-muted transition-transform duration-300" id="arrow_{{ $product->id }}"></i>
                        </div>
                    </button>

                    <div id="details_{{ $product->id }}" class="hidden border-t ui-border ui-surface-strong-bg px-4 py-4">
                        <div class="grid gap-3 md:grid-cols-3">
                            <div class="ui-card-muted p-3 md:col-span-2">
                                <p class="ui-text-caption font-bold ui-text-muted">الوصف</p>
                                <p class="mt-1 text-sm leading-6 ui-title">{{ $product->description ?: 'لا يوجد وصف.' }}</p>
                            </div>

                            @if($isSet && $product->piece_price > 0)
                                <div class="ui-card-muted p-3">
                                    <p class="ui-text-caption font-bold ui-text-muted">سعر الحبة</p>
                                    <p class="mt-1 text-lg font-black ui-status-info">{{ number_format($product->piece_price, 0) }} ر.س</p>
                                </div>
                            @endif

                            @if($isSet)
                                <div class="ui-card-muted p-3">
                                    <p class="ui-text-caption font-bold ui-text-muted">تفاصيل الطقم</p>
                                    <p class="mt-1 text-sm font-bold ui-title">{{ $product->items_per_unit }} حبة في الطقم</p>
                                    <p class="mt-1 ui-text-caption ui-text-muted">الإجمالي: {{ number_format($product->total_pieces ?? ($product->quantity * ($product->items_per_unit ?: 1)), 2) }} حبة</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-16 text-center">
                    <i class="fas fa-box-open mb-4 text-5xl ui-text-muted"></i>
                    <p class="text-lg font-bold ui-text-soft">لا توجد منتجات مطابقة</p>
                    <p class="mt-2 text-sm ui-text-muted">تأكد من أن المنتج نشط ومكتمل السعر والكمية والتكلفة.</p>
                    @if(request('search') || request('category_id'))
                        <a href="{{ route('accountant.pos.searchProduct') }}"
                           class="ui-btn ui-btn-secondary mt-4 px-5 py-2 text-sm">
                            عرض جميع المنتجات
                        </a>
                    @endif
                </div>
            @endforelse
        </div>
    </div>

    @if($products->hasPages())
        <div class="ui-card p-4 shadow-xl">
            {{ $products->links() }}
        </div>
    @endif
</div>

@endsection
