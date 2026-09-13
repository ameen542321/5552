@extends('dashboard.app')
@section('title', 'إدخال الجرد')
@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <h1 class="ui-title text-2xl font-bold">{{ $session->referenceCode() }}</h1>
            <x-ui.help title="إدخال نتيجة الجرد" body="عدّ كل منتج فعليًا ثم أدخل الكمية والوحدة. يسجل النظام اليوم ووقت التعديل تلقائيًا، ولا يعرض كمية النظام أثناء العد." />
        </div>
        <a class="ui-btn ui-btn-secondary" href="{{ route('accountant.inventory-counts.index') }}"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> رجوع</a>
    </div>
    @if(session('success'))<div class="ui-alert ui-alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="ui-alert ui-alert-danger" role="alert"><strong>لم يتم إرسال النتائج:</strong> {{ $errors->first() }}</div>@endif
    <div class="flex items-center gap-2">
        <x-ui.badge variant="info">احفظ كل منتج بعد إدخال كميته</x-ui.badge>
        <x-ui.help title="تثبيت الكمية" body="الكتابة داخل الحقل وحدها لا تثبت الكمية. اضغط حفظ كمية المنتج حتى تدخل ضمن النتائج التي سترسل إلى المالك." />
    </div>

    @foreach($session->items as $item)
        @php
            $unitOptions = $item->product?->product_type === 'fractional'
                ? ['roll' => 'رول', 'meter' => 'متر']
                : ($item->product?->is_splittable
                    ? ['kit' => 'طقم', 'piece' => 'حبة']
                    : ['piece' => 'حبة']);
        @endphp
        <form method="POST" action="{{ route('accountant.inventory-counts.items.update', [$session, $item]) }}" class="ui-card p-4 space-y-3">
            @csrf
            @method('PUT')
            <div>
                <h2 class="ui-title text-lg font-bold">{{ $item->product_name_snapshot }}</h2>
                @if(in_array($item->decision, ['returned', 'recounted']))<p class="ui-status-warning mt-2">أعاده المالك: {{ $item->owner_adjustment_reason }}</p>@endif
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <label>
                    <span class="ui-label">الكمية الفعلية</span>
                    <input class="ui-input" type="number" min="0" step="0.001" name="accountant_quantity" value="{{ $item->accountant_quantity }}" placeholder="مثال: 12" required>
                </label>
                <div>
                    <div class="ui-label inline-flex items-center gap-2">الوحدة <x-ui.help title="اختيار وحدة العد" body="تظهر الوحدات المناسبة للمنتج فقط. إذا كان المنتج طقمًا واخترت الحبة، يحول النظام رصيد الأطقم إلى حبات عند المقارنة مع المحافظة على الكمية التي أدخلتها." /></div>
                    <select class="ui-input" name="unit_type" aria-label="وحدة جرد {{ $item->product_name_snapshot }}">
                        @foreach($unitOptions as $value => $label)
                            <option value="{{ $value }}" @selected($item->unit_type === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <label class="block">
                <span class="ui-label">ملاحظة (اختياري)</span>
                <input class="ui-input" name="accountant_note" value="{{ $item->accountant_note }}" placeholder="مثال: الكمية موزعة على رفّين">
            </label>
            @if($item->accountant_updated_at)<p class="ui-text-caption">آخر حفظ: {{ $item->accountant_updated_at->format('Y-m-d H:i') }} — اليوم: {{ $item->count_business_date?->format('Y-m-d') }}</p>@endif
            <div class="flex items-center gap-2">
                <button class="ui-btn ui-btn-secondary">حفظ كمية المنتج</button>
                <x-ui.help title="حفظ كمية المنتج" body="يحفظ هذا الزر كمية هذا المنتج مؤقتًا مع الوحدة والملاحظة، ويمكنك تعديلها لاحقًا قبل إرسال نتائج الجلسة كاملة إلى المالك." />
            </div>
        </form>
    @endforeach

    @php($allItemsSaved = $session->items->isNotEmpty() && $session->items->every(fn ($item) => $item->accountant_quantity !== null && $item->decision !== 'returned'))
    @unless($allItemsSaved)<div class="ui-alert ui-alert-danger"><strong>الإرسال غير جاهز:</strong> توجد كمية غير محفوظة.</div>@endunless
    <form method="POST" action="{{ route('accountant.inventory-counts.submit', $session) }}"
          data-ui-confirm="سيتم إرسال جميع نتائج الجرد المحفوظة إلى صاحب المتجر للمراجعة، وسيتوقف تعديلها حتى يعيد لك منتجًا. هل تريد المتابعة؟"
          data-ui-confirm-title="إرسال نتائج الجرد للمالك"
          data-ui-confirm-busy="جارٍ إرسال النتائج...">
        @csrf
        <div class="flex items-center gap-2">
            <button class="ui-btn ui-btn-primary flex-1" @disabled(! $allItemsSaved)>{{ $allItemsSaved ? 'إرسال النتائج للمالك' : 'احفظ كميات المنتجات أولًا' }}</button>
            <x-ui.help title="إرسال النتائج" body="يرسل الكميات المحفوظة ولقطات المقارنة إلى المالك، ثم يتوقف التعديل إلا إذا أعاد المالك منتجًا لإعادة الجرد." />
        </div>
    </form>
</div>
@endsection
