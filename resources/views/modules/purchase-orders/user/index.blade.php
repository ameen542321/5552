@extends('dashboard.app')
@section('title', 'طلبيات توريد')
@section('content')
@php
    $isAccountantContext = ($purchaseOrderContext ?? null) === 'accountant';
    $isTechnicalSupport = !$isAccountantContext && isset($technicalSupportSession) && $technicalSupportSession?->target_role === 'owner';
    $indexRoute = $isAccountantContext ? route('accountant.purchase-orders.index') : route('user.stores.purchase-orders.index', $store->id);
    $createRoute = $isAccountantContext ? route('accountant.purchase-orders.create') : route('user.stores.purchase-orders.create', $store->id);
    $showRoute = fn ($order) => $isAccountantContext ? route('accountant.purchase-orders.show', $order->id) : route('user.stores.purchase-orders.show', [$store->id, $order->id]);
    $cancelRoute = fn ($order) => route('user.stores.purchase-orders.cancel', [$store->id, $order->id]);
    $destroyRoute = fn ($order) => route('user.stores.purchase-orders.destroy', [$store->id, $order->id]);
    $restoreRoute = fn ($order) => route('user.stores.purchase-orders.support-restore', [$store->id, $order->id]);
    $orderDisplayName = fn ($order) => $order->displayName();
    $labels = [
        'draft' => 'مسودة',
        'sent' => 'مرسلة',
        'received' => 'تم الاستلام',
        'approved' => 'معتمدة',
        'cancelled' => 'ملغية'
    ];
    $badgeClasses = [
        'draft' => 'ui-badge-neutral',
        'sent' => 'ui-badge-info',
        'received' => 'ui-badge-warning',
        'approved' => 'ui-badge-success',
        'cancelled' => 'ui-badge-danger'
    ];
    $workflowLabels = \App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow::labels($store->user?->name);
    $workflowBadgeClasses = \App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow::badgeClasses();
@endphp
<div class="max-w-7xl mx-auto p-4 md:p-6 space-y-6" dir="rtl">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 ui-card p-5">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-black ui-title">طلبيات التوريد</h1>
                <x-ui.help title="طلبيات التوريد" body="أنشئ الطلبية، ثم تابعها حتى الاستلام والاعتماد." />
            </div>
        </div>
        <a href="{{ $createRoute }}" class="ui-btn ui-btn-primary px-6 py-3 whitespace-nowrap">
            <span>+ إنشاء طلبية جديدة</span>
        </a>
    </div>

    @if($errors->any())
        <div class="rounded-xl border ui-border ui-status-danger-bg p-4 ui-status-danger flex items-center gap-3">
            <span class="inline-block w-2 h-2 rounded-full ui-status-danger-bg"></span>
            {{ $errors->first() }}
        </div>
    @endif

    <form id="filterForm" data-purchase-order-filter action="{{ $indexRoute }}" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto] gap-4 ui-card p-4">
        <input type="hidden" id="currentStatus" name="status" value="{{ $status }}">

        <div>
            <label for="date_from" class="block ui-text-caption ui-text-soft mb-1 font-bold cursor-pointer">من تاريخ</label>
            <input type="date" id="date_from" name="date_from" value="{{ $dateFromValue }}" class="ui-input w-full">
        </div>
        <div>
            <label for="date_to" class="block ui-text-caption ui-text-soft mb-1 font-bold cursor-pointer">إلى تاريخ</label>
            <input type="date" id="date_to" name="date_to" value="{{ $dateToValue }}" class="ui-input w-full">
        </div>
        <div>
            <label for="workflow_status" class="block ui-text-caption ui-text-soft mb-1 font-bold cursor-pointer">المرحلة الحالية</label>
            <select id="workflow_status" name="workflow_status" class="ui-input w-full">
                <option value="">كل المراحل</option>
                @foreach($workflowLabels as $value => $label)
                    <option value="{{ $value }}" @selected(($workflowStatus ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" id="submitFilterBtn" data-filter-submit class="ui-btn ui-btn-primary px-5 py-2.5 min-w-[120px]">
                <span id="submitFilterText" data-filter-submit-text>تطبيق الفلتر</span>
                <span id="submitFilterLoader" data-filter-submit-loader class="hidden animate-spin w-4 h-4 border-2 ui-border border-t-transparent rounded-full mr-2"></span>
            </button>
            <a href="{{ $indexRoute }}" class="ui-btn ui-btn-secondary">الشهر الحالي</a>
        </div>
    </form>

    <div class="flex flex-wrap gap-2 ui-card p-3">
        {{-- إصلاح مطبق: فلاتر حالة أوامر الشراء تضبط الحقل وترسل النموذج عبر ui-actions. --}}
        <button type="button" data-ui-set-value="currentStatus" data-ui-value="" data-ui-submit-form="filterForm" class="px-5 py-2 rounded-xl text-sm font-bold transition {{ empty($status) ? 'ui-status-info-bg ui-title shadow-lg ' : 'ui-surface-muted-bg ui-text-muted ui-surface-muted-bg ui-hover-info' }}">الكل</button>
        @foreach($labels as $value => $label)
            <button type="button" data-ui-set-value="currentStatus" data-ui-value="{{ $value }}" data-ui-submit-form="filterForm" class="px-5 py-2 rounded-xl text-sm font-bold transition {{ ($status ?? null) === $value ? 'ui-status-info-bg ui-title shadow-lg ' : 'ui-surface-muted-bg ui-text-muted ui-surface-muted-bg ui-hover-info' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4">
        @forelse($orders as $order)
            <div class="ui-card p-5 ui-border transition group">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    @if($order->trashed())<div class="flex-1">@else<a href="{{ $showRoute($order) }}" class="flex-1 block">@endif
                        <p class="ui-title font-black group-ui-status-info transition">{{ $orderDisplayName($order) }}</p>
                        <p class="ui-text-soft text-sm" dir="ltr">{{ $order->referenceCode() }}</p>
                        <p class="ui-text-muted text-sm mt-1">{{ $order->items_count }} منتج • {{ $order->created_at?->format('Y-m-d') }}</p>
                        @if($order->trashed())<span class="ui-badge ui-badge-danger mt-2">محذوفة</span>@endif
                    @if($order->trashed())</div>@else</a>@endif

                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="ui-badge {{ $badgeClasses[$order->status] ?? 'ui-badge-neutral' }}">{{ $labels[$order->status] ?? $order->status }}</span>
                        <span class="ui-badge {{ $workflowBadgeClasses[$order->workflow_status] ?? 'ui-badge-neutral' }}">{{ $workflowLabels[$order->workflow_status] ?? \App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow::UNKNOWN_LABEL }}</span>

                        @if($isTechnicalSupport)
                            @if($order->trashed())
                                <form method="POST" action="{{ $restoreRoute($order) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="support_note" value="استعادة الطلبية المحذوفة بعد مراجعة تذكرة الدعم">
                                    <button type="submit" class="ui-btn ui-btn-success px-4 py-2 ui-text-caption">استعادة الطلبية</button>
                                </form>
                            @endif
                            @if(!in_array($order->status, ['received', 'approved'], true))
                            <form method="POST" action="{{ route('user.stores.purchase-orders.support-purge', [$store->id, $order->id]) }}" data-ui-confirm="سيحذف الدعم الطلبية وملفاتها التابعة نهائيًا." data-ui-confirm-title="حذف نهائي بواسطة الدعم؟">
                                @csrf @method('DELETE')
                                <input type="hidden" name="confirmation" value="{{ $order->referenceCode() }}">
                                <input type="hidden" name="support_note" value="حذف إداري نهائي بعد مراجعة تذكرة الدعم">
                                <button type="submit" class="ui-btn ui-btn-danger px-4 py-2 ui-text-caption">حذف نهائي</button>
                            </form>
                            @endif
                        @elseif(!$isAccountantContext && in_array($order->status, ['draft','sent'], true))
                            {{-- إصلاح مطبق: تأكيدات أوامر الشراء ومنع النقر المزدوج تستخدم عقد الحوارات المركزي. --}}
                            <form method="POST" action="{{ $cancelRoute($order) }}"
                                  data-ui-confirm="هل أنت متأكد من إلغاء هذه الطلبية؟"
                                  data-ui-confirm-title="تأكيد إلغاء الطلبية"
                                  data-ui-confirm-busy="انتظر...">
                                @csrf
                                <button type="submit" class="js-action-btn ui-btn ui-btn-danger px-4 py-2 ui-text-caption">إلغاء الطلبية</button>
                            </form>
                        @elseif(!$isAccountantContext && in_array($order->status, ['cancelled', 'approved'], true))
                            <form method="POST" action="{{ $destroyRoute($order) }}"
                                  data-ui-confirm="هل تريد حذف هذه الطلبية من القائمة؟ لن تتغير المنتجات أو الكميات الموردة."
                                  data-ui-confirm-title="حذف الطلبية؟"
                                  data-ui-confirm-busy="انتظر...">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="confirmation" value="{{ $order->id }}">
                                <button type="submit" class="js-action-btn ui-btn ui-btn-danger px-4 py-2 ui-text-caption">حذف الطلبية</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="ui-card p-12 flex flex-col items-center justify-center text-center">
                <div class="w-16 h-16 ui-surface-muted-bg rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 ui-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <h3 class="text-lg font-bold ui-text-muted mb-1">لا توجد سجلات مطابقة</h3>
                <p class="ui-text-muted text-sm mb-5">لم يتم العثور على أي طلبيات توريد تناسب الفلتر أو الحالة المحددة.</p>
                @if(!empty($status) || request()->filled('date_from'))
                    <a href="{{ $indexRoute }}" class="px-5 py-2 rounded-xl ui-surface-muted-bg ui-text-muted font-bold transition">مسح الفلاتر</a>
                @else
                    <a href="{{ $createRoute }}" class="px-5 py-2.5 rounded-xl ui-status-info-bg ui-title font-bold transition">ابدأ بإنشاء طلبية</a>
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $orders->appends(['status'=>$status,'date_from'=>$dateFromValue,'date_to'=>$dateToValue])->links() }}
    </div>
</div>

@endsection
