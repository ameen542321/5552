@extends('dashboard.app')

@section('title', 'المنتجات – متجر ' . $store->name)

@section('content')

<div class="owner-products-page max-w-7xl mx-auto px-4 py-6 text-right overflow-x-hidden" dir="rtl" data-store-products-catalog x-data="storeProductsLookup" x-init="init()">

    {{-- ===== الهيدر العلوي ===== --}}
    <div class="mb-6 ui-surface-muted-bg p-4 rounded-2xl border ui-border">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('user.stores.show', $store->id) }}"
                   class="p-2 rounded-lg ui-surface-muted-bg border ui-border ui-text-muted ui-hover-info transition-all duration-200">
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold ui-title flex items-center gap-2">
                        <i class="fas fa-boxes ui-status-success"></i>
                        المنتجات
                    </h1>
                    <p class="ui-text-muted text-sm mt-1">{{ $store->name }}</p>
                </div>
            </div>

            <a href="{{ route('user.stores.products.create', ['store' => $store->id, 'category_id' => request('category_id')]) }}"
               class="ui-btn ui-btn-primary px-4 py-2.5">
                <i class="fa-solid fa-plus"></i>
                <span>إضافة منتج</span>
            </a>
        </div>
    </div>

    {{-- ===== كروت الإحصائيات السريعة ===== --}}
    @php
        $totalProductsCount = $stats->total_count ?? 0;
        $totalCostValue = $stats->total_cost ?? 0;
        $totalStockValue = $stats->total_value ?? 0;
        $lowStockCount = $stats->low_stock_count ?? 0;
    @endphp

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6 ui-text-caption sm:text-sm">
        <div class="ui-surface-muted-bg p-4 rounded-2xl border ui-border">
            <p class="ui-text-muted ui-text-caption">إجمالي التكلفة</p>
            {{-- القيم المالية تُعرض بدقتين حتى لا يتغير سعر التكلفة بسبب التقريب إلى عدد صحيح. --}}
            <p class="ui-status-success font-bold text-lg sm:text-xl">{{ number_format((float) $totalCostValue, 2) }} <span class="ui-text-caption ui-text-muted">ر.س</span></p>
        </div>
        <div class="ui-surface-muted-bg p-4 rounded-2xl border ui-border">
            <p class="ui-text-muted ui-text-caption">القيمة السوقية</p>
            <p class="ui-status-info font-bold text-lg sm:text-xl">{{ number_format($totalStockValue, 0) }} <span class="ui-text-caption ui-text-muted">ر.س</span></p>
        </div>
        <div class="ui-surface-muted-bg p-4 rounded-2xl border ui-border">
            <p class="ui-text-muted ui-text-caption">عدد المنتجات</p>
            <p class="ui-status-info font-bold text-lg sm:text-xl">{{ $totalProductsCount }}</p>
        </div>
        <div class="ui-surface-muted-bg p-4 rounded-2xl border ui-border">
            <p class="ui-text-muted ui-text-caption">المخزون المنخفض</p>
            <p class="ui-status-info font-bold text-lg sm:text-xl">{{ $lowStockCount }}</p>
        </div>
    </div>

    <details class="mb-6 ui-card overflow-hidden">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4">
            <div class="flex items-center gap-2"><i class="fa-solid fa-clipboard-check ui-status-success"></i><span class="ui-title font-bold">ملخص جرد المنتجات</span></div>
            <span class="ui-text-caption ui-text-soft">{{ $inventoryAuditCounts['green'] ?? 0 }} مكتمل من {{ $inventoryAuditCounts['total'] ?? 0 }}</span>
        </summary>
        <div class="border-t ui-border p-4">
            <p class="ui-text-muted ui-text-caption">دورة الجرد الحالية ({{ $store->inventoryAuditCycleMonths() }} شهرًا): <strong class="ui-title">{{ $inventoryAuditCycleStart->format('Y-m-d') }} إلى {{ $inventoryAuditCycleEnd->format('Y-m-d') }}</strong></p>
            <div class="mt-3 flex flex-wrap items-center gap-2 ui-text-caption">
                <span class="rounded-lg ui-surface-muted-bg px-3 py-1.5 ui-text-muted font-bold">الكل: {{ $inventoryAuditCounts['total'] ?? 0 }}</span>
                <span class="rounded-lg ui-status-danger-bg px-3 py-1.5 ui-status-danger"><b>أحمر: {{ $inventoryAuditCounts['red'] ?? 0 }}</b></span>
                <span class="rounded-lg ui-status-warning-bg px-3 py-1.5 ui-status-warning"><b>أصفر: {{ $inventoryAuditCounts['yellow'] ?? 0 }}</b></span>
                <span class="rounded-lg ui-status-success-bg px-3 py-1.5 ui-status-success"><b>أخضر: {{ $inventoryAuditCounts['green'] ?? 0 }}</b></span>
            </div>
            <a href="{{ route('user.stores.products.audit', $store->id) }}" class="ui-btn ui-btn-primary mt-4"><i class="fa-solid fa-arrow-left"></i> فتح صفحة الجرد</a>
        </div>
    </details>

    {{-- ===== الفلترة والبحث ===== --}}
    <div class="mb-6 ui-surface-muted-bg p-4 rounded-2xl border ui-border">
        <form method="GET" action="{{ route('user.stores.products.index', $store->id) }}" class="flex flex-col lg:flex-row gap-2 w-full">
            <div class="relative flex-grow">
                <input type="text" name="search" value="{{ request('search') }}"
                       x-model="searchQuery"
                       @input="filterClientProducts"
                       placeholder="🔍 بحث عن منتج..."
                       class="ui-card py-2.5 px-4 pr-10 text-sm ui-title w-full"
                       id="searchInput">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 ui-text-muted"></i>
            </div>

            <select name="category_id" class="ui-card py-2.5 px-4 text-sm ui-title w-full lg:w-auto">
                <option value="">📁 جميع الأقسام</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>

            <button type="submit" class="ui-status-success-bg ui-title px-6 py-2.5 rounded-xl transition flex items-center gap-2 justify-center">
                <i class="fas fa-search"></i>
                <span>بحث</span>
            </button>

            @if(request('search') || request('category_id') || request('show_owner_purchase'))
                <a href="{{ route('user.stores.products.index', $store->id) }}"
                   class="ui-surface-muted-bg ui-title px-4 py-2.5 rounded-xl transition flex items-center gap-2 justify-center">
                    <i class="fas fa-times"></i>
                    <span>إلغاء</span>
                </a>
            @endif
        </form>

        <div class="mt-3 flex justify-end">
            <a href="{{ route('user.stores.products.index', array_merge(['store' => $store->id], request()->except(['page', 'show_owner_purchase']), ['show_owner_purchase' => $showOwnerPurchase ? 0 : 1])) }}"
               class="ui-btn ui-btn-secondary px-3 py-2 ui-text-caption">
                <i class="fa-solid {{ $showOwnerPurchase ? 'fa-box-open' : 'fa-box-archive' }}" aria-hidden="true"></i>
                {{ $showOwnerPurchase ? 'العودة إلى منتجات البيع' : 'عرض مشتريات المالك' }}
            </a>
        </div>

        <div class="mt-3 ui-text-caption ui-text-muted">
            تلميح سريع: اضغط <kbd class="px-1.5 py-0.5 rounded ui-surface-muted-bg border ui-border ui-text-muted">/</kbd> للانتقال مباشرة إلى مربع البحث.
        </div>

        <div class="mt-3 border ui-border rounded-xl p-2 ui-surface-muted-bg max-h-52 overflow-y-auto">
            <div class="mb-2 flex items-center justify-between gap-2 px-1 ui-text-caption ui-text-muted">
                <span class="font-bold ui-text-muted" x-text="searchQuery ? 'نتائج البحث السريع' : 'أحدث المنتجات المباعة'"></span>
                <span x-show="!searchQuery" x-text="filteredProducts.length ? (filteredProducts.length + ' منتج') : 'لا توجد مبيعات بعد'"></span>
                <span x-show="searchQuery" x-text="filteredProducts.length ? ('نتائج سريعة: ' + filteredProducts.length) : 'لا توجد نتائج'"></span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                <template x-for="p in visibleProducts()" :key="p.id">
                    <button type="button" @click="openProductCard(p)"
                        class="text-right ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 transition">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span class="inline-flex w-2.5 h-2.5 rounded-full flex-shrink-0"
                                      :class="p.audit_dot_class"
                                      ></span>
                                <p class="font-bold text-sm ui-title truncate" x-text="p.name"></p>
                            </div>
                            <span class="ui-text-caption ui-text-muted truncate max-w-[120px]" x-text="p.category_label"></span>
                        </div>
                        <div class="flex items-center justify-between gap-3 ui-section-divider ui-section-divider-sm">
                            <p class="ui-text-caption ui-status-success font-bold" x-text="p.price_label"></p>
                            <p class="ui-text-caption ui-text-soft" x-text="p.stock_label"></p>
                        </div>
                        <div class="mt-1 flex items-center justify-end gap-2">
                            <span class="ui-text-caption ui-status-success">فتح البطاقة</span>
                        </div>
                    </button>
                </template>
            </div>
            <div x-show="filteredProducts.length === 0" class="ui-text-caption ui-status-warning p-2">
                لا توجد منتجات مطابقة للبحث السريع.
            </div>
            <div class="pt-2" x-show="!searchQuery && filteredProducts.length > 5">
                <button type="button" @click="showAllMatches = !showAllMatches"
                        class="w-full ui-text-caption py-2 rounded-lg border ui-border ui-surface-muted-bg ui-text-muted transition">
                    <span x-text="showAllMatches ? 'إظهار أول 5 نتائج فقط' : 'عرض كل النتائج'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ===== بطاقات المنتجات (قابلة للطي) ===== --}}
    <div class="space-y-3">
        @forelse($products as $index => $product)
            @php
                $isFractional = ($product->product_type === 'fractional' && $product->roll_length > 0);
                $isSet = ($product->is_splittable && $product->items_per_unit > 0);
                $bgColor = $loop->iteration % 2 == 0
                    ? 'ui-surface-muted-bg'
                    : 'ui-surface-bg';

                if ($isFractional) {
                    if ($product->roll_length > 0) {
                        $rolls = $product->quantity / $product->roll_length;
                        $totalMeters = number_format($product->quantity, 2);
                        $lowStock = $rolls <= $product->min_stock;
                    } else {
                        $totalMeters = number_format($product->quantity, 2);
                        $lowStock = $product->quantity <= $product->min_stock;
                    }
                } elseif ($isSet) {
                    $itemsPerUnit = $product->items_per_unit ?: 1;
                    $totalSets = $product->quantity;
                    $totalPieces = $totalSets * $itemsPerUnit;
                    $lowStock = $totalSets <= $product->min_stock;
                } else {
                    $lowStock = $product->quantity <= $product->min_stock;
                }
                $defaultSaleUnit = $isSet && ($product->quick_sale_default_unit ?? 'unit') === 'piece' ? 'piece' : 'unit';
                $stockDisplayLabel = \App\Support\ProductQuantityFormatter::currentStock($product, $defaultSaleUnit);
                $setCompositionLabel = $isSet
                    ? \App\Support\ProductQuantityFormatter::currentStock($product, 'unit')
                    : null;
                $minStockDisplayLabel = \App\Support\ProductQuantityFormatter::minimumStock($product, $defaultSaleUnit);

                $setSalePrice = (float) $product->price;
                $pieceSalePrice = (float) $product->piece_price;
                $setCostPrice = (float) ($product->cost_price ?? 0);
                $pieceCostPrice = $isSet && $product->items_per_unit > 0
                    ? $setCostPrice / (float) $product->items_per_unit
                    : $setCostPrice;
                $primarySaleLabel = $isSet && $defaultSaleUnit === 'piece' ? 'الحبة' : ($isSet ? 'الطقم' : ($isFractional ? 'الرول' : 'الحبة'));
                $primarySalePrice = $isSet && $defaultSaleUnit === 'piece' ? $pieceSalePrice : $setSalePrice;
                $secondarySaleLabel = $isSet ? ($defaultSaleUnit === 'piece' ? 'الطقم' : 'الحبة') : null;
                $secondarySalePrice = $isSet ? ($defaultSaleUnit === 'piece' ? $setSalePrice : $pieceSalePrice) : null;
                $primaryCostLabel = $isSet && $defaultSaleUnit === 'piece' ? 'الحبة' : ($isSet ? 'الطقم' : ($isFractional ? 'الرول' : 'الحبة'));
                $primaryCostPrice = $isSet && $defaultSaleUnit === 'piece' ? $pieceCostPrice : $setCostPrice;
                $secondaryCostLabel = $isSet ? ($defaultSaleUnit === 'piece' ? 'الطقم' : 'الحبة') : null;
                $secondaryCostPrice = $isSet ? ($defaultSaleUnit === 'piece' ? $setCostPrice : $pieceCostPrice) : null;
                $serialNumber = $loop->iteration;

                $headerPriceLabel = 'الحبة';
                $headerPriceValue = $product->price;
                $isOwnerPurchaseOnly = $product->isOwnerPurchaseOnly();
                $inventoryAudit = $isOwnerPurchaseOnly ? null : $product->inventoryAuditStatus($store);
                $inventoryAuditDot = $inventoryAudit ? ([
                    'red' => 'ui-dot-danger',
                    'yellow' => 'ui-dot-warning',
                    'green' => 'ui-dot-success',
                ][$inventoryAudit['color']] ?? 'ui-surface-muted-bg') : 'ui-dot-warning';

                if ($isSet) {
                    if ($defaultSaleUnit === 'piece' && ($product->piece_price ?? 0) > 0) {
                        $headerPriceLabel = 'الحبة';
                        $headerPriceValue = $product->piece_price;
                    } else {
                        $headerPriceLabel = 'الطقم';
                        $headerPriceValue = $product->price;
                    }
                } elseif ($isFractional) {
                    $headerPriceLabel = $product->roll_length > 0 ? 'الرول' : 'المتر';
                    $headerPriceValue = $product->price;
                }
            @endphp

            <div id="product-card-{{ $product->id }}"
                 class="{{ $bgColor }} rounded-xl border ui-border transition-all hover:shadow-lg">
                {{-- رأس البطاقة (دائماً ظاهر) --}}
                <div class="px-3.5 py-2.5 flex items-center justify-between gap-3 cursor-pointer ui-surface-muted-bg transition-colors" data-product-details-toggle data-details-id="details_{{ $product->id }}" data-arrow-id="arrow_{{ $product->id }}">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="ui-text-muted ui-title font-bold ui-surface-muted-bg  w-7 h-7 rounded-lg flex items-center justify-center ui-text-caption flex-shrink-0">{{ $serialNumber }}</span>
                            @if(! $isOwnerPurchaseOnly)
                                <span class="inline-flex w-2 h-2 rounded-full {{ $inventoryAuditDot }} flex-shrink-0"></span>
                            @endif
                            <span class="ui-text-muted ui-title text-sm font-bold truncate">{{ $product->name }}</span>
                        </div>
                        <p class="ui-text-caption ui-text-muted  mt-1 truncate">
                            النوع: {{ $isSet ? ($defaultSaleUnit === 'piece' ? 'حبة' : 'طقم') : ($isFractional ? 'رول' : 'عادي') }}
                            <span class="mx-1">•</span>
                            @if($isOwnerPurchaseOnly)
                                <span class="ui-status-warning font-semibold">مستبعد من الجرد والمخزون</span>
                            @else
                                المخزون: <span class="{{ $lowStock ? 'ui-status-danger' : 'ui-status-success' }} font-semibold">{{ $stockDisplayLabel }}</span>
                            @endif
                            <span class="mx-1">•</span>
                            {{ $headerPriceLabel }}: <span class="ui-status-info font-semibold">{{ number_format($headerPriceValue, 0) }} ر.س</span>
                        </p>
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($product->usage_type === \App\Models\Product::USAGE_TYPE_OWNER_PURCHASE)
                            <span class="ui-text-caption px-2 py-1 rounded-lg whitespace-nowrap ui-status-warning-bg ui-status-warning border ui-border">
                                مشتريات للمالك
                            </span>
                        @endif
                        <span class="ui-text-caption px-2 py-1 rounded-lg whitespace-nowrap {{ $product->status === 'active' ? 'ui-status-success-bg ui-status-success' : 'ui-status-warning-bg ui-status-warning' }}">
                            <span class="hidden xs:inline">{{ $product->status === 'active' ? 'نشط' : 'مخفي' }}</span>
                            <i class="fas {{ $product->status === 'active' ? 'fa-eye' : 'fa-eye-slash' }} xs:hidden"></i>
                        </span>
                        <i class="fas fa-chevron-down ui-text-muted  ui-text-caption transition-transform duration-300" id="arrow_{{ $product->id }}"></i>
                    </div>
                </div>

                <div id="details_{{ $product->id }}" class="hidden border-t ui-border  p-4 ui-surface-muted-bg ">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div class="lg:col-span-2 space-y-4">
                            <div>
                                <h3 class="ui-text-muted ui-title font-bold text-lg">{{ $product->name }}</h3>
                                @if($product->description)
                                    <p class="ui-text-muted text-sm mt-1 break-words">{{ $product->description }}</p>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 items-stretch">
                                <div class="ui-surface-muted-bg  p-3 rounded-lg border ui-border  h-full flex flex-col justify-between">
                                    <p class="ui-text-muted ui-text-caption">الكمية</p>
                                    <p class="ui-text-muted ui-title font-bold">{{ $stockDisplayLabel }}</p>
                                </div>
                                @if($isSet)
                                <div class="ui-status-info-bg  p-3 rounded-lg border ui-border  h-full flex flex-col justify-between">
                                    <p class="ui-status-info ui-text-caption">مكونات</p>
                                    <p class="ui-text-muted ui-title font-bold">{{ $setCompositionLabel }}</p>
                                    <p class="ui-text-muted ui-text-caption">الطقم = {{ number_format($product->items_per_unit, 0) }} حبة</p>
                                </div>
                                @endif
                                @if($isFractional && $product->roll_length > 0)
                                <div class="ui-status-info-bg  p-3 rounded-lg border ui-border  h-full flex flex-col justify-between">
                                    <p class="ui-status-info ui-text-caption">طول الرول</p>
                                    <p class="ui-text-muted ui-title font-bold">{{ $product->roll_length }} متر</p>
                                    <p class="ui-text-muted ui-text-caption">إجمالي {{ $totalMeters }} متر</p>
                                </div>
                                @endif
                                <div class="ui-surface-muted-bg  p-3 rounded-lg border ui-border  h-full flex flex-col justify-between">
                                    <p class="ui-text-muted ui-text-caption">الحد الأدنى</p>
                                    <p class="ui-text-muted ui-title font-bold">{{ $minStockDisplayLabel }}</p>
                                </div>
                                <div class="ui-surface-muted-bg  p-3 rounded-lg border ui-border  h-full flex flex-col justify-between">
                                    <p class="ui-text-muted ui-text-caption">القسم</p>
                                    <p class="ui-status-info">{{ $product->category->name ?? 'غير مصنف' }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-stretch">
                                <div class="ui-surface-muted-bg p-3 rounded-lg border ui-border h-full flex flex-col justify-between">
                                    <p class="ui-text-muted ui-text-caption flex items-center gap-1">
                                        <i class="fa-regular fa-calendar-plus ui-status-success"></i>
                                        تاريخ الإضافة
                                    </p>
                                    <p class="ui-title text-sm font-medium" dir="ltr">
                                        {{ $product->created_at ? $product->created_at->format('Y-m-d') : '--' }}
                                    </p>
                                    <p class="ui-text-muted ui-text-caption" dir="ltr">
                                        {{ $product->created_at ? $product->created_at->format('h:i A') : '' }}
                                    </p>
                                </div>
                                <button type="button"
                                        class="ui-surface-muted-bg p-3 rounded-lg border ui-border h-full flex flex-col justify-between text-right transition"
                                        data-price-history-url="{{ route('user.stores.products.price-history', [$store->id, $product->id]) }}">
                                    <p class="ui-text-muted ui-text-caption flex items-center gap-1">
                                        <i class="fa-regular fa-calendar-check ui-status-info"></i>
                                        آخر تعديل
                                        <span class="ui-status-info">(سجل السعر)</span>
                                    </p>
                                    <p class="ui-title text-sm font-medium" dir="ltr">
                                        {{ $product->updated_at ? $product->updated_at->format('Y-m-d') : '--' }}
                                    </p>
                                    <p class="ui-text-muted ui-text-caption" dir="ltr">
                                        {{ $product->updated_at ? $product->updated_at->format('h:i A') : '' }}
                                    </p>
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-stretch">
                                <div class="ui-surface-muted-bg  p-3 rounded-lg border ui-border  h-full flex flex-col justify-between">
                                    <p class="ui-text-muted ui-text-caption">سعر البيع</p>
                                    <p class="ui-status-info font-bold text-lg sm:text-xl">{{ $primarySaleLabel }}: {{ number_format($primarySalePrice, 0) }} <span class="ui-text-caption ui-text-muted">ر.س</span></p>
                                    @if($isSet)
                                        <p class="ui-text-muted ui-text-caption">{{ $secondarySaleLabel }}: {{ number_format($secondarySalePrice, 0) }} ر.س</p>
                                    @endif
                                </div>
                                <div class="ui-surface-muted-bg  p-3 rounded-lg border ui-border  h-full flex flex-col justify-between">
                                    <p class="ui-text-muted ui-text-caption">سعر التكلفة</p>
                                    <p class="ui-status-success font-bold text-lg sm:text-xl">{{ $primaryCostLabel }}: {{ number_format($primaryCostPrice, 2) }} <span class="ui-text-caption ui-text-muted">ر.س</span></p>
                                    @if($isSet)
                                        <p class="ui-text-muted ui-text-caption">{{ $secondaryCostLabel }}: {{ number_format($secondaryCostPrice, 2) }} ر.س</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="ui-surface-muted-bg p-4 rounded-lg">
                                <h3 class="ui-title font-bold mb-3 text-sm">التحكم</h3>
                                <div class="space-y-2">
                                    @if(! $product->isOwnerPurchaseOnly())
                                        <a href="{{ route('user.stores.products.stock', [$store->id, $product->id]) }}"
                                           class="w-full flex items-center justify-center gap-2 py-2.5 ui-status-info-bg ui-status-info rounded-lg transition-colors duration-200 border ui-border">
                                            <i class="fa-solid fa-warehouse"></i>
                                            <span>إدارة المخزون</span>
                                        </a>
                                    @endif

                                    <a href="{{ route('user.stores.products.edit', [$store->id, $product->id]) }}"
                                       class="w-full flex items-center justify-center gap-2 py-2.5 ui-status-info-bg ui-status-info rounded-lg transition-colors duration-200 border ui-border">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                        <span>تعديل المنتج</span>
                                    </a>

                                    <form action="{{ route('user.stores.products.toggle-status', [$store->id, $product->id]) }}"
                                          method="POST"
                                          data-ui-confirm="هل تريد {{ $product->status === 'active' ? 'إخفاء' : 'تفعيل' }} المنتج؟"
                                          data-ui-confirm-title="تأكيد حالة المنتج">
                                        @csrf @method('PUT')
                                        <button type="submit"
                                                class="w-full flex items-center justify-center gap-2 py-2.5 {{ $product->status === 'active' ? 'ui-status-warning-bg ui-status-warning border ui-border' : 'ui-status-success-bg ui-status-success' }} rounded-lg transition-colors duration-200">
                                            <i class="fa-solid {{ $product->status === 'active' ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                            <span>{{ $product->status === 'active' ? 'إخفاء المنتج' : 'تفعيل المنتج' }}</span>
                                        </button>
                                    </form>

                                    <form action="{{ route('user.stores.products.destroy', [$store->id, $product->id]) }}"
                                          method="POST"
                                          data-ui-confirm="هل تريد نقل المنتج إلى سلة المحذوفات؟"
                                          data-ui-confirm-title="نقل المنتج إلى السلة">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="ui-btn ui-btn-danger w-full py-2.5">
                                            <i class="fa-solid fa-trash-can"></i>
                                            <span>حذف المنتج</span>
                                        </button>
                                    </form>

                                    @if($product->latestInventoryAuditMovement)
                                        @php
                                            $auditMovement = $product->latestInventoryAuditMovement;
                                            $auditNote = trim((string) \Illuminate\Support\Str::after((string) $auditMovement->note, '—'));
                                        @endphp
                                        <div class="ui-status-info-bg ui-status-info rounded-lg p-3 text-center">
                                            <p class="ui-text-caption font-bold">تأكيد الجرد: {{ $auditMovement->created_at->format('Y-m-d') }}</p>
                                            @if($auditNote !== '' && $auditNote !== $auditMovement->note)
                                                <p class="ui-text-caption ui-text-soft mt-1 break-words">{{ $auditNote }}</p>
                                            @endif
                                        </div>
                                    @else
                                        <div class="ui-surface-muted-bg ui-text-muted rounded-lg p-3 text-center">
                                            <p class="ui-text-caption font-bold">لا يوجد جرد</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-16 ui-surface-muted-bg rounded-2xl border ui-border">
                <i class="fas fa-box-open text-5xl ui-text-muted mb-4"></i>
                <p class="ui-text-muted text-lg">لا توجد منتجات</p>
                @if(request('search') || request('category_id') || request('show_owner_purchase'))
                    <a href="{{ route('user.stores.products.index', $store->id) }}"
                       class="mt-4 inline-block ui-surface-muted-bg ui-title px-6 py-2 rounded-xl">
                        عرض جميع المنتجات
                    </a>
                @endif
            </div>
        @endforelse
    </div>

    {{-- ===== الترقيم وسلة المحذوفات ===== --}}
    <div class="mt-6 p-4 ui-surface-muted-bg rounded-xl border ui-border flex flex-col lg:flex-row items-center justify-between gap-4">
        <div class="w-full lg:w-auto owner-products-pagination">
            {{ $products->links('pagination::simple-tailwind') }}
        </div>

        <a href="{{ route('user.stores.products.trash', $store->id) }}"
           class="flex items-center gap-2 ui-text-muted ui-status-danger transition-colors text-sm py-2 px-4 rounded-lg ui-surface-muted-bg border ui-border">
            <i class="fa-solid fa-trash-can-arrow-up"></i>
            <span>سلة المحذوفات</span>
            <span class="ui-status-danger-bg ui-status-danger px-2 py-0.5 rounded-lg ui-text-caption">{{ $trashedCount }}</span>
        </a>
    </div>

    {{-- ===== أدوات الاستيراد/التصدير (بنهاية الصفحة) ===== --}}
    <div class="mt-6 ui-status-info-bg border ui-border rounded-2xl p-4">
        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3">
            <p class="ui-status-info ui-text-caption sm:text-sm">
                <i class="fa-solid fa-circle-info ml-1"></i>
                أدوات النقل: الكمية في ملف CSV تكون دائمًا صفر، وإذا كان أحد السعرين غير مكتمل يتم تصفير سعر البيع والتكلفة معًا.
            </p>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('user.stores.products.export.csv', $store->id) }}"
                   class="ui-status-info-bg ui-title px-4 py-2.5 rounded-xl transition-all duration-200 flex items-center gap-2">
                    <i class="fa-solid fa-file-csv"></i>
                    <span>تصدير CSV</span>
                </a>

                <form action="{{ route('user.stores.products.import.csv', $store->id) }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2 ui-card px-2 py-1.5">
                    @csrf
                    <input type="file" name="csv_file" accept=".csv,text/csv" required class="ui-text-caption ui-text-muted file:ml-2 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:ui-surface-muted-bg file:ui-title hover:file:ui-surface-muted-bg">
                    <button type="submit" class="ui-status-warning-bg ui-title px-3 py-2 rounded-lg text-sm flex items-center gap-2">
                        <i class="fa-solid fa-file-import"></i>
                        <span>استيراد</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

@php
    $quickProducts = collect($quickSearchProducts ?? [])->map(function ($product) use ($store) {
        $audit = $product->inventoryAuditStatus($store);
        $auditDotClass = [
            'red' => 'ui-dot-danger',
            'yellow' => 'ui-dot-warning',
            'green' => 'ui-dot-success',
        ][$audit['color']] ?? 'ui-surface-muted-bg';
        $quickStockLabel = $product->product_type === 'fractional'
            ? rtrim(rtrim(number_format((float) $product->quantity, 2, '.', ''), '0'), '.') . ' متر'
            : ($product->is_splittable
                ? \App\Support\ProductQuantityFormatter::currentStock(
                $product,
                ($product->quick_sale_default_unit ?? 'unit') === 'piece' ? 'piece' : 'unit'
                )
                : \App\Support\ProductQuantityFormatter::stockSnapshot($product, (float) $product->quantity));
        $isQuickSet = (bool) $product->is_splittable && (int) $product->items_per_unit > 1;
        $quickDefaultUnit = $isQuickSet && ($product->quick_sale_default_unit ?? 'unit') === 'piece' ? 'piece' : 'unit';
        $quickSetSalePrice = (float) $product->price;
        $quickPieceSalePrice = (float) $product->piece_price;
        $quickPrimaryPriceLabel = $isQuickSet && $quickDefaultUnit === 'piece' ? 'الحبة' : ($isQuickSet ? 'الطقم' : ($product->product_type === 'fractional' ? 'الرول' : 'الحبة'));
        $quickPrimaryPrice = $isQuickSet && $quickDefaultUnit === 'piece' ? $quickPieceSalePrice : $quickSetSalePrice;
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'barcode' => $product->barcode,
            'category_label' => $product->category?->name ?: 'غير مصنف',
            'price_label' => $quickPrimaryPriceLabel . ': ' . number_format($quickPrimaryPrice, 0) . ' ر.س',
            'stock_label' => 'الكمية: ' . $quickStockLabel,
            'audit_dot_class' => $auditDotClass,
            'audit_label' => $audit['label'],
            'audit_message' => $audit['message'],
            'card_url' => route('user.stores.products.index', [
                'store' => $store->id,
                'search' => $product->name,
                'highlight_product' => $product->id,
            ]),
        ];
    })->values();

    $productCatalogConfig = [
        'searchQuery' => request('search', ''),
        'hasServerResults' => ($products->count() ?? 0) > 0,
        'products' => $quickProducts,
        'highlightedProductId' => (int) request('highlight_product', 0),
    ];
@endphp

<script type="application/json" data-store-products-catalog-config>@json($productCatalogConfig)</script>


{{-- مودال واحد فقط لسجل أسعار المنتجات المعروضة --}}
<div id="priceHistoryModal" class="ui-modal-backdrop hidden" dir="rtl">
    <div class="ui-modal-panel ui-modal-panel-wide">
        <div class="ui-modal-header">
            <div>
                <h3 class="ui-title font-bold text-lg">سجل تغيّر سعر المنتج</h3>
                <p id="priceHistoryProductName" class="ui-text-muted ui-text-caption mt-1">--</p>
            </div>
            <button type="button" data-price-history-close class="ui-modal-close-text-danger">إغلاق</button>
        </div>
        <div class="p-4">
            <div id="priceHistoryCurrent" class="mb-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm"></div>
            <div id="priceHistoryLoading" class="text-center ui-text-muted py-8 hidden">جاري تحميل السجل...</div>
            <div id="priceHistoryEmpty" class="text-center ui-text-muted py-8 hidden">لا توجد تغييرات مسجلة على سعر البيع أو سعر التكلفة حتى الآن.</div>
            <div class="ui-table-wrap">
                <table class="ui-table text-sm">
                    <thead class="ui-text-muted border-b ui-border">
                        <tr>
                            <th class="py-2 px-2">التاريخ</th>
                            <th class="py-2 px-2">سعر البيع</th>
                            <th class="py-2 px-2">سعر التكلفة</th>
                            <th class="py-2 px-2">بواسطة</th>
                        </tr>
                    </thead>
                    <tbody id="priceHistoryRows" class="divide-y divide-ui-border ui-text-muted"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
