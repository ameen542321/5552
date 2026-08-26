@extends('dashboard.app')

@section('title', 'أقسام متجر ' . $store->name)

@section('content')

<div class="max-w-7xl mx-auto px-4 py-6 sm:py-10" data-category-system>

{{-- العنوان + الأزرار --}}
<header class="ui-card mb-8 flex flex-col gap-5 p-5 md:flex-row md:items-center md:justify-between">

    {{-- زر الرجوع --}}
    <a href="{{ route('user.stores.show', $store->id) }}"
       class="ui-btn ui-btn-secondary w-fit">
        <i class="fa-solid fa-arrow-right text-sm"></i>
        <span class="text-sm font-medium">رجوع</span>
    </a>

    {{-- العنوان --}}
    <h1 class="text-2xl font-black ui-title sm:text-3xl">
        <i class="fa-solid fa-layer-group ml-2 ui-status-info"></i>
        الأقسام
    </h1>

    {{-- الأزرار --}}
    <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:items-center sm:gap-3">

        {{-- زر إضافة نشاط --}}
        <a href="{{ route('user.stores.categories.create', ['store' => $store->id, 'is_main_category' => 1]) }}"
           class="ui-btn ui-btn-primary">
            <i class="fa-solid fa-plus ml-2"></i>
            إضافة نشاط
        </a>

        {{-- زر إضافة قسم --}}
        <a href="{{ route('user.stores.categories.create', ['store' => $store->id, 'is_main_category' => 0]) }}"
           class="ui-btn ui-btn-secondary">
            <i class="fa-solid fa-plus ml-2"></i>
            إضافة قسم
        </a>

    </div>

</header>

{{-- إحصائيات سريعة --}}
@php
    // حساب إحصائيات الأقسام
    $categoriesStats = App\Models\Category::where('store_id', $store->id)
        ->whereNull('deleted_at')
        ->withCount(['products' => function($query) {
            $query->whereNull('deleted_at');
        }])
        ->get();

    $totalActiveProducts = $categoriesStats->sum('products_count');
    $totalActiveCategories = $categoriesStats->count();
    $categoriesWithProducts = $categoriesStats->filter(fn($cat) => $cat->products_count > 0)->count();
@endphp

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">
    {{-- عدد الأقسام النشطة --}}
    <div class="ui-card p-5 text-center">
        <i class="fa-solid fa-layer-group ui-status-info text-3xl mb-3"></i>
        <h3 class="ui-text-muted text-sm mb-1">الأقسام النشطة</h3>
        <p class="text-3xl font-bold ui-status-info">
            {{ $totalActiveCategories }}
            <span class="text-sm ui-text-muted">قسم</span>
        </p>
    </div>

    {{-- إجمالي المنتجات --}}
    <div class="ui-card p-5 text-center">
        <i class="fa-solid fa-box ui-status-info text-3xl mb-3"></i>
        <h3 class="ui-text-muted text-sm mb-1">إجمالي المنتجات</h3>
        <p class="text-3xl font-bold ui-status-info">
            {{ $totalActiveProducts }}
            <span class="text-sm ui-text-muted">منتج</span>
        </p>
    </div>

    {{-- الأقسام تحتوي منتجات --}}
    <div class="ui-card p-5 text-center">
        <i class="fa-solid fa-folder-open ui-status-success text-3xl mb-3"></i>
        <h3 class="ui-text-muted text-sm mb-1">أقسام تحتوي منتجات</h3>
        <p class="text-3xl font-bold ui-status-success">
            {{ $categoriesWithProducts }}
            <span class="text-sm ui-text-muted">قسم</span>
        </p>
    </div>
</div>

@php
    $mainCategories = $categories->where('is_main_category', 1);
    $normalCategories = $categories->where('is_main_category', 0);
@endphp

{{-- في حال لا توجد أقسام --}}
@if($categories->isEmpty())
    <div class="ui-surface-muted-bg border ui-border p-10 rounded-xl text-center shadow-lg">
        <i class="fa-solid fa-folder-open ui-text-muted text-6xl mb-4"></i>
        <p class="ui-text-muted text-lg">لا توجد أقسام حتى الآن</p>
    </div>

@else

    {{-- الأنشطة --}}
    @if($mainCategories->isNotEmpty())
        <h2 class="text-xl font-bold ui-title mb-4">الأنشطة</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            @foreach($mainCategories as $category)
                @php
                    // حساب عدد المنتجات في هذا القسم
                    $productsCount = App\Models\Product::where('store_id', $store->id)
                        ->where('category_id', $category->id)
                        ->whereNull('deleted_at')
                        ->count();

                    $productsValue = App\Models\Product::where('store_id', $store->id)
                        ->where('category_id', $category->id)
                        ->whereNull('deleted_at')
                        ->sum('price');
                @endphp

                {{-- بطاقة النشاط --}}
                <div class="ui-surface-muted-bg border ui-border p-6 rounded-xl transition duration-200 shadow-sm">

                    {{-- العنوان + زر تعديل --}}
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-bold ui-title truncate">
                                {{ $category->name }}
                            </h2>
                            <span class="ui-status-info ui-text-caption" title="نشاط رئيسي">
                                <i class="fa-solid fa-star"></i>
                            </span>
                        </div>

                        <a href="{{ route('user.stores.categories.edit', [$store->id, $category->id]) }}"
                           class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg ui-status-info-bg border ui-border ui-hover-info transition ui-text-caption font-bold">
                            <i class="fa-solid fa-pen-to-square"></i>
                            <span>تعديل</span>
                        </a>
                    </div>

                    {{-- الوصف --}}
                    <p class="ui-text-muted text-sm mb-4 line-clamp-2 leading-relaxed">
                        {{ $category->description ?: 'لا يوجد وصف' }}
                    </p>

                    {{-- إحصائيات القسم --}}
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="ui-surface-muted-bg p-2 rounded-lg text-center">
                            <div class="ui-text-caption ui-text-muted mb-1">المنتجات</div>
                            <div class="text-lg font-bold ui-status-info">{{ $productsCount }}</div>
                        </div>
                        <div class="ui-surface-muted-bg p-2 rounded-lg text-center">
                            <div class="ui-text-caption ui-text-muted mb-1">القيمة</div>
                            <div class="text-lg font-bold ui-status-success">{{ number_format($productsValue, 0) }} ر.س</div>
                        </div>
                    </div>

                    {{-- الحالة --}}
                    <div class="mb-4">
                        @if($category->status === 'active')
                            <span class="px-3 py-1 rounded-full ui-text-caption ui-status-success-bg ui-status-success">
                                <i class="fa-solid fa-check ml-1"></i> مفعل
                            </span>
                        @else
                            <span class="px-3 py-1 rounded-full ui-text-caption ui-status-warning-bg ui-status-warning">
                                <i class="fa-solid fa-ban ml-1"></i> غير مفعل
                            </span>
                        @endif
                    </div>

                    {{-- الأزرار --}}
                    <div class="flex items-center justify-between mt-6">

                        {{-- زر تفعيل/تعطيل --}}
                        <form action="{{ route('user.stores.categories.toggle-status', [$store->id, $category->id]) }}"
                              method="POST">
                            @csrf
                            @method('PUT')

                            @if($category->status === 'active')
                                <button class="flex items-center ui-status-warning text-sm transition">
                                    <i class="fa-solid fa-ban ml-1"></i>
                                    تعطيل
                                </button>
                            @else
                                <button class="flex items-center ui-status-success text-sm transition">
                                    <i class="fa-solid fa-check ml-1"></i>
                                    تفعيل
                                </button>
                            @endif
                        </form>

                        {{-- زر حذف --}}
                        <form action="{{ route('user.stores.categories.destroy', [$store->id, $category->id]) }}"
                              method="POST"
                              data-ui-confirm="هل أنت متأكد من حذف هذا النشاط؟ سيتم حذف جميع المنتجات المرتبطة به."
                              data-ui-confirm-title="حذف النشاط">
                            @csrf
                            @method('DELETE')

                            <button class="ui-btn ui-btn-danger px-3 py-2 text-sm">
                                <i class="fa-solid fa-trash ml-1"></i>
                                حذف
                            </button>
                        </form>

                    </div>

                </div>

            @endforeach
        </div>
    @endif

    {{-- الأقسام العادية --}}
    @if($normalCategories->isNotEmpty())
        <h2 class="text-xl font-bold ui-title mb-4">الأقسام</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach($normalCategories as $category)
                @php
                    // حساب عدد المنتجات في هذا القسم
                    $productsCount = App\Models\Product::where('store_id', $store->id)
                        ->where('category_id', $category->id)
                        ->whereNull('deleted_at')
                        ->count();

                    $productsValue = App\Models\Product::where('store_id', $store->id)
                        ->where('category_id', $category->id)
                        ->whereNull('deleted_at')
                        ->sum('price');
                @endphp

                {{-- بطاقة القسم --}}
                <div class="ui-surface-muted-bg border ui-border p-6 rounded-xl transition duration-200 shadow-sm">

                    {{-- العنوان + زر تعديل --}}
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold ui-title truncate">
                            {{ $category->name }}
                        </h2>

                        <a href="{{ route('user.stores.categories.edit', [$store->id, $category->id]) }}"
                           class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg ui-status-info-bg border ui-border ui-hover-info transition ui-text-caption font-bold">
                            <i class="fa-solid fa-pen-to-square"></i>
                            <span>تعديل</span>
                        </a>
                    </div>

                    {{-- الوصف --}}
                    <p class="ui-text-muted text-sm mb-4 line-clamp-2 leading-relaxed">
                        {{ $category->description ?: 'لا يوجد وصف' }}
                    </p>

                    {{-- إحصائيات القسم --}}
                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="ui-surface-muted-bg p-2 rounded-lg text-center">
                            <div class="ui-text-caption ui-text-muted mb-1">المنتجات</div>
                            <div class="text-lg font-bold ui-status-info">{{ $productsCount }}</div>
                        </div>
                        <div class="ui-surface-muted-bg p-2 rounded-lg text-center">
                            <div class="ui-text-caption ui-text-muted mb-1">القيمة</div>
                            <div class="text-lg font-bold ui-status-success">{{ number_format($productsValue, 0) }} ر.س</div>
                        </div>
                    </div>

                    {{-- الحالة --}}
                    <div class="mb-4">
                        @if($category->status === 'active')
                            <span class="px-3 py-1 rounded-full ui-text-caption ui-status-success-bg ui-status-success">
                                <i class="fa-solid fa-check ml-1"></i> مفعل
                            </span>
                        @else
                            <span class="px-3 py-1 rounded-full ui-text-caption ui-status-warning-bg ui-status-warning">
                                <i class="fa-solid fa-ban ml-1"></i> غير مفعل
                            </span>
                        @endif
                    </div>

                    {{-- الأزرار --}}
                    <div class="flex items-center justify-between mt-6">

                        {{-- زر تفعيل/تعطيل --}}
                        <form action="{{ route('user.stores.categories.toggle-status', [$store->id, $category->id]) }}"
                              method="POST">
                            @csrf
                            @method('PUT')

                            @if($category->status === 'active')
                                <button class="flex items-center ui-status-warning text-sm transition">
                                    <i class="fa-solid fa-ban ml-1"></i>
                                    تعطيل
                                </button>
                            @else
                                <button class="flex items-center ui-status-success text-sm transition">
                                    <i class="fa-solid fa-check ml-1"></i>
                                    تفعيل
                                </button>
                            @endif
                        </form>

                        {{-- زر حذف --}}
                        <form action="{{ route('user.stores.categories.destroy', [$store->id, $category->id]) }}"
                              method="POST"
                              data-ui-confirm="هل أنت متأكد من حذف هذا القسم؟ سيتم حذف جميع المنتجات المرتبطة به."
                              data-ui-confirm-title="حذف القسم">
                            @csrf
                            @method('DELETE')

                            <button class="ui-btn ui-btn-danger px-3 py-2 text-sm">
                                <i class="fa-solid fa-trash ml-1"></i>
                                حذف
                            </button>
                        </form>

                    </div>

                </div>

            @endforeach
        </div>
    @endif

@endif

{{-- سلة المحذوفات --}}
<div class="mt-10 text-center">
    <a href="{{ route('user.stores.categories.trash', $store->id) }}"
       class="inline-flex items-center px-4 py-2 rounded-lg border ui-border ui-text-muted transition">

        <i class="fa-solid fa-trash-can ml-2"></i>

        سلة المحذوفات

        <span class="ml-2 ui-surface-muted-bg ui-text-muted px-2 py-0.5 rounded ui-text-caption">
            {{ $trashCount }}
        </span>
    </a>
</div>

</div>



@endsection
