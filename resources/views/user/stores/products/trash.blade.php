@extends('dashboard.app')

@section('title', 'سلة محذوفات المنتجات – متجر ' . $store->name)

@section('content')

<div class="max-w-6xl mx-auto py-10">

    {{-- الهيدر --}}
    <div class="flex items-center justify-between mb-8">

        {{-- زر الرجوع إلى الكتالوج --}}
        <a href="{{ route('user.stores.products.index', $store->id) }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg ui-card ui-text-soft ui-hover-info-bg ui-title transition shadow-sm">
            <i class="fa-solid fa-arrow-right text-sm"></i>
            <span class="text-sm font-medium">رجوع إلى المنتجات</span>
        </a>

        {{-- عنوان الصفحة --}}
        <h1 class="text-2xl font-bold ui-title">
            سلة محذوفات المنتجات
        </h1>

        {{-- إفراغ السلة متاح فقط عند وجود منتجات محذوفة. --}}
        <div class="min-w-32 flex justify-end">
            @if($products->isNotEmpty())
                <form action="{{ route('user.stores.products.trash.empty', $store->id) }}"
                      method="POST"
                      data-ui-confirm="ستُحذف المنتجات نهائيًا من حسابك، ويمكن طلب استعادتها من الدعم التقني خلال 30 يومًا."
                      data-ui-confirm-title="إفراغ سلة المنتجات">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="ui-btn ui-btn-danger px-4 py-2">
                        <i class="fa-solid fa-trash-can"></i>
                        <span class="text-sm font-medium">إفراغ السلة ({{ $products->count() }})</span>
                    </button>
                </form>
            @endif
        </div>

    </div>

    <div class="ui-card overflow-hidden">

        <table class="w-full ui-title">
            <thead class="ui-surface-muted-bg">
                <tr class="ui-text-soft">
                    <th class="py-3 px-4 text-right">المنتج</th>
                    <th class="py-3 px-4 text-right">القسم</th>
                    <th class="py-3 px-4 text-right">تاريخ الحذف</th>
                    <th class="py-3 px-4 text-right">العمليات</th>
                </tr>
            </thead>

            <tbody>
                @forelse($products as $product)
                    <tr class="border-b ui-border ui-surface-muted-bg transition">

                        <td class="py-3 px-4">
                            <div>{{ $product->name }}</div>
                            @if($product->archivedItem?->status === 'archived')
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <span class="ui-badge ui-badge-warning">محذوف من الحساب</span>
                                    <span class="ui-badge ui-badge-neutral">{{ $product->archivedItem->reference }}</span>
                                </div>
                                <div class="ui-text-muted mt-2 text-sm">
                                    آخر موعد لطلب المالك: {{ $product->archivedItem->owner_restore_deadline?->format('Y-m-d H:i') ?: 'غير محدد' }}
                                </div>
                            @endif
                        </td>

                        <td class="py-3 px-4">
                            {{ $product->category->name ?? '—' }}
                        </td>

                        <td class="py-3 px-4">
                            {{ $product->deleted_at?->format('Y-m-d H:i') }}
                        </td>

                        <td class="py-3 px-4">
                            <div class="flex flex-wrap items-center gap-3">

                            {{-- استرجاع --}}
                            <form action="{{ route('user.stores.products.restore', [$store->id, $product->id]) }}"
                                  method="POST">
                                @csrf
                                @method('PUT')
                                <button class="ui-btn ui-btn-secondary px-3 py-2 text-sm">
                                    <i class="fa-solid fa-rotate-left"></i>
                                    <span>استرجاع</span>
                                </button>
                            </form>

                            {{-- حذف نهائي من حساب المالك، أو حذف فعلي عند دخول الدعم إلى عنصر موجود في سجل المحذوفات. --}}
                            <form action="{{ route('user.stores.products.force-delete', [$store->id, $product->id]) }}"
                                  method="POST"
                                  data-ui-confirm="{{ $product->archivedItem?->status === 'archived' ? 'سيحذف الدعم التقني السجل فعلياً بعد فحص ارتباطاته.' : 'سيختفي المنتج من حسابك، ويمكن طلب استعادته من الدعم التقني خلال 30 يوماً.' }}"
                                  data-ui-confirm-title="حذف المنتج نهائيًا">
                                @csrf
                                @method('DELETE')
                                <button class="ui-btn ui-btn-danger px-3 py-2 text-sm">
                                    <i class="fa-solid fa-trash"></i>
                                    <span>{{ $product->archivedItem?->status === 'archived' ? 'حذف فعلي بواسطة الدعم' : 'حذف نهائي' }}</span>
                                </button>
                            </form>

                            </div>

                            @if($product->archivedItem?->status === 'archived')
                                <form method="POST" action="{{ route('user.stores.products.archive-message', [$store->id, $product->id]) }}" class="mt-3 space-y-2">
                                    @csrf
                                    @method('PATCH')
                                    <label class="ui-text-soft block text-sm">رسالة الدعم أو سبب تعذر الاستعادة</label>
                                    <textarea name="admin_message" required minlength="5" maxlength="2000" rows="2" class="ui-input w-full"
                                              placeholder="اكتب رسالة واضحة تظهر في سجل المحذوفات...">{{ $product->archivedItem->admin_message }}</textarea>
                                    <button type="submit" class="ui-btn ui-btn-secondary">حفظ رسالة الدعم</button>
                                </form>
                            @endif

                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-6 ui-text-muted">
                            لا توجد منتجات محذوفة
                        </td>
                    </tr>
                @endforelse
            </tbody>

        </table>

    </div>

</div>

@endsection
