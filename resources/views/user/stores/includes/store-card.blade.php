<div class="ui-card p-5 transition-all duration-200 ui-hover-surface">

    {{-- اسم المتجر والحالة --}}
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold ui-title">
            {{ $store->name }}
        </h3>
        @include('user.stores.includes.status-badge', ['status' => $store->status])
    </div>

    @if($store->archivedItem?->status === 'archived')
        <div class="mb-4 flex flex-wrap gap-2">
            <span class="ui-badge ui-badge-warning">محذوف من الحساب</span>
            <span class="ui-badge ui-badge-neutral">{{ $store->archivedItem->reference }}</span>
        </div>
        <p class="ui-text-muted mb-3 text-sm">آخر موعد لطلب الاستعادة: {{ $store->archivedItem->owner_restore_deadline?->format('Y-m-d H:i') }}</p>
        @if($store->archivedItem->admin_message)
            <div class="ui-alert ui-alert-warning mb-3">{{ $store->archivedItem->admin_message }}</div>
        @endif
        <form method="POST" action="{{ route('admin.support.archive.message', $store->archivedItem) }}" class="mb-4 space-y-2">
            @csrf @method('PATCH')
            <textarea name="admin_message" required minlength="5" maxlength="2000" rows="2" class="ui-input w-full" placeholder="رسالة الدعم أو سبب تعذر الاستعادة">{{ $store->archivedItem->admin_message }}</textarea>
            <button type="submit" class="ui-btn ui-btn-secondary">حفظ رسالة الدعم</button>
        </form>
    @endif

    {{-- الوصف --}}
    <p class="text-sm ui-text-muted line-clamp-2 mb-4 h-10">
        {{ $store->description ?? 'لا يوجد وصف لهذا المتجر' }}
    </p>

    {{-- معلومات المتجر (كلها بنفس الستايل) --}}
    <div class="space-y-2 mb-6">
       {{-- السجل التجاري --}}
<div class="flex items-center ui-text-caption ui-text-muted gap-2">
    <i class="fa-solid fa-file-invoice w-4 ui-brand-text"></i>
    <span class="ui-text-muted">السجل:</span>
    <span class="ui-text-soft">{{ $store->commercial_registration?? '—' }}</span>
</div>
        {{-- الرقم الضريبي --}}
        <div class="flex items-center ui-text-caption ui-text-muted gap-2">
            <i class="fa-solid fa-receipt w-4 ui-status-success"></i>
            <span class="ui-text-muted">الضريبة:</span>
            <span class="ui-text-soft">{{ $store->tax_number ?? '—' }}</span>
        </div>

        {{-- رقم الهاتف --}}
        <div class="flex items-center ui-text-caption ui-text-muted gap-2">
            <i class="fa-solid fa-phone w-4 ui-text-muted"></i>
            <span class="ui-text-soft">{{ $store->phone ?? '—' }}</span>
        </div>

        {{-- العنوان --}}
        <div class="flex items-center ui-text-caption ui-text-muted gap-2">
            <i class="fa-solid fa-location-dot w-4 ui-text-muted"></i>
            <span class="ui-text-soft line-clamp-1">{{ $store->address ?? '—' }}</span>
        </div>
    </div>

    {{-- الأزرار --}}
    <div class="flex items-center justify-between mt-auto pt-4 ui-border-top">
        @include('user.stores.includes.actions', ['store' => $store])
    </div>

</div>
