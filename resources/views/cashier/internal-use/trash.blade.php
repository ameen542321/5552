@extends('dashboard.app')
@section('title', 'سلة محذوفات مشتريات المالك')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6 text-right" dir="rtl">
    <div class="mb-6 ui-card p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold ui-title">سلة محذوفات مشتريات المالك</h1>
                <div class="mt-1.5 flex items-center gap-2"><p class="ui-text-soft">{{ $store->name ?? 'المتجر' }}</p><x-ui.help title="سلة مشتريات المالك" body="تعرض العمليات المحذوفة وتتيح الاستعادة. الحذف الفعلي متاح للدعم التقني فقط." /></div>
            </div>
            <a href="{{ route('user.stores.internal-use.report.view', $storeId) }}" class="ui-btn ui-btn-secondary px-4 py-2.5 ui-text-caption">العودة للتقرير</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        @forelse($deletedPurchases as $purchase)
            <div class="ui-card p-4 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="ui-title font-bold text-sm">{{ $purchase->purchase_name ?: 'مشتريات المالك' }}</p>
                        <p class="ui-text-muted ui-text-caption mt-1">{{ $purchase->description ?: 'بدون ملاحظات' }}</p>
                    </div>
                    <div class="text-left shrink-0">
                        <p class="ui-status-warning font-black font-mono">{{ number_format((float) $purchase->cost, 2) }}</p>
                        <span class="ui-text-caption ui-text-muted">ر.س</span>
                    </div>
                </div>

                @if($purchase->archivedItem?->status === 'archived')
                    <div class="ui-alert ui-alert-warning">
                        <div class="font-bold">عملية محذوفة من الحساب — {{ $purchase->archivedItem->reference }}</div>
                        <div class="mt-1">آخر موعد لطلب الاستعادة: {{ $purchase->archivedItem->owner_restore_deadline?->format('Y-m-d H:i') }}</div>
                        @if($purchase->archivedItem->admin_message)
                            <div class="mt-2">{{ $purchase->archivedItem->admin_message }}</div>
                        @endif
                    </div>
                    @if(($technicalSupportSession ?? null)?->target_role === 'owner')
                    <form method="POST" action="{{ route('admin.support.archive.message', $purchase->archivedItem) }}" class="space-y-2">
                        @csrf @method('PATCH')
                        <textarea name="admin_message" required minlength="5" maxlength="2000" rows="2" class="ui-input w-full" placeholder="رسالة الدعم أو نتيجة المراجعة">{{ $purchase->archivedItem->admin_message }}</textarea>
                        <button type="submit" class="ui-btn ui-btn-secondary">حفظ رسالة الدعم</button>
                    </form>
                    @endif
                @endif

                <div class="flex items-center justify-between gap-3 pt-3">
                    <span class="ui-text-muted ui-text-caption font-mono">حُذفت: {{ optional($purchase->deleted_at)->format('Y-m-d h:i A') }}</span>
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('user.stores.internal-use.trash.restore', ['store' => $storeId, 'purchase' => $purchase->id]) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="ui-btn ui-btn-secondary px-3 py-2 ui-text-caption">استعادة</button>
                        </form>
                        {{-- إصلاح مطبق: تأكيد الحذف النهائي لسلة الاستهلاك يستخدم الحوار المركزي. --}}
                        <form method="POST" action="{{ route('user.stores.internal-use.trash.force-delete', ['store' => $storeId, 'purchase' => $purchase->id]) }}"
                              data-ui-confirm="{{ (($technicalSupportSession ?? null)?->target_role === 'owner' && $purchase->archivedItem?->status === 'archived') ? 'سيحذف الدعم التقني العملية نهائيًا ولا يمكن استعادتها.' : 'ستختفي العملية من حسابك وتبقى قابلة للمراجعة والاستعادة بواسطة الدعم خلال 30 يومًا.' }}"
                              data-ui-confirm-title="تأكيد الحذف النهائي">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="ui-btn ui-btn-danger px-3 py-2 ui-text-caption">{{ (($technicalSupportSession ?? null)?->target_role === 'owner' && $purchase->archivedItem?->status === 'archived') ? 'حذف نهائي بواسطة الدعم' : 'حذف نهائي' }}</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="md:col-span-2 ui-surface-muted-bg border border-dashed ui-border rounded-2xl py-12 text-center">
                <p class="ui-text-muted text-sm">سلة المحذوفات فارغة حالياً.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $deletedPurchases->links() }}
    </div>
</div>
@endsection
