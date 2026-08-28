@extends('dashboard.app')
@section('title', 'مراجعة جلسة الجرد')
@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h1 class="ui-title text-2xl font-bold">{{ $session->referenceCode() }}</h1><p class="ui-text-soft">الحالة: {{ $session->statusLabel() }} — المحاسب: {{ $session->accountant?->name }}</p></div><div class="flex flex-wrap gap-2"><a class="ui-btn ui-btn-secondary" href="{{ route('user.stores.inventory-counts.index', $store) }}"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> رجوع</a><a class="ui-btn ui-btn-secondary" href="{{ route('user.stores.inventory-counts.pdf', [$store, $session]) }}" target="_blank">تصدير PDF</a></div></div>
    @if($session->status === 'draft')
        <div class="ui-card p-4 flex flex-col gap-3 sm:flex-row">
            <a class="ui-btn ui-btn-secondary" href="{{ route('user.stores.inventory-counts.create', ['store' => $store, 'inventory_session' => $session->id]) }}">إضافة أو حذف منتجات</a>
            <form method="POST" action="{{ route('user.stores.inventory-counts.send', [$store, $session]) }}">@csrf<button class="ui-btn ui-btn-primary">إرسال للمحاسب</button></form>
            <form method="POST" action="{{ route('user.stores.inventory-counts.destroy', [$store, $session]) }}" data-ui-confirm="سيتم حذف مسودة الجرد.">@csrf @method('DELETE')<button class="ui-btn ui-btn-danger">حذف المسودة</button></form>
        </div>
    @endif
    <div class="space-y-4">
        @foreach($session->items as $item)
            <x-ui.card>
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div><h2 class="ui-title text-lg font-bold">{{ $item->product_name_snapshot }}</h2><p class="ui-text-caption mt-2">الوحدة: {{ ['piece'=>'حبة','kit'=>'طقم','meter'=>'متر','roll'=>'رول','unit'=>'وحدة'][$item->unit_type] ?? $item->unit_type }}</p></div>
                    @if($item->accountant_quantity !== null)<div class="ui-frame-row"><strong>كمية المحاسب: {{ $item->accountant_quantity }}</strong><span class="block ui-text-soft">كمية النظام للمقارنة: {{ $item->system_quantity_snapshot }}</span><span class="block ui-text-caption">يوم الجرد: {{ $item->count_business_date?->format('Y-m-d') }} — {{ $item->isMatching() ? 'مطابق' : 'يوجد فرق' }}</span></div>@else <x-ui.badge variant="info">بانتظار المحاسب</x-ui.badge>@endif
                </div>
                @if(in_array($session->status, ['pending_owner', 'partially_approved', 'returned_to_accountant']) && $item->decision === 'pending')
                    <div class="mt-4 grid gap-3 lg:grid-cols-3">
                        <form method="POST" action="{{ route('user.stores.inventory-counts.items.decision', [$store, $session, $item]) }}">@csrf<input type="hidden" name="action" value="approve"><button class="ui-btn ui-btn-success w-full">اعتماد كمية المحاسب</button></form>
                        <form method="POST" action="{{ route('user.stores.inventory-counts.items.decision', [$store, $session, $item]) }}" class="space-y-2">@csrf<input type="hidden" name="action" value="adjust"><input class="ui-input" name="owner_quantity" type="number" min="0" step="0.001" required placeholder="الكمية الصحيحة"><input class="ui-input" name="reason" required minlength="5" placeholder="سبب التعديل"><button class="ui-btn ui-btn-warning w-full">تعديل واعتماد</button></form>
                        <form method="POST" action="{{ route('user.stores.inventory-counts.items.decision', [$store, $session, $item]) }}" class="space-y-2">@csrf<input type="hidden" name="action" value="return"><input class="ui-input" name="reason" required minlength="5" placeholder="سبب إعادة الجرد"><button class="ui-btn ui-btn-secondary w-full">إعادة للمحاسب</button></form>
                    </div>
                @elseif($item->decision !== 'pending' || $item->accountant_quantity !== null)
                    <p class="ui-text-soft mt-3">{{ ['pending'=>'بانتظار مراجعة صاحب المتجر','returned'=>'أعيد للمحاسب لإعادة العد','approved'=>'اعتمد صاحب المتجر نتيجة المحاسب','adjusted_approved'=>'عدّل صاحب المتجر الكمية واعتمدها'][$item->decision] ?? $item->decision }} @if($item->owner_quantity !== null)— الكمية النهائية: {{ $item->owner_quantity }}@endif</p>
                @endif
            </x-ui.card>
        @endforeach
    </div>
</div>
@endsection
