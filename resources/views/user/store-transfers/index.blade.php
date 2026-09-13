@extends('dashboard.app')
@section('title', 'النقل المخزني')
@section('content')
@php
    $formatQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';
    $transferQuantity = fn ($item) => \App\Support\ProductQuantityFormatter::transferQuantity(
        $item->senderProduct,
        (float) $item->requested_quantity,
        (string) $item->unit_type
    );
@endphp
<div class="max-w-7xl mx-auto space-y-6 px-4 py-6 sm:px-6" dir="rtl" data-store-transfer-system>
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="flex min-w-0 items-center justify-center gap-2 text-center md:justify-start md:text-right">
            <h1 class="text-2xl font-black ui-title">النقل المخزني بين المتاجر</h1>
            <x-ui.help title="إدارة النقل المخزني" body="إدارة طلبات النقل الصادرة والواردة بين متاجرك." />
        </div>
        <a href="{{ route('user.stores.transfers.create', $store->id) }}" class="ui-btn ui-btn-primary ui-title inline-flex w-full items-center justify-center rounded-xl px-5 py-3 font-bold md:w-auto">+ طلب نقل جديد</a>
    </div>

    @php($statusLabels = ['pending' => 'معلق', 'completed' => 'مكتمل', 'rejected' => 'مرفوض', 'cancelled' => 'ملغي'])
    <div class="grid grid-cols-2 gap-2 rounded-2xl border ui-border ui-surface-strong-bg p-3 sm:flex sm:flex-wrap">
        <a href="{{ route('user.stores.transfers.index', $store->id) }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-bold {{ empty($status) ? 'ui-btn ui-btn-primary ui-title' : 'ui-surface-muted-bg ui-text-soft ui-hover-info-bg' }}">الكل</a>
        @foreach($statusLabels as $value => $label)
            <a href="{{ route('user.stores.transfers.index', ['store' => $store->id, 'status' => $value]) }}" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-bold {{ ($status ?? null) === $value ? 'ui-btn ui-btn-primary ui-title' : 'ui-surface-muted-bg ui-text-soft ui-hover-info-bg' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if(session('success'))
        <div class="rounded-xl border ui-border ui-status-success-bg p-4 ui-status-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border ui-border ui-status-danger-bg p-4 ui-status-danger">{{ $errors->first() }}</div>
    @endif

    <div class="space-y-4">
        @forelse($transfers as $transfer)
            <div class="ui-card p-5 space-y-4">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="ui-title font-black">طلب #{{ $transfer->id }}</span>
                            <span class="px-3 py-1 rounded-full ui-text-caption font-bold {{ $transfer->status === 'pending' ? 'ui-status-warning-bg ui-status-warning border ui-border' : ($transfer->status === 'completed' ? 'ui-status-success-bg ui-status-success' : 'ui-status-danger-bg ui-status-danger ui-border') }}">
                                {{ ['pending' => 'معلق', 'completed' => 'مكتمل', 'rejected' => 'مرفوض', 'cancelled' => 'ملغي'][$transfer->status] ?? $transfer->status }}
                            </span>
                            <span class="ui-badge ui-badge-info">{{ (int) $transfer->sender_store_id === (int) $store->id ? 'صادر من هذا المتجر' : 'وارد إلى هذا المتجر' }}</span>
                        </div>
                        <p class="ui-text-soft text-sm mt-2">من: <span class="ui-title">{{ $transfer->senderStore?->name }}</span> ← إلى: <span class="ui-title">{{ $transfer->receiverStore?->name }}</span></p>
                        <p class="ui-text-muted ui-text-caption mt-1">منذ {{ $transfer->created_at?->locale('ar')?->diffForHumans(null, true) }}</p>
                        @if($transfer->notes)
                            <p class="ui-status-warning ui-text-caption mt-2 ui-status-warning-bg border ui-border rounded-lg px-3 py-2">ملاحظة الطلب: {{ $transfer->notes }}</p>
                        @endif
                    </div>
                    @if($transfer->status === 'pending')
                        <div class="grid w-full grid-cols-1 gap-2 sm:flex sm:w-auto sm:flex-wrap">
                        @if((int) $transfer->sender_store_id === (int) $store->id)
                            <form method="POST" action="{{ route('user.stores.transfers.cancel', [$store->id, $transfer->id]) }}" data-ui-confirm="سيتم إلغاء الطلب وإرجاع الكمية للمتجر المرسل." data-ui-confirm-title="تأكيد إلغاء طلب النقل">
                                @csrf
                                <input type="date" name="business_date" value="{{ $currentBusinessDate }}" min="{{ now()->startOfMonth()->toDateString() }}" max="{{ now()->endOfMonth()->toDateString() }}" required class="ui-input px-3 py-2">
                                <button class="ui-btn ui-btn-danger w-full px-4 py-2 text-sm">إلغاء</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('user.stores.transfers.reject', [$store->id, $transfer->id]) }}" data-ui-confirm="سيتم رفض الطلب وإرجاع الكمية للمرسل." data-ui-confirm-title="تأكيد رفض طلب النقل" class="grid grid-cols-1 gap-2 sm:flex">
                                @csrf
                                <input type="date" name="business_date" value="{{ $currentBusinessDate }}" min="{{ now()->startOfMonth()->toDateString() }}" max="{{ now()->endOfMonth()->toDateString() }}" required class="ui-input px-3 py-2">
                                <input name="reason" required placeholder="سبب الرفض" class="ui-input rounded-lg px-3 py-2 text-sm">
                                <button class="ui-btn ui-btn-danger w-full px-4 py-2 text-sm sm:w-auto">رفض</button>
                            </form>
                        @endif
                        </div>
                    @endif
                </div>

                @php($ownerCanApproveIncoming = $transfer->status === 'pending' && (int) $transfer->receiver_store_id === (int) $store->id)
                @if($ownerCanApproveIncoming)
                    <form method="POST" action="{{ route('user.stores.transfers.owner-approve', [$store->id, $transfer->id]) }}" class="space-y-4" data-transfer-approval-form>
                        @csrf
                        <div class="ui-card-muted p-4 space-y-2">
                            <label class="block ui-text-soft font-bold" for="transfer-business-date-{{ $transfer->id }}">تاريخ استلام جميع البنود</label>
                            <input id="transfer-business-date-{{ $transfer->id }}" type="date" name="business_date" value="{{ $currentBusinessDate }}" min="{{ now()->startOfMonth()->toDateString() }}" max="{{ now()->endOfMonth()->toDateString() }}" required class="ui-input px-4 py-3">
                            <p class="ui-text-soft ui-text-caption">اختر منتجًا مقابلًا لكل بند، ثم اعتمد الطلب كاملًا مرة واحدة.</p>
                        </div>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            @foreach($transfer->items as $item)
                                <div class="rounded-xl border ui-border ui-surface-muted-bg p-4 space-y-3">
                                    <div>
                                        <p class="ui-title font-bold">{{ $item->product_name_snapshot ?? $item->senderProduct?->name ?? 'منتج غير متاح' }}</p>
                                        <p class="ui-text-muted ui-text-caption">الكمية المحولة: {{ $transferQuantity($item) }}</p>
                                    </div>
                                    <x-store-transfers.product-picker
                                        :item="$item"
                                        label="المنتج الذي ستضاف إليه الكمية في المتجر المستلم"
                                        note="يجب اختيار منتج لهذا البند. إذا لم تجده، أنشئه في المتجر المستلم ثم عد إلى الطلب." />
                                </div>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-1 gap-2 sm:flex sm:justify-end">
                            <a href="{{ route('user.stores.products.create', $transfer->receiver_store_id) }}" target="_blank" class="ui-btn ui-btn-secondary inline-flex items-center justify-center px-4 py-2 text-center text-sm">إنشاء منتج في المتجر المستلم</a>
                            <button class="ui-btn ui-btn-success px-5 py-3">اعتماد واستلام جميع البنود ({{ $transfer->items->count() }})</button>
                        </div>
                    </form>
                @else
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        @foreach($transfer->items as $item)
                            <div class="rounded-xl border ui-border ui-surface-muted-bg p-4 space-y-3">
                                <p class="ui-title font-bold">{{ $item->product_name_snapshot ?? $item->senderProduct?->name ?? 'منتج غير متاح' }}</p>
                                <p class="ui-text-muted ui-text-caption">الكمية المحولة: {{ $transferQuantity($item) }}</p>
                                @if($item->receiverProduct)<p class="ui-status-success text-sm">المنتج المستلم: {{ $item->receiverProduct->name }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="ui-card p-10 text-center ui-text-muted">لا توجد طلبات نقل حتى الآن.</div>
        @endforelse
    </div>

    {{ $transfers->appends(['status' => $status])->links() }}
</div>
@endsection
