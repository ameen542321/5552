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
        <div class="min-w-0 text-center md:text-right">
            <div class="flex items-center justify-center gap-2 md:justify-start">
                <h1 class="text-2xl font-black ui-title">النقل المخزني</h1>
                <x-ui.help title="النقل المخزني" body="معالجة البضاعة الواردة ومتابعة الصادر من متجرك." />
            </div>
        </div>
        <a href="{{ route('accountant.transfers.create') }}" class="ui-btn ui-btn-primary inline-flex w-full items-center justify-center px-5 py-3 md:w-auto">+ إرسال منتج لمتجر آخر</a>
    </div>

    @php($statusLabels = ['pending' => 'معلق', 'completed' => 'مكتمل', 'rejected' => 'مرفوض', 'cancelled' => 'ملغي'])
    <div class="grid grid-cols-2 gap-2 ui-card p-3 sm:flex sm:flex-wrap">
        <a href="{{ route('accountant.transfers.index') }}" class="ui-btn inline-flex items-center justify-center px-4 py-2 text-sm {{ empty($status) ? 'ui-btn-primary' : 'ui-btn-secondary' }}">الكل</a>
        @foreach($statusLabels as $value => $label)
            <a href="{{ route('accountant.transfers.index', ['status' => $value]) }}" class="ui-btn inline-flex items-center justify-center px-4 py-2 text-sm {{ ($status ?? null) === $value ? 'ui-btn-primary' : 'ui-btn-secondary' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if($errors->any())
        <div class="rounded-xl border ui-border ui-status-danger-bg p-4 ui-status-danger">{{ $errors->first() }}</div>
    @endif

    <section class="space-y-4">
        <h2 class="text-xl font-black ui-title">بضاعة واردة بحاجة لمعالجة</h2>
        @forelse($incoming as $transfer)
            <div class="ui-card p-5 space-y-4">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
                    <div>
                        <p class="ui-title font-black">طلب #{{ $transfer->id }}</p>
                        <p class="ui-text-soft text-sm mt-1">من: {{ $transfer->senderStore?->name }} — الحالة: {{ ['pending' => 'معلق', 'completed' => 'مكتمل', 'rejected' => 'مرفوض', 'cancelled' => 'ملغي'][$transfer->status] ?? $transfer->status }}</p>
                        <p class="ui-text-muted ui-text-caption mt-1">تاريخ الاستلام: {{ $transfer->created_at?->format('Y-m-d') }}</p>
                        @if($transfer->notes)
                            <p class="ui-status-warning ui-text-caption mt-2 ui-status-warning-bg border ui-status-warning-border rounded-lg px-3 py-2">ملاحظة الطلب: {{ $transfer->notes }}</p>
                        @endif
                    </div>
                    @if($transfer->status === 'pending')
                        <form method="POST" action="{{ route('accountant.transfers.reject', $transfer->id) }}" data-ui-confirm="سيتم رفض النقل وإرجاع الكمية للمرسل." data-ui-confirm-title="تأكيد رفض النقل" class="grid w-full grid-cols-1 gap-2 sm:flex sm:w-auto sm:flex-wrap">
                            @csrf
                            <input type="date" name="business_date" value="{{ $currentBusinessDate }}" min="{{ now()->startOfMonth()->toDateString() }}" max="{{ now()->endOfMonth()->toDateString() }}" required class="ui-input px-3 py-2">
                            <input name="reason" required placeholder="سبب الرفض" class="ui-input px-3 py-2 text-sm">
                            <button class="ui-btn ui-btn-danger w-full px-4 py-2 text-sm sm:w-auto">رفض</button>
                        </form>
                    @endif
                </div>

                @if($transfer->status === 'pending')
                    <form method="POST" action="{{ route('accountant.transfers.approve', $transfer->id) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block ui-text-soft font-bold mb-2">اختر التاريخ</label>
                            <input type="date" name="business_date" value="{{ $currentBusinessDate }}" min="{{ now()->startOfMonth()->toDateString() }}" max="{{ now()->endOfMonth()->toDateString() }}" required class="ui-input px-4 py-3">
                        </div>
                @endif
                <div class="grid grid-cols-1 gap-4">
                    @foreach($transfer->items as $item)
                        <div class="ui-card-muted p-4 space-y-3">
                            <p class="ui-title font-bold">{{ $item->product_name_snapshot ?? $item->senderProduct?->name ?? 'منتج غير متاح' }}</p>
                            <p class="ui-text-soft ui-text-caption">الكمية: {{ $transferQuantity($item) }}</p>
                            @if($item->receiverProduct)
                                <p class="ui-status-success text-sm">تمت إضافته إلى: {{ $item->receiverProduct->name }}</p>
                            @endif

                            @if($transfer->status === 'pending')
                                    <x-store-transfers.product-picker
                                        :item="$item"
                                        label="اختر المنتج المقابل في متجرك"
                                        note="إذا لم تجد المنتج، أنشئه أولاً ثم عد للموافقة." />
                            @endif
                        </div>
                    @endforeach
                </div>
                @if($transfer->status === 'pending')
                        <button class="ui-btn ui-btn-success w-full px-4 py-3">موافقة واستلام جميع البنود</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="ui-card p-8 text-center ui-text-soft">لا توجد بضاعة واردة.</div>
        @endforelse
        {{ $incoming->appends(['status' => $status])->links() }}
    </section>

    <section class="space-y-4">
        <h2 class="text-xl font-black ui-title">بضاعة صادرة قيد الانتظار</h2>
        @forelse($outgoingPending as $transfer)
            <div class="ui-card p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <p class="ui-title font-bold">طلب #{{ $transfer->id }} إلى {{ $transfer->receiverStore?->name }}</p>
                    <p class="ui-text-soft text-sm">الحالة: {{ ['pending' => 'معلق', 'completed' => 'مكتمل', 'rejected' => 'مرفوض', 'cancelled' => 'ملغي'][$transfer->status] ?? $transfer->status }}</p>
                </div>
                @if($transfer->status === 'pending')
                    <form method="POST" action="{{ route('accountant.transfers.cancel', $transfer->id) }}" data-ui-confirm="سيتم إلغاء النقل وإرجاع الكمية لمتجرك." data-ui-confirm-title="تأكيد إلغاء النقل">
                    @csrf
                    <input type="date" name="business_date" value="{{ $currentBusinessDate }}" min="{{ now()->startOfMonth()->toDateString() }}" max="{{ now()->endOfMonth()->toDateString() }}" required class="ui-input px-3 py-2">
                        <button class="ui-btn ui-btn-danger px-4 py-2">إلغاء</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="ui-card p-8 text-center ui-text-soft">لا توجد بضاعة صادرة.</div>
        @endforelse
        {{ $outgoingPending->appends(request()->except('outgoing_pending_page'))->links() }}
    </section>

    <section class="space-y-4">
        <h2 class="text-xl font-black ui-title">البضاعة الصادرة المكتملة</h2>
        @forelse($outgoingCompleted as $transfer)
            <div class="ui-card p-5">
                <p class="ui-title font-bold">طلب #{{ $transfer->id }} إلى {{ $transfer->receiverStore?->name }}</p>
                <p class="ui-status-success ui-text-caption mt-1">مكتمل</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($transfer->items as $item)
                        <span class="ui-card-muted px-3 py-2 ui-text-caption">{{ $item->product_name_snapshot ?? $item->senderProduct?->name ?? 'منتج غير متاح' }} — {{ $transferQuantity($item) }}</span>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="ui-card p-8 text-center ui-text-soft">لا توجد طلبات صادرة مكتملة.</div>
        @endforelse
        {{ $outgoingCompleted->appends(request()->except('outgoing_completed_page'))->links() }}
    </section>
</div>
@endsection
