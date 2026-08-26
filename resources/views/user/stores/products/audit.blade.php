@extends('dashboard.app')

@section('title', 'جرد المنتجات – ' . $store->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 text-right" dir="rtl" data-inventory-audit>
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold ui-title flex items-center gap-2">
                <i class="fa-solid fa-clipboard-check ui-status-success"></i>
                جرد المنتجات
            </h1>
            <x-ui.help title="صفحة جرد المنتجات" body="صفحة مستقلة لمتابعة حالة اكتمال بيانات الجرد." />
        </div>
        <a href="{{ route('user.stores.show', $store->id) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl ui-surface-muted-bg border ui-border ui-text-muted transition w-fit">
            <i class="fa-solid fa-arrow-right"></i>
            العودة للمتجر
        </a>
    </div>

    <div class="mb-6 rounded-2xl border ui-border ui-surface-muted-bg p-5">
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
            <div>
                <h2 class="ui-title font-bold flex items-center gap-2">
                    <i class="fa-solid fa-circle-info ui-status-info"></i>
                    ملخص جرد المنتجات
                </h2>
                {{-- مدة الدورة والقيمتان المحسوبتان تظهران معًا لمنع التباس إعداد 6 و12 شهرًا. --}}
                <div class="ui-inline-frame mt-3 ui-text-caption">
                    <span class="ui-text-soft">دورة الجرد الحالية ({{ $store->inventoryAuditCycleMonths() }} شهرًا)</span>
                    <strong class="ui-title">{{ $inventoryAuditCycleStart->format('Y-m-d') }} إلى {{ $inventoryAuditCycleEnd->format('Y-m-d') }}</strong>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 ui-text-caption">
                <div class="rounded-xl ui-surface-muted-bg border ui-border px-4 py-3 ui-text-muted">
                    <span class="block ui-text-muted">الكل</span>
                    <b class="text-2xl ui-title">{{ $inventoryAuditCounts['total'] ?? 0 }}</b>
                </div>
                <div class="rounded-xl ui-status-danger-bg border ui-border px-4 py-3">
                    <span class="inline-flex items-center gap-1 font-bold"><span class="w-2 h-2 rounded-full ui-dot-danger"></span> أحمر: {{ $inventoryAuditCounts['red'] ?? 0 }}</span>
                    <x-ui.help variant="warning" title="الحالة الحمراء" body="بيانات ناقصة أو لم تدخل الكمية بعد." />
                </div>
                <div class="rounded-xl ui-status-warning-bg border ui-border px-4 py-3">
                    <span class="inline-flex items-center gap-1 font-bold"><span class="inline-block w-2 h-2 rounded-full ui-dot-warning"></span> أصفر: {{ $inventoryAuditCounts['yellow'] ?? 0 }}</span>
                    <x-ui.help variant="warning" title="الحالة الصفراء" body="مكتمل البيانات لكن لم يؤكد جرده في دورة الجرد الحالية." />
                </div>
                <div class="rounded-xl ui-status-success-bg border ui-border px-4 py-3">
                    <span class="inline-flex items-center gap-1 font-bold"><span class="w-2 h-2 rounded-full ui-dot-success"></span> أخضر: {{ $inventoryAuditCounts['green'] ?? 0 }}</span>
                    <x-ui.help title="الحالة الخضراء" body="تم تأكيد الجرد في دورة الجرد الحالية." />
                </div>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('user.stores.products.audit', $store->id) }}" class="mb-5 ui-card p-4 flex flex-col lg:flex-row gap-3">
        <input type="text" name="search" value="{{ $searchTerm }}" placeholder="بحث باسم المنتج أو الوصف أو الباركود"
               class="flex-1 ui-card px-4 py-2.5 ui-title text-sm">
        <select name="audit_status" class="ui-card px-4 py-2.5 ui-title text-sm">
            <option value="">كل حالات الجرد</option>
            <option value="red" @selected($auditStatus === 'red')>أحمر</option>
            <option value="yellow" @selected($auditStatus === 'yellow')>أصفر</option>
            <option value="green" @selected($auditStatus === 'green')>أخضر</option>
        </select>
        <button class="ui-status-success-bg ui-title px-5 py-2.5 rounded-xl text-sm font-bold">تطبيق</button>
        @if($searchTerm || $auditStatus)
            {{-- زر ثانوي واضح يعيد الفلاتر دون تنفيذ حذف أو تعديل للبيانات. --}}
            <a href="{{ route('user.stores.products.audit', $store->id) }}" class="ui-btn ui-btn-secondary px-5 py-2.5 text-sm text-center">مسح</a>
        @endif
    </form>

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
            <div class="ui-card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="ui-title font-bold truncate flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full {{ $dotClass }} flex-shrink-0"></span>
                            {{ $product->name }}
                        </h3>
                        <p class="ui-text-muted ui-text-caption mt-1 truncate">{{ $product->category->name ?? 'غير مصنف' }}</p>
                    </div>
                    <a href="{{ route('user.stores.products.stock', ['store' => $store->id, 'product' => $product->id, 'return_to' => 'audit']) }}" class="ui-text-caption ui-status-info-bg border ui-border ui-status-info px-3 py-1.5 rounded-lg">إدارة المخزون</a>
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2 ui-text-caption">
                    <span class="ui-surface-muted-bg border ui-border rounded-lg p-2 ui-text-muted">الكمية: <b class="ui-title">{{ number_format((float) $product->quantity, 2) }}</b></span>
                    <span class="ui-surface-muted-bg border ui-border rounded-lg p-2 ui-text-muted">البيع: <b class="ui-status-info">{{ number_format((float) $product->price, 2) }}</b></span>
                    <span class="ui-surface-muted-bg border ui-border rounded-lg p-2 ui-text-muted">التكلفة: <b class="ui-status-success">{{ number_format((float) ($product->cost_price ?? 0), 2) }}</b></span>
                </div>
            </div>
        @empty
            <div class="md:col-span-2 xl:col-span-3 ui-card p-8 text-center ui-text-muted">
                لا توجد منتجات مطابقة للبحث أو الفلتر الحالي.
            </div>
        @endforelse
    </div>
    <div class="mt-5">{{ $products->links() }}</div>
</div>
@endsection
