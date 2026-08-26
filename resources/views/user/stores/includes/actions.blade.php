<div class="flex items-center gap-2">

    {{-- إذا كان المتجر محذوف (Soft Deleted) --}}
    @if($store->trashed())

        {{-- زر الاسترجاع --}}
        <form action="{{ route('user.stores.restore', $store->id) }}" method="POST">
            @csrf
            <button
                class="ui-btn ui-btn-secondary px-3 py-2 ui-text-caption">
                استرجاع
            </button>
        </form>

        {{-- زر الحذف النهائي --}}
        <form action="{{ route('user.stores.forceDelete', $store->id) }}" method="POST"
              data-ui-confirm="{{ $store->archivedItem?->status === 'archived' ? 'سيحذف الدعم المتجر الفارغ فعليًا. سيمنع النظام الحذف إذا وجد أي سجل مرتبط.' : 'سيختفي المتجر من حسابك ويمكن طلب استعادته خلال 30 يومًا.' }}"
              data-ui-confirm-title="تأكيد حذف المتجر">
            @csrf
            @method('DELETE')
            <button
                class="ui-btn ui-btn-danger px-3 py-2 ui-text-caption">
                {{ $store->archivedItem?->status === 'archived' ? 'حذف فعلي بواسطة الدعم' : 'حذف نهائي' }}
            </button>
        </form>

    @else

        {{-- زر التفعيل والإيقاف (الجديد) --}}
        <form action="{{ route('user.stores.toggle-status', $store->id) }}" method="POST">
            @csrf
            @method('PATCH')
            <button type="submit"
                class="ui-btn {{ $store->status === 'active' ? 'ui-btn-warning' : 'ui-btn-primary' }} px-3 py-2 ui-text-caption">
                {{ $store->status === 'active' ? 'إيقاف' : 'تفعيل' }}
            </button>
        </form>

        {{-- زر عرض المتجر --}}
        <a href="{{ route('user.stores.show', $store->id) }}"
           class="ui-btn ui-btn-secondary px-3 py-2 ui-text-caption">
            عرض
        </a>

        {{-- زر تعديل المتجر --}}
        <a href="{{ route('user.stores.edit', $store->id) }}"
           class="ui-btn ui-btn-secondary px-3 py-2 ui-text-caption">
            تعديل
        </a>

        {{-- زر الحذف (Soft Delete) --}}
        <form action="{{ route('user.stores.destroy', $store->id) }}" method="POST">
            @csrf
            @method('DELETE')
            <button
                class="ui-btn ui-btn-danger px-3 py-2 ui-text-caption">
                حذف
            </button>
        </form>

    @endif

</div>
