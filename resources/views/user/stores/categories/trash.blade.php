@extends('dashboard.app')

@section('title', 'سلة المحذوفات – متجر ' . $store->name)

@section('content')

<div class="max-w-7xl mx-auto px-4 py-6 sm:py-10" data-category-system>

    {{-- الهيدر --}}
    <div class="mb-6 grid grid-cols-1 gap-4 md:mb-10 md:grid-cols-[auto_1fr_5rem] md:items-center">

        {{-- زر الرجوع --}}
        <a href="{{ route('user.stores.categories.index', $store->id) }}"
           class="inline-flex w-fit items-center gap-2 px-4 py-2 rounded-lg ui-card ui-text-soft ui-hover-info-bg ui-title transition shadow-sm">
            <i class="fa-solid fa-arrow-right text-sm"></i>
            <span class="text-sm font-medium">رجوع</span>
        </a>

        {{-- عنوان الصفحة --}}
        <h1 class="text-center text-2xl font-bold ui-title">
            سلة المحذوفات
        </h1>

        <div class="hidden w-20 md:block" aria-hidden="true"></div>

    </div>

    {{-- في حال لا توجد عناصر محذوفة --}}
    @if($categories->isEmpty())
        <div class="ui-card p-10 rounded-xl text-center shadow-lg">
            <i class="fa-solid fa-trash-can ui-text-muted text-6xl mb-4"></i>
            <p class="ui-text-muted text-lg">لا توجد عناصر في سلة المحذوفات</p>
        </div>

    @else

        {{-- قائمة العناصر المحذوفة --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            @foreach($categories as $category)
                <div class="ui-card p-6 rounded-xl ui-surface-muted-bg transition duration-200 shadow-sm">

                    {{-- الاسم --}}
                    <h2 class="text-xl font-bold ui-title mb-3 truncate">
                        {{ $category->name }}
                    </h2>

                    @if($category->archivedItem?->status === 'archived')
                        <div class="mb-3 flex flex-wrap gap-2">
                            <span class="ui-badge ui-badge-warning">محذوف من الحساب</span>
                            <span class="ui-badge ui-badge-neutral">{{ $category->archivedItem->reference }}</span>
                        </div>
                        <p class="ui-text-muted mb-3 text-sm">آخر موعد لطلب الاستعادة: {{ $category->archivedItem->owner_restore_deadline?->format('Y-m-d H:i') }}</p>
                        @if($category->archivedItem->admin_message)
                            <div class="ui-alert ui-alert-warning mb-3">{{ $category->archivedItem->admin_message }}</div>
                        @endif
                        <form method="POST" action="{{ route('admin.support.archive.message', $category->archivedItem) }}" class="mb-4 space-y-2">
                            @csrf @method('PATCH')
                            <textarea name="admin_message" required minlength="5" maxlength="2000" rows="2" class="ui-input w-full" placeholder="رسالة الدعم أو سبب تعذر الاستعادة">{{ $category->archivedItem->admin_message }}</textarea>
                            <button class="ui-btn ui-btn-secondary" type="submit">حفظ رسالة الدعم</button>
                        </form>
                    @endif

                    {{-- الوصف --}}
                    <p class="ui-text-muted text-sm mb-4 line-clamp-2">
                        {{ $category->description ?: 'لا يوجد وصف' }}
                    </p>

                    {{-- تاريخ الحذف --}}
                    <p class="ui-text-muted ui-text-caption mb-6">
                        <i class="fa-solid fa-clock ml-1"></i>
                        تم الحذف في: {{ $category->deleted_at->format('Y-m-d H:i') }}
                    </p>

                    {{-- الأزرار --}}
                    <div class="flex items-center justify-between">

                        {{-- زر الاسترجاع --}}
                        <form action="{{ route('user.stores.categories.restore', [$store->id, $category->id]) }}"
      method="POST">
    @csrf
    @method('PUT')

                            <button class="ui-btn ui-btn-secondary px-3 py-2 text-sm">
                                <i class="fa-solid fa-rotate-left ml-1"></i>
                                استرجاع
                            </button>
                        </form>

                        {{-- زر الحذف النهائي --}}
                        <form action="{{ route('user.stores.categories.force-delete', [$store->id, $category->id]) }}"
                              method="POST"
                              data-ui-confirm="{{ $category->archivedItem?->status === 'archived' ? 'سيحذف الدعم القسم الفارغ فعليًا. لن يسمح النظام بذلك إن كان مرتبطًا بمنتجات.' : 'سيختفي القسم من حسابك ويمكن طلب استعادته خلال 30 يومًا.' }}"
                              data-ui-confirm-title="حذف القسم نهائيًا">
                            @csrf
                            @method('DELETE')

                            <button class="ui-btn ui-btn-danger px-3 py-2 text-sm">
                                <i class="fa-solid fa-trash ml-1"></i>
                                {{ $category->archivedItem?->status === 'archived' ? 'حذف فعلي بواسطة الدعم' : 'حذف نهائي' }}
                            </button>
                        </form>

                    </div>

                </div>
            @endforeach

        </div>

    @endif

</div>

@endsection
