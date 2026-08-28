@extends('dashboard.app')
@section('title', 'مراجعة جلسة الجرد')
@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h1 class="ui-title text-2xl font-bold">{{ $session->referenceCode() }}</h1><p class="ui-text-soft">الحالة: {{ $session->statusLabel() }} — المحاسب: {{ $session->accountant?->name }}</p></div><div class="flex flex-wrap gap-2"><a class="ui-btn ui-btn-secondary" href="{{ route('user.stores.inventory-counts.index', $store) }}"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> رجوع</a><a class="ui-btn ui-btn-secondary" href="{{ route('user.stores.inventory-counts.pdf', [$store, $session]) }}" target="_blank">تصدير PDF</a></div></div>
    @if($session->status === 'draft')
        <x-ui.card>
            <div class="flex items-center gap-2"><h2 class="ui-title text-xl font-bold">مراجعة المسودة قبل الإرسال</h2><x-ui.help title="مراجعة المسودة" body="تأكد من المحاسب والمنتجات والملاحظة. بعد الإرسال تنتقل الجلسة إلى المحاسب ولا يعود تعديل منتجات المسودة متاحًا." /></div>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="ui-frame-row"><span class="ui-text-soft">المنتجات</span><strong class="ui-title">{{ $session->items->count() }}</strong></div>
                <div class="ui-frame-row"><span class="ui-text-soft">المحاسب</span><strong class="ui-title">{{ $session->accountant?->name ?: 'غير محدد' }}</strong></div>
                <div class="ui-frame-row"><span class="ui-text-soft">حالة المسودة</span><strong class="ui-status-success">جاهزة للمراجعة</strong></div>
            </div>
            @if($session->note)<div class="ui-alert ui-alert-info mt-4"><strong>ملاحظة الجلسة:</strong> {{ $session->note }}</div>@endif
            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <form method="POST" action="{{ route('user.stores.inventory-counts.send', [$store, $session]) }}" data-ui-confirm="سيتم تثبيت منتجات المسودة وإرسالها إلى المحاسب لبدء إدخال كميات الجرد. هل تريد المتابعة؟" data-ui-confirm-title="إرسال جلسة الجرد للمحاسب" data-ui-confirm-busy="جارٍ إرسال الجلسة...">@csrf<button class="ui-btn ui-btn-primary w-full sm:w-auto">إرسال للمحاسب</button></form>
                <a class="ui-btn ui-btn-secondary" href="{{ route('user.stores.inventory-counts.create', ['store' => $store, 'inventory_session' => $session->id]) }}">تعديل المنتجات أو المحاسب</a>
                <form method="POST" action="{{ route('user.stores.inventory-counts.destroy', [$store, $session]) }}" data-ui-confirm="سيتم حذف مسودة الجرد." data-ui-confirm-title="حذف المسودة">@csrf @method('DELETE')<button class="ui-btn ui-btn-danger w-full sm:w-auto">حذف المسودة</button></form>
            </div>
        </x-ui.card>
    @endif
    @if(in_array($session->status, \App\Models\InventoryCountSession::OPEN_STATUSES, true) && $session->status !== 'draft')
        <x-ui.card>
            <form method="POST" action="{{ route('user.stores.inventory-counts.cancel', [$store, $session]) }}" class="flex flex-col gap-3 sm:flex-row sm:items-end" data-ui-confirm="سيتم إيقاف العمل على الجلسة ولن يستطيع المحاسب إرسال نتائج جديدة." data-ui-confirm-title="إلغاء جلسة الجرد">
                @csrf
                <div class="flex-1"><label class="ui-label" for="cancellation-reason">سبب الإلغاء</label><input id="cancellation-reason" class="ui-input" name="reason" required minlength="5" maxlength="1000" placeholder="مثال: اختيار منتجات غير صحيحة"></div>
                <button class="ui-btn ui-btn-danger">إلغاء الجلسة</button>
            </form>
        </x-ui.card>
    @elseif($session->status === 'cancelled')
        <div class="ui-alert ui-alert-warning"><strong>الجلسة ملغاة.</strong> {{ $session->cancellation_reason }} <span class="ui-text-caption">{{ $session->cancelled_at?->format('Y-m-d H:i') }}</span></div>
        <form method="POST" action="{{ route('user.stores.inventory-counts.destroy', [$store, $session]) }}" data-ui-confirm="سيتم حذف الجلسة الملغاة من القائمة مع إبقاء السجل التقني." data-ui-confirm-title="حذف الجلسة الملغاة">@csrf @method('DELETE')<button class="ui-btn ui-btn-danger">حذف الجلسة</button></form>
    @endif
    @if($session->items->contains(fn ($item) => in_array($item->decision, ['approved', 'adjusted_approved'], true)))
        <div class="flex items-center gap-2">
            <x-ui.badge variant="success">تم تسجيل المنتجات المعتمدة</x-ui.badge>
            <x-ui.help title="ما بعد الاعتماد" body="تُثبت الكمية المعتمدة في المخزون. إذا وُجد فرق يسجل النظام الزيادة أو النقص تلقائيًا في اليوم المحاسبي المختار." />
        </div>
    @endif
    @if(in_array($session->status, ['pending_owner', 'partially_approved', 'returned_to_accountant']))
        <form id="inventory-bulk-approval" method="POST" action="{{ route('user.stores.inventory-counts.bulk-approve', [$store, $session]) }}" class="ui-card p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            @csrf
            <div class="flex items-center gap-2"><strong class="ui-title">اعتماد مجموعة منتجات</strong><x-ui.help title="الاعتماد الجماعي" body="حدد المنتجات التي تريد اعتماد كمية المحاسب لها، ثم اضغط اعتماد المنتجات المحددة. يمكنك ترك بقية المنتجات للمراجعة أو إعادتها للمحاسب." /></div>
            <div><label class="ui-label" for="bulk-approval-date">اليوم المحاسبي</label><input id="bulk-approval-date" class="ui-input" type="date" name="approval_business_date" value="{{ $currentBusinessDate }}" min="{{ $session->items->max(fn ($item) => $item->count_business_date?->format('Y-m-d')) }}" required></div>
            <button class="ui-btn ui-btn-success">اعتماد المنتجات المحددة</button>
        </form>
    @endif
    <div class="{{ $session->status === 'draft' ? 'grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3' : 'space-y-4' }}">
        @foreach($session->items as $item)
            @php($legacyAudit = $legacyAudits->get($item->product_id))
            @php($legacyAuditMovement = $legacyAuditMovements->get($item->product_id))
            <x-ui.card>
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex items-start gap-3">
                        @if(in_array($session->status, ['pending_owner', 'partially_approved', 'returned_to_accountant']) && $item->decision === 'pending' && $item->accountant_quantity !== null)
                            <input type="checkbox" name="items[]" value="{{ $item->id }}" form="inventory-bulk-approval" aria-label="تحديد {{ $item->product_name_snapshot }} للاعتماد">
                        @endif
                        <div><h2 class="ui-title text-lg font-bold">{{ $item->product_name_snapshot }}</h2><p class="ui-text-caption mt-2">الوحدة: {{ ['piece'=>'حبة','kit'=>'طقم','meter'=>'متر','roll'=>'رول','unit'=>'وحدة'][$item->unit_type] ?? $item->unit_type }}</p></div>
                    </div>
                    @if($item->accountant_quantity === null)
                        <div class="flex flex-col items-end gap-2">
                            <x-ui.badge :variant="$session->status === 'draft' ? 'success' : 'info'">{{ $session->status === 'draft' ? 'ضمن المسودة' : 'بانتظار المحاسب' }}</x-ui.badge>
                            @if($legacyAudit || $legacyAuditMovement)
                                @php($previousAudit = $legacyAudit ?: $legacyAuditMovement)
                                <span class="ui-text-caption">آخر جرد سابق: {{ optional($previousAudit->business_date)->format('Y-m-d') ?: $previousAudit->created_at?->format('Y-m-d') }} — الكمية: {{ $legacyAudit ? $legacyAudit->quantity_snapshot : $legacyAuditMovement->current_balance }} @if($previousAudit->user)— بواسطة {{ $previousAudit->user->name }}@endif</span>
                            @endif
                        </div>
                    @elseif($item->decision === 'recounted')
                        <x-ui.badge variant="info">حفظ المحاسب نتيجة الإعادة ولم يرسلها بعد</x-ui.badge>
                    @elseif(! in_array($session->status, ['pending_owner', 'partially_approved', 'returned_to_accountant', 'approved']))
                        <x-ui.badge variant="info">حفظ المحاسب الكمية ولم يرسل النتائج بعد</x-ui.badge>
                    @elseif($item->system_quantity_snapshot !== null)
                        <div class="ui-frame-row"><strong>كمية المحاسب: {{ $item->accountant_quantity }}</strong><span class="block ui-text-soft">كمية النظام وقت حفظ المحاسب: {{ $item->system_quantity_snapshot }}</span><span class="block ui-text-caption">وقت المقارنة: {{ $item->system_snapshot_at?->format('Y-m-d H:i') }} — اليوم: {{ $item->count_business_date?->format('Y-m-d') }} — {{ $item->isMatching() ? 'مطابق' : 'يوجد فرق' }}</span></div>
                    @else
                        <div class="ui-alert ui-alert-warning">تعذر العثور على لقطة النظام لهذه النتيجة القديمة. أعد المنتج للمحاسب ليحفظ الكمية من جديد.</div>
                    @endif
                </div>
                @if(in_array($session->status, ['pending_owner', 'partially_approved', 'returned_to_accountant']) && $item->decision === 'pending')
                    <div class="mt-4 grid gap-3 lg:grid-cols-3">
                        <form method="POST" action="{{ route('user.stores.inventory-counts.items.decision', [$store, $session, $item]) }}" class="space-y-2">@csrf<input type="hidden" name="action" value="approve"><input class="ui-input" type="date" name="approval_business_date" value="{{ $currentBusinessDate }}" min="{{ $item->count_business_date?->format('Y-m-d') }}" required aria-label="اليوم المحاسبي للاعتماد"><button class="ui-btn ui-btn-success w-full">اعتماد كمية المحاسب</button></form>
                        <form method="POST" action="{{ route('user.stores.inventory-counts.items.decision', [$store, $session, $item]) }}" class="space-y-2">@csrf<input type="hidden" name="action" value="adjust"><input class="ui-input" type="date" name="approval_business_date" value="{{ $currentBusinessDate }}" min="{{ $item->count_business_date?->format('Y-m-d') }}" required aria-label="اليوم المحاسبي للاعتماد"><input class="ui-input" name="owner_quantity" type="number" min="0" step="0.001" required placeholder="الكمية الصحيحة"><input class="ui-input" name="reason" required minlength="5" placeholder="سبب التعديل"><button class="ui-btn ui-btn-warning w-full">تعديل واعتماد</button></form>
                        <form method="POST" action="{{ route('user.stores.inventory-counts.items.decision', [$store, $session, $item]) }}" class="space-y-2">@csrf<input type="hidden" name="action" value="return"><input class="ui-input" name="reason" required minlength="5" placeholder="سبب إعادة الجرد"><button class="ui-btn ui-btn-secondary w-full">إعادة للمحاسب</button></form>
                    </div>
                @elseif($item->decision !== 'pending' || $item->accountant_quantity !== null)
                    <p class="ui-text-soft mt-3">{{ ['pending'=>'بانتظار مراجعة صاحب المتجر','returned'=>'أعيد للمحاسب لإعادة العد','recounted'=>'حفظ المحاسب نتيجة الإعادة ولم يرسلها بعد','approved'=>'اعتمد صاحب المتجر نتيجة المحاسب','adjusted_approved'=>'عدّل صاحب المتجر الكمية واعتمدها'][$item->decision] ?? $item->decision }} @if($item->owner_quantity !== null)— الكمية النهائية: {{ $item->owner_quantity }}@endif</p>
                @endif
                @if(in_array($item->decision, ['approved', 'adjusted_approved'], true) && $item->product && ! $item->product->trashed())
                    <div class="mt-4 flex items-center gap-2">
                        <a href="{{ route('user.stores.products.stock', ['store' => $store->id, 'product' => $item->product_id, 'return_to' => 'inventory-count', 'inventory_count' => $session->id]) }}" class="ui-btn ui-btn-secondary">إدارة مخزون المنتج</a>
                        <x-ui.help title="إدارة مخزون المنتج" body="يفتح سجل المنتج لمراجعة حركة تأكيد الجرد وأي زيادة أو نقص نتج عنها." />
                    </div>
                @endif
            </x-ui.card>
        @endforeach
    </div>
</div>
@endsection
