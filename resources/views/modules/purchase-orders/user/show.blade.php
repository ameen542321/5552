@extends('dashboard.app')
@section('title', $order->displayName().' — '.\App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow::label($order->workflow_status, $store->user?->name))

@section('content')
@php
    $isAccountantContext = ($purchaseOrderContext ?? null) === 'accountant';
    $storeOwnerName = $store->user?->name ?: 'صاحب المتجر';
    $isTechnicalSupport = isset($technicalSupportSession) && $technicalSupportSession?->target_role === 'owner';
    $orderDisplayName = $order->displayName();
    $unitLabels = ['unit' => 'وحدة', 'piece' => 'حبة', 'kit' => 'طقم', 'meter' => 'متر', 'meters' => 'متر', 'roll' => 'رول'];
    $labels = [
        'draft' => 'مسودة',
        'sent' => 'مرسلة',
        'received' => 'تم الاستلام',
        'approved' => 'معتمدة',
        'cancelled' => 'ملغية'
    ];
    $statusBadgeClasses = [
        'draft' => 'ui-badge-neutral',
        'sent' => 'ui-badge-info',
        'received' => 'ui-badge-warning',
        'approved' => 'ui-badge-success',
        'cancelled' => 'ui-badge-danger',
    ];
    $workflowLabels = \App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow::labels($storeOwnerName);
    $workflowBadgeClasses = \App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow::badgeClasses();
    $supportWorkflowLabels = \App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow::supportLabels();
    $inventoryReviewLabels = [
        'returned_to_accountant' => 'معادة للجرد من ' . $storeOwnerName,
        'returned_for_edit' => 'معادة للتعديل من ' . $storeOwnerName,
        'count_draft' => 'جرد قيد التعبئة',
        'pending_owner_after_count' => 'بانتظار اعتماد ' . $storeOwnerName,
        'approved' => 'تم اعتماد مراجعة الجرد',
    ];
    $latestAccountantEditEvent = $order->events
        ->where('event', 'items_updated')
        ->where('actor_type', 'accountant')
        ->sortByDesc('created_at')
        ->first();
    $accountantItemChanges = collect(data_get($latestAccountantEditEvent?->data, 'changes', []))
        ->reject(fn ($change) => data_get($change, 'type') === 'removed')
        ->keyBy(fn ($change) => mb_strtolower(trim((string) data_get($change, 'name'))));
    $accountantRemovedItems = collect(data_get($latestAccountantEditEvent?->data, 'changes', []))
        ->where('type', 'removed')
        ->pluck('name')
        ->filter()
        ->values();
    $inventoryReviewLocked = in_array($order->inventory_review_status, ['returned_to_accountant', 'count_draft', 'pending_owner_after_count'], true);
    $isOwnerReceiptReview = ! $isAccountantContext
        && $order->status === 'received'
        && $order->workflow_status === 'pending_owner_receipt_review';
    $isInventoryApproval = ! $isAccountantContext
        && $order->status === 'received'
        && $order->workflow_status === 'pending_inventory_approval';
    $isFocusedOwnerWorkflow = $isOwnerReceiptReview || $isInventoryApproval;
    $latestAccountantReceiptEvent = $order->events
        ->where('event', 'receipt_confirmed')
        ->where('actor_type', 'accountant')
        ->sortByDesc('created_at')
        ->first();
    $accountantReceiptChanges = collect(data_get($latestAccountantReceiptEvent?->data, 'item_changes', []));
    // ملخص تشغيلي مشتق للعرض فقط؛ لا يغير حالة الطلبية أو قيم المخزون.
    $receiptReviewItems = $order->items->reject(fn ($item) => (bool) $item->excluded_after_count);
    $receiptReviewChangedCount = $receiptReviewItems->filter(fn ($item) => $accountantReceiptChanges->has((string) $item->id))->count();
    $receiptReviewVarianceCount = $receiptReviewItems->filter(fn ($item) => abs((float) $item->price_variance) > 0.01)->count();
    $receiptReviewUnresolvedCount = $receiptReviewItems->filter(fn ($item) => ! $item->product_id && ! $item->matched_product_id && ! $item->add_to_owner_purchases)->count();
    $receiptReviewOwnerPurchaseCount = $receiptReviewItems->where('add_to_owner_purchases', true)->count();
    $latestCountApprovalEvent = $order->events
        ->where('event', 'count_approved')
        ->sortByDesc('created_at')
        ->first();
@endphp

@if($isAccountantContext)
<div class="max-w-7xl mx-auto p-4 md:p-6 space-y-6" dir="rtl">
    <div class="ui-card p-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="ui-title text-2xl font-black">{{ $orderDisplayName }}</h1>
            <p class="ui-text-soft mt-1">المرحلة الحالية: {{ $workflowLabels[$order->workflow_status] ?? \App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow::UNKNOWN_LABEL }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="ui-badge {{ $workflowBadgeClasses[$order->workflow_status] ?? 'ui-badge-neutral' }}">
                    {{ $workflowLabels[$order->workflow_status] ?? \App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow::UNKNOWN_LABEL }}
                    @if(in_array($order->workflow_status, ['returned_for_edit', 'returned_for_count'], true))
                        من {{ $storeOwnerName }}
                    @endif
                </span>
            </div>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('accountant.purchase-orders.index') }}" class="ui-btn ui-btn-secondary">رجوع</a>
            @if($order->inventory_review_status === 'returned_for_edit')
                <a href="{{ route('accountant.purchase-orders.edit', $order->id) }}" class="ui-btn ui-btn-secondary">تعديل الطلبية</a>
            @endif
            @if(in_array($order->inventory_review_status, ['returned_to_accountant', 'count_draft'], true))
                <a href="{{ route('accountant.purchase-orders.inventory-count', $order->id) }}" class="ui-btn ui-btn-info">إدخال الجرد</a>
            @endif
        </div>
    </div>

    {{-- نعرض كل أسباب فشل الحفظ للمحاسب حتى لا يبدو زر تأكيد الاستلام وكأنه لم يستجب. --}}
    @if($errors->any())
        <div class="ui-alert ui-alert-danger-plain" role="alert" aria-live="assertive">
            <strong class="ui-alert-title">تعذر تنفيذ الإجراء</strong>
            <div class="ui-alert-body space-y-1">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        </div>
    @endif

    @if($order->inventory_review_note && in_array($order->inventory_review_status, ['returned_to_accountant', 'count_draft', 'returned_for_edit'], true))
        <div class="ui-alert ui-alert-info">
            <strong class="ui-title">ملاحظة {{ $storeOwnerName }}:</strong>
            <span>{{ $order->inventory_review_note }}</span>
        </div>
    @endif

    @if($order->status !== 'sent')
    <div class="ui-card p-5 space-y-4">
        <div>
            <div class="flex items-center gap-2"><h2 class="ui-title text-lg font-black">بنود الطلبية</h2><x-ui.help title="بنود الطلبية" body="تظهر هنا بيانات البنود المطلوبة." /></div>
        </div>
        <div class="overflow-x-auto">
            <table class="ui-table min-w-full">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المنتج</th>
                        <th>الكمية المطلوبة</th>
                        <th>الوحدة</th>
                        <th>ملاحظة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="ui-title font-bold">{{ $item->productName() }}</td>
                            <td>{{ number_format((float) $item->quantity_requested, 2) }}</td>
                            <td>{{ $unitLabels[$item->unit_type ?: 'unit'] ?? 'وحدة' }}</td>
                            <td>{{ $item->receipt_notes ?: 'لا توجد ملاحظات' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($order->status === 'sent')
        <form id="receipt-confirmation" method="POST" action="{{ route('accountant.purchase-orders.receive', $order->id) }}" class="ui-card p-5 space-y-5">
            @csrf
            <div class="flex items-center gap-2">
                <h2 class="ui-title text-lg font-black">تأكيد استلام الطلبية</h2>
                <x-ui.help title="كميات الاستلام" body="أدخل الكمية التي وصلت فعلًا، وأدخل صفرًا إذا لم يصل المنتج." />
                <a href="{{ route('accountant.purchase-orders.receipt.pdf', $order->id) }}" class="ui-btn ui-btn-info">تحميل مستند استلام الطلبية</a>
            </div>
            @foreach($order->items->where('excluded_after_count', false) as $item)
                @php
                    $receiptProduct = $item->product ?: $item->matchedProduct;
                    $baseReceiptCost = (float) ($receiptProduct?->cost_price ?? $item->cost_price_at_order ?? 0);
                    $receiptUnits = ['unit' => ['label' => 'وحدة', 'cost' => $baseReceiptCost]];
                    $receiptUnitHint = null;
                    if ($receiptProduct && (($receiptProduct->product_type ?? null) === 'fractional' || (float) $receiptProduct->roll_length > 0)) {
                        $rollLength = max(0.0, (float) $receiptProduct->roll_length);
                        $receiptUnits = [
                            'roll' => ['label' => 'رول', 'cost' => $baseReceiptCost],
                            'meter' => ['label' => 'متر', 'cost' => $rollLength > 0 ? $baseReceiptCost / $rollLength : 0],
                        ];
                        $receiptUnitHint = $rollLength > 0 ? 'الرول الواحد يساوي '.number_format($rollLength, 2).' متر.' : 'تحقق هل المستلم رول كامل أم أمتار.';
                    } elseif ($receiptProduct?->is_splittable) {
                        $itemsPerUnit = max(1, (int) $receiptProduct->items_per_unit);
                        $receiptUnits = [
                            'kit' => ['label' => 'طقم', 'cost' => $baseReceiptCost],
                            'piece' => ['label' => 'حبة', 'cost' => $baseReceiptCost / $itemsPerUnit],
                        ];
                        $receiptUnitHint = 'الطقم الواحد يساوي '.$itemsPerUnit.' حبة.';
                    }
                    $defaultReceiptUnit = old(
                        'items.'.$item->id.'.unit_type',
                        in_array($item->unit_type, array_keys($receiptUnits), true)
                            ? $item->unit_type
                            : ($receiptProduct?->quick_sale_default_unit ?: array_key_first($receiptUnits))
                    );
                    $defaultReceiptQuantity = old('items.'.$item->id.'.quantity_received', $item->quantity_received ?? $item->quantity_requested);
                    $defaultReceiptPrice = old(
                        'items.'.$item->id.'.cost_price_at_receipt',
                        $item->cost_price_at_receipt ?? round((float) ($receiptUnits[$defaultReceiptUnit]['cost'] ?? $baseReceiptCost) * (float) $defaultReceiptQuantity, 2)
                    );
                    $autoFillReceiptPrice = ! session()->hasOldInput('items.'.$item->id.'.cost_price_at_receipt') && $item->cost_price_at_receipt === null;
                @endphp
                <div class="js-receive-item ui-card p-4 grid gap-4 md:grid-cols-3">
                    <input type="hidden" name="items[{{ $item->id }}][id]" value="{{ $item->id }}">
                    <div><span class="ui-text-muted">المنتج</span><strong class="block ui-title">{{ $item->productName() }}</strong></div>
                    <div><span class="ui-text-muted">المطلوب</span><strong class="block ui-title">{{ number_format((float) $item->quantity_requested, 2) }} {{ $unitLabels[$item->unit_type ?: 'unit'] ?? 'وحدة' }}</strong></div>
                    <div><span class="ui-text-muted">التكلفة المسجلة</span><strong class="block ui-title">{{ number_format((float) $item->cost_price_at_order, 2) }} ر.س</strong></div>
                    <label class="ui-label">الكمية المستلمة
                        <input class="ui-input" type="number" min="0" step="0.01" name="items[{{ $item->id }}][quantity_received]" value="{{ $defaultReceiptQuantity }}">
                        <span class="js-receipt-expected ui-text-soft text-sm"></span>
                        @error('items.'.$item->id.'.quantity_received')<span class="ui-status-danger">{{ $message }}</span>@enderror
                    </label>
                    <label class="ui-label">تكلفة الاستلام
                        <input class="js-receipt-price ui-input" type="number" min="0" step="0.01" name="items[{{ $item->id }}][cost_price_at_receipt]" value="{{ $defaultReceiptPrice }}" data-auto-fill="{{ $autoFillReceiptPrice ? '1' : '0' }}" data-order-price="{{ (float) $item->cost_price_at_order }}" data-requested-qty="{{ (float) $item->quantity_requested }}" data-variance-target="accountant-variance-{{ $item->id }}">
                        <span id="accountant-variance-{{ $item->id }}" class="hidden ui-text-soft text-sm"></span>
                    </label>
                    @if(count($receiptUnits) > 1)
                        <label class="ui-label">وحدة الاستلام
                            @if($receiptUnitHint)<span class="ui-text-soft text-sm">{{ $receiptUnitHint }} تأكد من نوع المستلم قبل الحفظ.</span>@endif
                            <select class="js-receipt-unit ui-input" name="items[{{ $item->id }}][unit_type]">
                                @foreach($receiptUnits as $receiptUnit => $receiptUnitData)
                                    <option value="{{ $receiptUnit }}" data-unit-cost="{{ (float) $receiptUnitData['cost'] }}" @selected($defaultReceiptUnit === $receiptUnit)>{{ $receiptUnitData['label'] }} — سعر الواحدة المتوقع {{ number_format((float) $receiptUnitData['cost'], 2) }} ر.س</option>
                                @endforeach
                            </select>
                        </label>
                    @else
                        <input type="hidden" class="js-receipt-unit" name="items[{{ $item->id }}][unit_type]" value="{{ array_key_first($receiptUnits) }}" data-unit-cost="{{ (float) collect($receiptUnits)->first()['cost'] }}">
                    @endif
                </div>
            @endforeach
            <button class="ui-btn ui-btn-success">تأكيد استلام الطلبية</button>
        </form>
    @endif

    @if($order->events->isNotEmpty() && !in_array($order->workflow_status, ['returned_for_edit', 'returned_for_count', 'pending_receipt_confirmation'], true))
        <div class="ui-card p-5 space-y-3">
            <h2 class="ui-title text-lg font-black">سجل الطلبية</h2>
            @foreach($order->events->sortByDesc('created_at') as $event)
                <div class="ui-card-muted p-3">
                    @if($event->event === 'item_added')
                        <span>أضاف {{ $event->actor_type === 'user' ? $storeOwnerName : ($order->accountant?->name ?: 'المحاسب') }} المنتج {{ data_get($event->data, 'product_name') }}</span>
                    @elseif($event->event === 'item_deleted')
                        <span>حذف {{ $event->actor_type === 'user' ? $storeOwnerName : ($order->accountant?->name ?: 'المحاسب') }} المنتج {{ data_get($event->data, 'product_name') }}</span>
                    @else
                        <span>{{ $event->note ?: ($workflowLabels[$event->to_status] ?? 'تحديث الطلبية') }}</span>
                    @endif
                    <time class="block ui-text-muted">{{ $event->created_at?->format('Y-m-d H:i') }}</time>
                </div>
            @endforeach
        </div>
    @endif
</div>
@else

<div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-8" dir="rtl">
    <a href="{{ route('user.stores.purchase-orders.index', $store->id) }}" class="ui-btn ui-btn-secondary">رجوع إلى الطلبيات</a>
    <details class="ui-card ui-disclosure">
        <summary class="ui-disclosure-summary p-5 sm:p-6">
            <span class="min-w-0">
                <span class="ui-title text-2xl sm:text-3xl font-black break-words">{{ $isOwnerReceiptReview ? 'مراجعة تأكيد الاستلام' : ($isInventoryApproval ? 'الاعتماد المخزني' : $orderDisplayName) }}</span>
                <span class="ui-text-soft block mt-1 break-words">{{ $orderDisplayName }}</span>
            </span>
            <span class="flex items-center gap-2">
                <span class="ui-badge {{ $workflowBadgeClasses[$order->workflow_status] ?? 'ui-badge-neutral' }}">{{ $workflowLabels[$order->workflow_status] ?? \App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow::UNKNOWN_LABEL }}</span>
                <i class="fa-solid fa-chevron-down ui-text-soft ui-disclosure-chevron" aria-hidden="true"></i>
            </span>
        </summary>
        <div class="border-t ui-border p-5 sm:p-6 space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <p class="ui-text-soft">رمز الطلبية<br><strong class="ui-title" dir="ltr">{{ $order->referenceCode() }}</strong></p>
                <p class="ui-text-soft">المورد<br><strong class="ui-title">{{ $order->supplier_name ?: 'لم يحدد بعد' }}</strong></p>
                <p class="ui-text-soft">المتجر<br><strong class="ui-title">{{ $store->name }}</strong></p>
                <p class="ui-text-soft">المحاسب<br><strong class="ui-title">{{ $receiptAccountantName ?: 'غير محدد' }}</strong></p>
            </div>
            @if($order->received_at)
                <p class="ui-text-soft flex flex-wrap items-center gap-2">
                    <i class="fa-solid fa-circle-check ui-status-success" aria-hidden="true"></i>
                    <span>{{ $order->receipt_actor_type === 'accountant' ? 'أكد المحاسب الاستلام' : 'أكد المالك الاستلام' }}</span>
                    <time class="ui-title font-bold" datetime="{{ $order->received_at->toIso8601String() }}">{{ $order->received_at->format('Y-m-d H:i') }}</time>
                </p>
            @endif
            @if($order->status === 'approved' && $order->approved_business_date)
                <p class="ui-text-soft">تاريخ اعتماد المخزون: <strong class="ui-title">{{ $order->approved_business_date->format('Y-m-d') }}</strong></p>
                <p class="ui-text-soft">وقت الاعتماد الفعلي للمراجعة: <strong class="ui-title" dir="ltr">{{ $order->approved_at?->format('Y-m-d H:i') ?: '—' }}</strong></p>
            @endif
        </div>
    </details>

    @if($isTechnicalSupport)
        @include('modules.purchase-orders.user.partials.support-tools')
    @endif

    {{-- يبقى التنبيه خارج شرط مراحل المالك المركزة ليظهر في مراجعة الاستلام والاعتماد المخزني أيضًا. --}}
    @if($errors->any())
        <div class="ui-alert ui-alert-danger-plain" role="alert" aria-live="assertive">
            <strong class="ui-alert-title">تعذر تنفيذ الإجراء</strong>
            <div class="ui-alert-body space-y-1">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        </div>
    @endif

    @if(!$isFocusedOwnerWorkflow)

    @if(in_array($order->status, ['received', 'approved'], true))
        <div class="grid gap-4 sm:grid-cols-2">
            <article class="ui-card p-5 space-y-3">
                <h2 class="ui-title font-black">{{ $order->status === 'approved' ? 'سجل الاعتماد المخزني' : 'سجل تأكيد الاستلام' }}</h2>
                <p class="ui-text-soft">نسخة تعرض الكميات والتكاليف المحفوظة.</p>
                <a href="{{ route('user.stores.purchase-orders.pdf', [$store->id, $order->id, 'type' => $order->status === 'approved' ? 'inventory' : 'receipt']) }}" class="ui-btn ui-btn-info w-full">تحميل السجل</a>
            </article>
            <article class="ui-card p-5 space-y-3">
                <h2 class="ui-title font-black">سجل بدون أسعار</h2>
                <p class="ui-text-soft">نسخة للمشاركة لا تعرض التكاليف.</p>
                <a href="{{ route('user.stores.purchase-orders.pdf', [$store->id, $order->id, 'type' => $order->status === 'approved' ? 'inventory' : 'receipt', 'hide_prices' => 1]) }}" class="ui-btn ui-btn-secondary w-full">تحميل بدون أسعار</a>
            </article>
        </div>
    @endif

    @if($order->status === 'draft')
        @if($order->inventory_review_status === 'approved')
            <section class="ui-card p-5 space-y-5">
                <div class="flex items-center gap-2">
                    <h2 class="ui-title text-xl font-black">اعتماد وإرسال للمورد</h2>
                    <x-ui.help title="اعتماد الطلبية" body="اعتمدت نتيجة الجرد. أدخل اسم المورد ثم أرسل الطلبية، أو راجع البنود وعدّلها قبل الإرسال." />
                </div>
                <form id="sendPurchaseOrderForm" method="POST" action="{{ route('user.stores.purchase-orders.mark-sent', [$store->id, $order->id]) }}" class="space-y-4">
                    @csrf
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="ui-label">اسم المورد <span class="ui-status-danger">*</span><input required name="supplier_name" value="{{ old('supplier_name', $order->supplier_name) }}" class="ui-input" placeholder="اكتب اسم المورد"></label>
                        <label class="ui-label">ملاحظة الطلبية<input maxlength="40" name="notes" value="{{ old('notes', $order->notes) }}" class="ui-input" placeholder="ملاحظة اختيارية حتى 40 حرفًا"></label>
                    </div>
                    <button class="ui-btn ui-btn-success">اعتماد وإرسال للمورد</button>
                </form>
            </section>
        @endif
        @if($order->inventory_review_status === 'pending_owner_after_count')
            <section class="ui-card p-5 space-y-5" data-owner-count-review>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <h2 class="ui-title text-xl font-black">معادة من المحاسب بعد الجرد</h2>
                        <x-ui.help title="مراجعة نتيجة الجرد" body="قارن الكمية وقت إرسال الجرد بكمية المحاسب. أعد المنتجات التي تحتاج مراجعة أخرى، أو اعتمد النتيجة من أسفل البنود." />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="ui-btn ui-btn-secondary" data-owner-count-select-all>تحديد الكل</button>
                        <button type="button" class="ui-btn ui-btn-secondary" data-owner-count-clear>إلغاء التحديد</button>
                    </div>
                </div>
                <form id="rejectCountItemsForm" method="POST" action="{{ route('user.stores.purchase-orders.inventory-count.reject-items', [$store->id, $order->id]) }}" class="ui-card-muted p-4 flex flex-col gap-3 sm:flex-row">
                    @csrf
                    <input maxlength="40" required name="reason" class="ui-input" placeholder="سبب إعادة المنتجات المحددة للجرد">
                    <button class="ui-btn ui-btn-warning">إعادة المحدد للجرد</button>
                </form>
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach($order->items->where('add_to_owner_purchases', false)->filter(fn ($item) => $item->product_id || $item->matched_product_id) as $item)
                        @php
                            $countProduct = $item->product ?: $item->matchedProduct;
                            $hasSubmittedCount = $item->inventory_snapshot_at && $item->inventory_count_quantity !== null;
                            $currentQuantity = $countProduct ? (float) $countProduct->getRawOriginal('quantity') : 0;
                            $snapshotQuantity = (float) ($item->system_quantity_snapshot ?? 0);
                            $accountantQuantity = (float) ($item->inventory_count_quantity ?? 0);
                            $inventoryCountUnit = $item->inventory_count_unit ?: $item->unit_type ?: 'unit';
                            $normalizedAccountantQuantity = $countProduct ? $countProduct->normalizeQuantityByUnit($accountantQuantity, $inventoryCountUnit) : $accountantQuantity;
                            $countDifference = $normalizedAccountantQuantity - $snapshotQuantity;
                            $differenceLabel = abs($countDifference) < 0.0001 ? 'مطابق' : ($countDifference > 0 ? 'زيادة' : 'نقص');
                            $differenceVariant = abs($countDifference) < 0.0001 ? 'success' : 'warning';
                            $storedUnitLabel = $countProduct ? \App\Support\ProductQuantityFormatter::inventoryDefaultUnit($countProduct) : 'وحدة';
                            $displayCurrentQuantity = $countProduct ? \App\Support\ProductQuantityFormatter::inventoryQuantity($countProduct, $currentQuantity) : $currentQuantity;
                            $displaySnapshotQuantity = $countProduct ? \App\Support\ProductQuantityFormatter::inventoryQuantity($countProduct, $snapshotQuantity) : $snapshotQuantity;
                            $displayDifference = $countProduct ? \App\Support\ProductQuantityFormatter::inventoryQuantity($countProduct, $countDifference) : $countDifference;
                            $productTypeLabel = (($countProduct?->product_type ?? null) === 'fractional' || (float) ($countProduct?->roll_length ?? 0) > 0)
                                ? 'منتج رول / متر'
                                : ($countProduct?->is_splittable ? 'منتج طقم / حبة' : 'منتج عادي');
                        @endphp
                        <article class="ui-card-muted p-4 space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <label class="flex items-start gap-3">
                                    <input type="checkbox" form="rejectCountItemsForm" name="item_ids[]" value="{{ $item->id }}" aria-label="إعادة {{ $item->productName() }} للجرد" data-owner-count-checkbox>
                                    <span class="ui-title font-black">{{ $item->productName() }}</span>
                                </label>
                                <span class="ui-badge ui-badge-neutral">{{ $productTypeLabel }}</span>
                            </div>
                            @if($hasSubmittedCount)
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div><span class="ui-text-soft block">الكمية الحالية</span><strong class="ui-title">{{ number_format($displayCurrentQuantity, 2) }} {{ $storedUnitLabel }}</strong></div>
                                    <div><span class="ui-text-soft block">الكمية وقت إرسال الجرد</span><strong class="ui-title">{{ number_format($displaySnapshotQuantity, 2) }} {{ $storedUnitLabel }}</strong></div>
                                    <div><span class="ui-text-soft block">كمية الجرد</span><strong class="ui-title">{{ number_format($accountantQuantity, 2) }} {{ $unitLabels[$inventoryCountUnit] ?? 'وحدة' }}</strong></div>
                                </div>
                                <div class="ui-inline-frame flex flex-wrap items-center justify-between gap-3">
                                    <span class="flex items-center gap-2"><strong class="ui-title">نتيجة مراجعة الجرد</strong><x-ui.help title="نتيجة مراجعة الجرد" body="تُوحّد كمية المحاسب أولًا حسب نوع المنتج ووحدة الجرد، ثم تقارن بالكمية وقت إرسال الجرد. لا تدخل الكمية الحالية في حساب الفرق لأنها قد تتغير بعد الإرسال." /></span>
                                    <x-ui.badge :variant="$differenceVariant">{{ $differenceLabel }}</x-ui.badge>
                                    <span class="ui-text-soft">الفرق: <strong class="ui-title">{{ number_format($displayDifference, 2) }} {{ $storedUnitLabel }}</strong></span>
                                </div>
                            @else
                                <div class="ui-alert ui-alert-info"><span class="ui-alert-body">لم يكن هذا المنتج ضمن الجرد السابق، ويمكن إرفاقه الآن مع إعادة الجرد.</span></div>
                            @endif
                        </article>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('user.stores.purchase-orders.inventory-count.approve', [$store->id, $order->id]) }}" class="ui-card-muted p-4 flex flex-col gap-3 sm:flex-row">
                    @csrf
                    <input maxlength="40" name="inventory_review_note" class="ui-input" placeholder="ملاحظة اختيارية حتى 40 حرفًا">
                    <button class="ui-btn ui-btn-success">اعتماد نتيجة الجرد</button>
                </form>
            </section>
        @endif
        @if($order->workflow_status === 'returned_after_edit' && $order->inventory_review_note)
            <div class="ui-alert ui-alert-info">
                <span class="flex items-center gap-2"><strong class="ui-alert-title">ملاحظة المراجعة المرسلة للمحاسب</strong><x-ui.help title="ملاحظة المراجعة" body="هذه هي الملاحظة التي أرسلتها للمحاسب عند إعادة الطلبية للتعديل." /></span>
                <span class="ui-alert-body">{{ $order->inventory_review_note }}</span>
            </div>
        @endif
        @if(!$inventoryReviewLocked)
        <section class="ui-card p-5 space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="ui-title text-xl font-black">تعديل الطلبية</h2>
                    <x-ui.help title="تعديل الطلبية" body="يفتح نموذج الطلبية لتعديل المنتجات أو الكميات أو الملاحظات قبل اعتمادها." />
                </div>
                <a href="{{ route('user.stores.purchase-orders.edit', [$store->id, $order->id]) }}" class="ui-btn ui-btn-secondary">تعديل البنود</a>
            </div>
            <div class="overflow-x-auto">
                @if($order->workflow_status === 'returned_after_edit' && $accountantRemovedItems->isNotEmpty())
                    <div class="ui-alert ui-alert-warning mb-4">
                        <strong class="ui-alert-title">بنود حذفها المحاسب أثناء التعديل</strong>
                        <span class="ui-alert-body">{{ $accountantRemovedItems->implode('، ') }}</span>
                    </div>
                @endif
                <table class="ui-table min-w-[680px]">
                    <thead><tr><th>المنتج</th><th>نوع البند</th><th>الكمية</th><th>الوحدة</th><th>الملاحظة</th>
                        @if($order->inventory_review_status !== 'approved')
                            <th>آخر تعديل</th>
                        @endif
                    </tr></thead>
                    <tbody>
                        @foreach($order->items as $draftItem)
                            @php
                                $draftChange = $accountantItemChanges->get(mb_strtolower(trim($draftItem->productName())));
                                $changedParts = collect([
                                    data_get($draftChange, 'before.quantity') !== data_get($draftChange, 'after.quantity') ? 'الكمية' : null,
                                    data_get($draftChange, 'before.unit') !== data_get($draftChange, 'after.unit') ? 'الوحدة' : null,
                                    data_get($draftChange, 'before.note') !== data_get($draftChange, 'after.note') ? 'الملاحظة' : null,
                                ])->filter()->implode('، ');
                            @endphp
                            <tr>
                                <td class="ui-title font-bold">{{ $draftItem->productName() }}</td>
                                <td>{{ $draftItem->add_to_owner_purchases ? 'مشتريات مالك / استهلاك' : ($draftItem->product_id || $draftItem->matched_product_id ? 'منتج مخزني' : 'منتج مخصص') }}</td>
                                <td>{{ number_format((float) $draftItem->quantity_requested, 2) }}</td>
                                <td>{{ $unitLabels[$draftItem->unit_type ?: 'unit'] ?? 'وحدة' }}</td>
                                <td>{{ $draftItem->receipt_notes ?: '—' }}</td>
                                @if($order->inventory_review_status !== 'approved')
                                <td>
                                    @if(data_get($draftChange, 'type') === 'added')
                                        <span class="ui-badge ui-badge-success">أضافه المحاسب</span>
                                    @elseif($draftChange)
                                        <span class="ui-badge ui-badge-info">عدّل المحاسب: {{ $changedParts }}</span>
                                    @else
                                        <span class="ui-badge ui-badge-neutral">دون تغيير</span>
                                    @endif
                                </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @endif
    @endif

    <div class="flex flex-col gap-3">
        @if($order->status === 'draft' && !$inventoryReviewLocked && $order->inventory_review_status !== 'approved')
            <section class="ui-card p-5 space-y-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <h2 class="ui-title text-xl font-black">اعتماد الطلبية</h2>
                        <x-ui.help title="اعتماد الطلبية" body="راجع البنود أولًا، ثم أدخل اسم المورد واضغط اعتماد وإرسال للمورد عندما تصبح الطلبية جاهزة." />
                    </div>
                </div>
                @if(!$order->inventory_review_status)
                    <form id="sendPurchaseOrderForm" method="POST" action="{{ route('user.stores.purchase-orders.mark-sent', [$store->id, $order->id]) }}" class="space-y-4">
                        @csrf
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="ui-label">اسم المورد <span class="ui-status-danger">*</span><input required name="supplier_name" value="{{ old('supplier_name', $order->supplier_name) }}" class="ui-input" placeholder="اكتب اسم المورد"></label>
                            <label class="ui-label">ملاحظة الطلبية<input maxlength="40" name="notes" value="{{ old('notes', $order->notes) }}" class="ui-input" placeholder="ملاحظة اختيارية حتى 40 حرفًا"></label>
                        </div>
                        <div class="flex justify-end"><button class="ui-btn ui-btn-success">اعتماد وإرسال للمورد</button></div>
                    </form>
                @else
                    <div class="ui-alert ui-alert-warning flex items-center gap-2"><span class="ui-alert-body">الإرسال متوقف حتى اعتماد الجرد.</span><x-ui.help variant="warning" title="لماذا توقف الإرسال؟" body="أكمل الجرد المطلوب أولًا، وبعد اعتماده يمكنك إرسال الطلبية للمورد." /></div>
                @endif
            </section>
        @elseif($order->status === 'received' && $order->workflow_status === 'pending_inventory_approval')
            <section class="ui-card p-5 space-y-2">
                <div class="flex items-center gap-2">
                    <h2 class="ui-title text-xl font-black">الاعتماد المخزني</h2>
                    <x-ui.help title="الاعتماد المخزني" body="ظهرت هذه المرحلة بعد حفظ بيانات الاستلام. راجع ربط المنتجات والتكلفة؛ عند الاعتماد تضاف الكميات المستلمة لمنتجات البيع، وتسجل مشتريات المالك دون إضافتها للمخزون، ثم تغلق الطلبية." />
                </div>
            </section>
        @endif
    </div>


    @if($order->status === 'draft' && !$inventoryReviewLocked && $order->inventory_review_status !== 'approved')
        <section class="ui-card p-5 space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <h2 class="ui-title text-xl font-black">إعادة للمراجعة والتعديل</h2>
                        <x-ui.help title="إعادة للمراجعة والتعديل" body="تعيد الطلبية إلى المحاسب ليصحح المنتجات أو الكميات أو الملاحظات. لن يُطلب منه تنفيذ جرد." />
                    </div>
                </div>
                @if($order->inventory_review_status)
                    <span class="ui-badge ui-badge-neutral">{{ $inventoryReviewLabels[$order->inventory_review_status] ?? $order->inventory_review_status }}</span>
                @endif
            </div>

            @if($order->inventory_review_status !== 'approved' && $order->inventory_review_status !== 'pending_owner_after_count')
                <form method="POST" action="{{ route('user.stores.purchase-orders.inventory-count.return', [$store->id, $order->id]) }}" class="space-y-3">
                    @csrf
                    @if((int) $order->edit_return_count === 2)
                        <div class="ui-alert ui-alert-warning">
                            <span class="flex items-center gap-2"><strong class="ui-alert-title">هذه المرة الثالثة والأخيرة</strong><x-ui.help variant="warning" title="حد مرات التعديل" body="يمكن إعادة الطلبية للتعديل ثلاث مرات فقط. بعد إتمام هذه المرة، أي طلب جديد لإعادتها للتعديل سيلغي الطلبية بالكامل." /></span>
                            <span class="ui-alert-body">راجع ملاحظتك جيدًا واكتب جميع التعديلات المطلوبة للمحاسب.</span>
                        </div>
                    @elseif((int) $order->edit_return_count >= 3)
                        <div class="ui-alert ui-alert-danger-plain">
                            <strong class="ui-alert-title">استُنفدت مرات الإعادة للتعديل</strong>
                            <span class="ui-alert-body">إرسال طلب تعديل جديد سيؤدي إلى إلغاء الطلبية بالكامل.</span>
                        </div>
                    @endif
                    <div>
                        <div class="mb-2 flex items-center gap-2"><label for="orderEditReviewNote" class="ui-label">التعديل المطلوب <span class="ui-status-danger">*</span></label><x-ui.help title="متى تستخدم التعديل؟" body="استخدم هذا الإجراء عندما تكون بيانات الطلبية نفسها بحاجة إلى تصحيح، مثل المنتج أو الكمية أو الملاحظة." /></div>
                        <textarea id="orderEditReviewNote" maxlength="40" required name="inventory_review_note" class="ui-input" rows="3" placeholder="مثال: عدّل كمية المنتج أو احذف البند غير المطلوب"></textarea>
                    </div>
                    <button name="return_action" value="edit" class="ui-btn ui-btn-secondary">إعادة للمراجعة والتعديل</button>
                </form>
            @else
                <p class="ui-text-soft">هذا الإجراء غير متاح أثناء مراجعة نتيجة الجرد أو بعد اعتمادها.</p>
            @endif
        </section>

        <section class="ui-card p-5 space-y-4" data-inventory-selection>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <h2 class="ui-title text-xl font-black">إعادة للجرد</h2>
                        <x-ui.help title="إعادة للجرد" body="ابحث عن المنتجات المطلوب عدها وحددها فقط. سيعود المحاسب بنتيجة الجرد لهذه المنتجات دون تعديل بقية الطلبية." />
                    </div>
                </div>
            </div>

            @if($order->inventory_review_status !== 'approved' && $order->inventory_review_status !== 'pending_owner_after_count')
                <form method="POST" action="{{ route('user.stores.purchase-orders.inventory-count.return', [$store->id, $order->id]) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="return_action" value="inventory">
                    <div>
                        <div class="mb-2 flex items-center gap-2"><label for="inventoryReviewNote" class="ui-label">سبب طلب الجرد <span class="ui-status-danger">*</span></label><x-ui.help title="متى تطلب الجرد؟" body="خصص طلب الجرد للمنتجات التي تحتاج تحققًا فعليًا من الكمية الموجودة في المخزن، واكتب سببًا يساعد المحاسب أثناء العد." /></div>
                        <textarea id="inventoryReviewNote" maxlength="40" required name="inventory_review_note" class="ui-input" rows="3" placeholder="اكتب سببًا واضحًا يساعد المحاسب أثناء العد"></textarea>
                    </div>
                    <div class="ui-card-muted p-4 space-y-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <label class="ui-label flex-1">البحث عن منتج
                                <input type="search" class="ui-input" data-inventory-search autocomplete="off" placeholder="اكتب اسم المنتج لتحديده بسرعة">
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="ui-btn ui-btn-info" data-inventory-show-all aria-expanded="false"><span data-inventory-show-all-label>إظهار كل المنتجات</span></button>
                                <button type="button" class="ui-btn ui-btn-secondary" data-inventory-select-all>تحديد الكل</button>
                                <button type="button" class="ui-btn ui-btn-secondary" data-inventory-clear>إلغاء التحديد</button>
                            </div>
                        </div>
                        <p class="ui-text-soft" data-inventory-selection-count aria-live="polite"></p>
                        <div class="max-h-80 overflow-y-auto grid gap-2 sm:grid-cols-2" data-inventory-options>
                            @foreach($order->items as $countItem)
                                @if(!$countItem->add_to_owner_purchases && ($countItem->product_id || $countItem->matched_product_id))
                                    <label class="ui-card-muted p-3 flex items-center gap-2" data-inventory-option data-search="{{ e($countItem->productName()) }}">
                                        <input type="checkbox" name="item_ids[]" value="{{ $countItem->id }}">
                                        <span>{{ $countItem->productName() }}</span>
                                    </label>
                                @endif
                            @endforeach
                        </div>
                        <p class="ui-text-soft hidden" data-inventory-empty>لا توجد منتجات مطابقة للبحث.</p>
                    </div>
                    @error('item_ids')<span class="ui-status-danger">{{ $message }}</span>@enderror
                    <div class="flex items-center gap-2">
                        <button class="ui-btn ui-btn-warning">إعادة المنتجات المحددة للجرد</button>
                        <x-ui.help title="ماذا يحدث بعد الإعادة؟" body="تعود المنتجات المحددة فقط إلى المحاسب ليكتب كمياتها الفعلية، وتبقى بقية بنود الطلبية دون طلب جرد." />
                    </div>
                </form>
            @else
                <p class="ui-text-soft">هذا الإجراء غير متاح أثناء مراجعة نتيجة الجرد أو بعد اعتمادها.</p>
            @endif
        </section>

    @endif

    @if(in_array($order->status, ['approved','cancelled'], true) && $order->inventory_review_status !== 'pending_owner_after_count')
        <div class="py-4 space-y-5">
            <div class="pb-2">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                <h2 class="ui-title font-black text-lg flex items-center gap-2">
                    <svg class="w-5 h-5 ui-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    {{ $order->status === 'approved' ? 'بيانات الاعتماد المخزني' : ($order->status === 'draft' ? 'مراجعة بنود الطلبية قبل الاعتماد' : 'ملخص بنود الطلبية') }}
                    @if($order->status === 'draft')<x-ui.help title="إجراءات المسودة" body="عدّل البنود عند الحاجة، وبعد التأكد منها اعتمد الطلبية لإرسالها." />@endif
                    @if($order->status === 'received')<x-ui.help title="ملخص البنود المستلمة" body="راجع الكميات والتكاليف قبل الاعتماد المخزني." />@endif
                </h2>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border ui-border ui-surface-muted-bg p-3">
                <label for="summaryItemsSearch" class="mb-1 block ui-text-caption font-bold ui-text-muted">بحث داخل بنود الطلبية</label>
                <input type="search" id="summaryItemsSearch" autocomplete="off" placeholder="ابحث باسم المنتج أو الملاحظة أو نوع البند..." class="ui-input text-sm font-bold">
                <p id="summaryItemsSearchCount" class="mt-2 ui-text-caption ui-text-muted"></p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                @foreach($order->items as $index => $item)
                    @php
                        $unitLabels = [
                            'unit' => 'وحدة',
                            'piece' => 'حبة',
                            'roll' => 'رول',
                            'kit' => 'طقم',
                            'meter' => 'متر'
                        ];
                        $unitType = $unitLabels[$item->unit_type] ?? $item->unit_type ?? 'وحدة';
                        $variance = (float) ($item->price_variance ?? 0);
                        $productForCost = $item->product ?: $item->matchedProduct;
                        $currentProductCost = (float) ($productForCost?->cost_price ?? 0);
                        $receivedQuantity = (float) ($item->quantity_received ?? 0);
                        $receiptCost = (float) ($item->cost_price_at_receipt ?? $item->cost_price_at_order ?? 0);
                        $unitPriceReceipt = $receivedQuantity > 0 ? $receiptCost / $receivedQuantity : 0;
                        $requestedQuantity = (float) ($item->quantity_requested ?? 0);
                        $quantityDifference = $receivedQuantity - $requestedQuantity;
                        $hasQuantityDifference = $item->quantity_received !== null && abs($quantityDifference) > 0.0001;
                        $newProductCost = $currentProductCost;
                        $productForAudit = $item->product ?: $item->matchedProduct;
                        $inventoryAudit = $productForAudit?->inventoryAuditStatus($store) ?? ['color' => 'red'];
                        $inventoryAuditDot = [
                            'red' => 'ui-dot-danger',
                            'yellow' => 'ui-dot-warning',
                            'green' => 'ui-dot-success',
                        ][$inventoryAudit['color']] ?? 'ui-surface-muted-bg';

                        if ($item->update_product_cost && $productForCost && $receivedQuantity > 0 && $receiptCost > 0) {
                            $unitReceiptCost = $receiptCost / $receivedQuantity;
                            if (in_array($item->unit_type, ['meter', 'meters'], true) && (float) ($productForCost->roll_length ?? 0) > 0) {
                                $newProductCost = round($unitReceiptCost * (float) $productForCost->roll_length, 2);
                            } elseif ($item->unit_type === 'piece' && (int) ($productForCost->items_per_unit ?? 0) > 0) {
                                $newProductCost = round($unitReceiptCost * (int) $productForCost->items_per_unit, 2);
                            } else {
                                $newProductCost = round($unitReceiptCost, 2);
                            }
                        }
                    @endphp

                    @if($order->status === 'approved')
                        <details class="js-summary-item ui-disclosure ui-purchase-item-card" data-search="{{ e($item->productName().' '.($item->receipt_notes ?? '').' '.($item->add_to_owner_purchases ? 'مشتريات مالك بدون مخزون' : '').' '.$unitType) }}">
                            <summary class="cursor-pointer list-none p-4 ui-hover-surface transition" aria-label="عرض تفاصيل {{ $item->productName() }}">
                                <div class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 sm:grid-cols-[auto_minmax(0,1fr)_auto_auto_auto]">
                                    <span class="ui-status-info font-bold ui-text-caption">{{ $index + 1 }}</span>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="inline-flex w-2 h-2 rounded-full {{ $inventoryAuditDot }} shrink-0" title="{{ $inventoryAudit['label'] ?? 'حالة الجرد' }}"></span>
                                            <strong class="ui-title truncate">{{ $item->productName() }}</strong>
                                        </div>
                                        @if($item->receipt_notes)
                                            <span class="block ui-text-caption ui-text-muted truncate mt-1">{{ $item->receipt_notes }}</span>
                                        @endif
                                        <span class="block sm:hidden ui-text-caption ui-text-muted mt-1">
                                            المطلوب {{ number_format($requestedQuantity, 2) }} · المستلم {{ number_format($receivedQuantity, 2) }} {{ $unitType }}
                                        </span>
                                    </div>
                                    <span class="ui-badge ui-badge-neutral whitespace-nowrap">{{ $unitType }}</span>
                                    <span class="hidden sm:block ui-text-caption ui-text-muted">المطلوب: <strong class="ui-title">{{ number_format($requestedQuantity, 2) }}</strong></span>
                                    <span class="hidden sm:block ui-text-caption ui-text-muted">المستلم: <strong class="ui-status-info">{{ number_format($receivedQuantity, 2) }}</strong></span>
                                </div>
                            </summary>
                            <div class="p-4 border-t ui-border">
                    @else
                        <article class="js-summary-item ui-purchase-item-card" data-search="{{ e($item->productName().' '.($item->receipt_notes ?? '').' '.($item->add_to_owner_purchases ? 'مشتريات مالك بدون مخزون' : '').' '.$unitType) }}">
                        <div class="ui-purchase-item-card__header">
                                <div>
                                    <p class="ui-status-info font-bold ui-text-caption mb-1">({{ $index + 1 }})</p>
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="inline-flex w-2 h-2 rounded-full {{ $inventoryAuditDot }} flex-shrink-0" title="{{ $inventoryAudit['label'] ?? 'حالة الجرد' }}"></span>
                                        <p class="ui-title font-bold text-base leading-tight break-words min-w-0">{{ $item->productName() }}</p>
                                        @if($item->add_to_owner_purchases)
                                            <span class="inline-flex items-center ui-text-caption font-bold ui-status-warning">مشتريات مالك</span>
                                        @endif
                                    </div>
                                    @if($item->receipt_notes)
                                        <p class="inline-block mt-2 px-2 py-1 rounded ui-status-warning-bg ui-status-warning ui-text-caption border ui-border">{{ $item->receipt_notes }}</p>
                                    @endif
                            </div>
                            <span class="ui-badge ui-badge-neutral whitespace-nowrap">{{ $unitType }}</span>
                        </div>
                    @endif

                        <div class="ui-purchase-item-card__metrics text-sm">
                            <div class="ui-purchase-item-card__metric">
                                @php $currentStock = $item->product?->quantity ?? $item->matchedProduct?->quantity ?? null; @endphp
                                @if($currentStock !== null && $order->status !== 'approved')
                                    <span class="block ui-status-info ui-text-caption font-bold mb-1" title="الكمية المتوفرة حالياً في المخزون">المتوفر الآن: {{ \App\Support\ProductQuantityFormatter::currentStock($productForCost) }}</span>
                                @endif
                                <span class="block ui-text-muted ui-text-caption mb-1">الكمية المطلوبة</span>
                                <strong class="ui-title">{{ $requestedQuantity > 0 ? number_format($requestedQuantity, 2).' '.$unitType : 'غير محدد' }}</strong>
                            </div>
                            <div class="ui-purchase-item-card__metric">
                                @php
                                    $unitPriceOrder = ((float)$item->quantity_requested > 0) ? ((float)$item->cost_price_at_order / (float)$item->quantity_requested) : (float)$item->cost_price_at_order;
                                @endphp
                                @if((float)$item->cost_price_at_order > 0)
                                    <span class="block ui-status-info ui-text-caption font-bold mb-1" title="سعر الوحدة/الطقم/الرول">السعر المفرد: {{ number_format($unitPriceOrder, 2) }}</span>
                                @endif
                                <span class="block ui-text-muted ui-text-caption mb-1">تكلفة الطلب</span>
                                <strong class="ui-title">{{ number_format((float) $item->cost_price_at_order, 2) }} <span class="ui-text-caption font-normal ui-text-muted">ر.س</span></strong>
                            </div>

                            @if(in_array($order->status, ['received', 'approved']))
                                <div class="ui-purchase-item-card__metric">
                                    <span class="block ui-text-muted ui-text-caption mb-1">الكمية المستلمة</span>
                                    <strong class="ui-status-info">{{ $item->quantity_received !== null ? number_format($receivedQuantity, 2).' '.$unitType : '-' }}</strong>
                                </div>
                                <div class="ui-purchase-item-card__metric">
                                    @if($receivedQuantity > 0 && $receiptCost > 0)
                                        <span class="block ui-status-info ui-text-caption font-bold mb-1" title="سعر الوحدة/الطقم/الرول المستلم">السعر المفرد: {{ number_format($unitPriceReceipt, 2) }}</span>
                                    @endif
                                    <span class="block ui-text-muted ui-text-caption mb-1">تكلفة الاستلام</span>
                                    <strong class="ui-status-info">{{ $receiptCost > 0 ? number_format($receiptCost, 2) : '-' }} <span class="ui-text-caption font-normal ui-status-info">ر.س</span></strong>
                                </div>

                                @if($hasQuantityDifference)
                                    <div class="ui-purchase-item-card__metric sm:col-span-2 {{ $quantityDifference < 0 ? 'ui-status-danger-bg' : 'ui-status-warning-bg' }}">
                                        <span class="block ui-text-muted ui-text-caption mb-1">فرق الكمية بين المطلوب والمستلم</span>
                                        <strong class="{{ $quantityDifference < 0 ? 'ui-status-danger' : 'ui-status-warning' }}">
                                            {{ $quantityDifference > 0 ? '+' : '' }}{{ number_format($quantityDifference, 2) }} {{ $unitType }}
                                        </strong>
                                    </div>
                                @endif
                            @endif

                            @if($order->status === 'approved' && !$item->add_to_owner_purchases && $productForCost && $item->stock_quantity_before !== null && $item->stock_quantity_after !== null)
                                <div class="ui-purchase-item-card__metric">
                                    <span class="block ui-text-muted ui-text-caption mb-1">المخزون قبل التوريد</span>
                                    <strong class="ui-title">{{ \App\Support\ProductQuantityFormatter::stockSnapshot($productForCost, (float) $item->stock_quantity_before) }}</strong>
                                </div>
                                <div class="ui-purchase-item-card__metric">
                                    <span class="block ui-text-muted ui-text-caption mb-1">المخزون بعد الاعتماد</span>
                                    <strong class="ui-status-success">{{ \App\Support\ProductQuantityFormatter::stockSnapshot($productForCost, (float) $item->stock_quantity_after) }}</strong>
                                </div>
                                <div class="ui-purchase-item-card__metric sm:col-span-2">
                                    <span class="block ui-text-muted ui-text-caption mb-1">الكمية المضافة للمخزون</span>
                                    <strong class="ui-status-info">{{ \App\Support\ProductQuantityFormatter::transferQuantity($productForCost, $receivedQuantity, (string) $item->unit_type) }}</strong>
                                </div>

                                @php
                                    $costBeforeApproval = (float) ($item->cost_price_before ?? 0);
                                    $costAfterApproval = (float) ($item->cost_price_after ?? $costBeforeApproval);
                                    $costChangedOnApproval = abs($costAfterApproval - $costBeforeApproval) > 0.0001;
                                @endphp
                                @if($costChangedOnApproval)
                                    <div class="ui-purchase-item-card__metric">
                                        <span class="block ui-text-muted ui-text-caption mb-1">تكلفة المنتج قبل الاعتماد</span>
                                        <strong class="ui-title">{{ number_format($costBeforeApproval, 2) }} ر.س</strong>
                                    </div>
                                    <div class="ui-purchase-item-card__metric">
                                        <span class="block ui-text-muted ui-text-caption mb-1">تكلفة المنتج بعد الاعتماد</span>
                                        <strong class="ui-status-success">{{ number_format($costAfterApproval, 2) }} ر.س</strong>
                                    </div>
                                @else
                                    <div class="ui-purchase-item-card__metric sm:col-span-2">
                                        <span class="block ui-text-muted ui-text-caption mb-1">تكلفة المنتج الأساسية (لم تتغير)</span>
                                        <strong class="ui-title">{{ number_format($costAfterApproval, 2) }} ر.س</strong>
                                    </div>
                                @endif
                            @endif

                            @if($order->status === 'received')
                                <div class="ui-purchase-item-card__metric flex items-center justify-between sm:col-span-2">
                                    <span class="ui-text-muted ui-text-caption">حالة السعر:</span>
                                    @if($variance > 0)
                                        <strong class="ui-status-danger ui-text-caption ui-status-danger-bg px-2 py-1 rounded">أكثر بـ {{ number_format($variance, 2) }} ر.س</strong>
                                    @elseif($variance < 0)
                                        <strong class="ui-status-success ui-text-caption ui-status-success-bg px-2 py-1 rounded">أقل بـ {{ number_format(abs($variance), 2) }} ر.س</strong>
                                    @else
                                        <strong class="ui-text-muted ui-text-caption">مطابق للنظام</strong>
                                    @endif
                                </div>

                                @if($item->update_product_cost)
                                    <div class="ui-purchase-item-card__metric ui-status-info-bg sm:col-span-2">
                                        <span class="block ui-status-info ui-text-caption mb-1">تحديث تكلفة المنتج:</span>
                                        <div class="flex justify-between items-center ui-text-caption">
                                            <span class="ui-text-muted line-through">{{ number_format($currentProductCost, 2) }}</span>
                                            <svg class="w-4 h-4 ui-text-muted mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                            <span class="ui-title font-bold">{{ number_format($newProductCost, 2) }} ر.س</span>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @if($order->status === 'approved')
                            </div>
                        </details>
                    @else
                        </article>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    @endif

    @if($order->status === 'sent' || $isOwnerReceiptReview)
    @if($order->status === 'sent')
    <section class="space-y-4" aria-labelledby="supplierShareTitle">
        <div>
            <h2 id="supplierShareTitle" class="ui-title text-xl font-black">إرسال نسخة للمورد</h2>
            <p class="ui-text-soft mt-1">اختر طريقة واحدة فقط عند الحاجة؛ لا يتم الإرسال تلقائيًا.</p>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <article class="ui-card p-5 flex flex-col gap-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <i class="fa-brands fa-whatsapp ui-status-success text-2xl shrink-0" aria-hidden="true"></i>
                        <h3 class="ui-title text-lg font-black">واتساب</h3>
                    </div>
                    <x-ui.help title="إرسال واتساب" body="يفتح واتساب برسالة الطلبية الجاهزة. راجع رقم المورد ومحتوى الرسالة قبل الإرسال. هذا الإجراء لا يؤكد الاستلام." />
                </div>
                <p class="ui-text-soft flex-1">فتح رسالة جاهزة تحتوي تفاصيل الطلبية.</p>
                <a id="whatsappLink" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode($whatsappText) }}" class="ui-btn ui-btn-success w-full">فتح واتساب</a>
            </article>
            <article class="ui-card p-5 flex flex-col gap-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <i class="fa-solid fa-file-pdf ui-status-info text-2xl shrink-0" aria-hidden="true"></i>
                        <h3 class="ui-title text-lg font-black">PDF</h3>
                    </div>
                    <x-ui.help title="مستند PDF" body="ينزّل نسخة ثابتة من الطلبية للطباعة أو المشاركة. تنزيل المستند لا يغيّر حالة الطلبية ولا يؤكد الاستلام." />
                </div>
                <p class="ui-text-soft flex-1">تنزيل مستند الطلبية للطباعة أو المشاركة.</p>
                <a id="purchaseOrderPdfLink" href="{{ route('user.stores.purchase-orders.pdf', [$store->id, $order->id, 'type' => 'receipt']) }}" class="ui-btn ui-btn-info w-full">تنزيل PDF</a>
            </article>
        </div>
    </section>
    @endif
    <form id="receipt-review" data-order-id="{{ $order->id }}" method="POST" action="{{ route('user.stores.purchase-orders.receive', [$store->id, $order->id]) }}" class="space-y-6">
        @csrf

        @if($isOwnerReceiptReview)
            <section class="ui-card p-5 space-y-5" aria-labelledby="receiptReviewSummaryTitle">
                <div>
                    <h2 id="receiptReviewSummaryTitle" class="ui-title text-xl font-black">ملخص مراجعة الاستلام</h2>
                    <p class="ui-text-soft mt-1">ابدأ بالبنود التي عدّلها المحاسب أو تحتاج إجراءً قبل اعتماد المراجعة.</p>
                </div>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                    <div class="ui-card-muted p-3"><span class="ui-text-soft block">إجمالي البنود</span><strong class="ui-title text-xl">{{ $receiptReviewItems->count() }}</strong></div>
                    @if($receiptReviewChangedCount > 0)<div class="ui-card-muted p-3"><span class="ui-text-soft block">عدّلها المحاسب</span><strong class="ui-status-info text-xl">{{ $receiptReviewChangedCount }}</strong></div>@endif
                    @if($receiptReviewVarianceCount > 0)<div class="ui-card-muted p-3"><span class="ui-text-soft block">فروقات تكلفة</span><strong class="ui-status-warning text-xl">{{ $receiptReviewVarianceCount }}</strong></div>@endif
                    @if($receiptReviewUnresolvedCount > 0)<div class="ui-card-muted p-3"><span class="ui-text-soft block">تحتاج ربطًا</span><strong class="ui-status-danger text-xl">{{ $receiptReviewUnresolvedCount }}</strong></div>@endif
                    @if($receiptReviewOwnerPurchaseCount > 0)<div class="ui-card-muted p-3"><span class="ui-text-soft block">مشتريات مالك</span><strong class="ui-status-warning text-xl">{{ $receiptReviewOwnerPurchaseCount }}</strong></div>@endif
                </div>
                <div class="flex flex-wrap gap-2" aria-label="تصفية بنود مراجعة الاستلام">
                    @foreach(collect([
                        'all' => ['label' => 'كل البنود', 'count' => $receiptReviewItems->count()],
                        'changed' => ['label' => 'عدّلها المحاسب', 'count' => $receiptReviewChangedCount],
                        'variance' => ['label' => 'فروقات التكلفة', 'count' => $receiptReviewVarianceCount],
                        'unresolved' => ['label' => 'تحتاج ربطًا', 'count' => $receiptReviewUnresolvedCount],
                        'owner' => ['label' => 'مشتريات المالك', 'count' => $receiptReviewOwnerPurchaseCount],
                    ])->filter(fn ($filter, $key) => $key === 'all' || $filter['count'] > 0) as $filterValue => $filter)
                        <button type="button" class="ui-btn {{ $filterValue === 'all' ? 'ui-btn-primary' : 'ui-btn-secondary' }}" data-receipt-filter="{{ $filterValue }}" aria-pressed="{{ $filterValue === 'all' ? 'true' : 'false' }}">{{ $filter['label'] }}</button>
                    @endforeach
                </div>
            </section>
        @endif

        @if($order->status === 'sent')
        <div class="ui-card p-5 space-y-4">
            <h2 class="ui-title font-black text-lg flex items-center gap-2">
                <svg class="w-6 h-6 ui-status-info" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                تأكيد الاستلام
            </h2>
            <div class="flex items-center gap-2 ui-status-info text-sm">
                <span>طابق المستلم فعليًا مع المطلوب قبل الحفظ.</span>
                <x-ui.help variant="warning" title="تأكيد الاستلام" body="يمكن للمالك تأكيد ما وصل من المورد أو مراجعة مدخلات المحاسب وتعديلها." />
            </div>
            <div class="space-y-2 text-sm mt-4">
                <div class="flex items-center gap-2"><strong class="ui-title">الكمية فارغة؟</strong><x-ui.help title="الكمية فارغة؟" body="إذا تركتها فارغة، سيستخدم النظام الكمية المطلوبة." /></div>
                <div class="flex items-center gap-2"><strong class="ui-title">السعر فارغ؟</strong><x-ui.help title="السعر فارغ؟" body="إذا تركته فارغًا، ستبقى التكلفة المسجلة في الطلبية." /></div>
                <div class="flex items-center gap-2"><strong class="ui-title">تغير في السعر؟</strong><x-ui.help title="تغير في السعر؟" body="إذا اختلف سعر المورد، راجع الفرق واختر تحديث التكلفة فقط عندما تريد اعتماد السعر الجديد." /></div>
            </div>
        </div>
        @endif

        <div class="rounded-xl border ui-border ui-surface-muted-bg p-3">
            <label for="receiveItemsSearch" class="mb-1 block ui-text-caption font-bold ui-text-muted">البحث داخل بنود الاستلام الحالية فقط</label>
            <input type="search" id="receiveItemsSearch" autocomplete="off" placeholder="صفِّ بنود الاستلام بالاسم أو الملاحظة أو نوع البند..." class="ui-input text-sm font-bold">
            <p id="receiveItemsSearchCount" class="mt-2 ui-text-caption ui-text-muted"></p>
        </div>

        <div id="receiveItemsEmpty" class="ui-alert ui-alert-info hidden">لا توجد بنود تطابق البحث والفلتر المحددين.</div>

        <div class="ui-receipt-review-grid">
            @foreach ($order->items as $item)
                @php
                    $receiptReviewProduct = $item->product ?: $item->matchedProduct;
                    $baseCost = (float) ($receiptReviewProduct?->cost_price ?? $item->cost_price_at_order ?? 0);
                    $receiveUnits = ['unit' => ['label' => 'افتراضي (وحدة)', 'cost' => $baseCost]];

                    if ($receiptReviewProduct && ((($receiptReviewProduct->product_type ?? null) === 'fractional') || (float) $receiptReviewProduct->roll_length > 0)) {
                        $rollLength = (float) $receiptReviewProduct->roll_length;
                        $receiveUnits = [
                            'roll' => ['label' => 'رول كامل', 'cost' => $baseCost],
                            'meter' => ['label' => 'متر', 'cost' => $rollLength > 0 ? $baseCost / $rollLength : 0],
                        ];
                    } elseif ($receiptReviewProduct?->is_splittable) {
                        $itemsPerUnit = (int) $receiptReviewProduct->items_per_unit;
                        $receiveUnits = [
                            'kit' => ['label' => 'طقم كامل', 'cost' => $baseCost],
                            'piece' => ['label' => 'حبة', 'cost' => $itemsPerUnit > 0 ? $baseCost / $itemsPerUnit : 0],
                        ];
                    }

                    $hasUnitChoices = count($receiveUnits) > 1;
                    $defaultReviewUnit = old(
                        'items.'.$item->id.'.unit_type',
                        in_array($item->unit_type, array_keys($receiveUnits), true)
                            ? $item->unit_type
                            : ($receiptReviewProduct?->quick_sale_default_unit ?: array_key_first($receiveUnits))
                    );
                    $variance = (float) ($item->price_variance ?? 0);

                    $varianceClass = 'hidden';
                    $varianceText = '';
                    if ($variance > 0) {
                        $varianceClass = 'ui-status-danger-bg ui-status-danger border ui-border';
                        $varianceText = 'زيادة: ' . number_format($variance, 2) . ' ر.س';
                    } elseif ($variance < 0) {
                        $varianceClass = 'ui-status-success-bg ui-status-success border ui-border';
                        $varianceText = 'نقصان: ' . number_format(abs($variance), 2) . ' ر.س';
                    }

                    $cardBorder = $variance > 0 ? 'ui-border ' : ($variance < 0 ? 'ui-border ' : 'ui-border');
                    $productForAudit = $item->product ?: $item->matchedProduct;
                    $inventoryAudit = $productForAudit?->inventoryAuditStatus($store) ?? ['color' => 'red'];
                    $inventoryAuditDot = [
                        'red' => 'ui-dot-danger',
                        'yellow' => 'ui-dot-warning',
                        'green' => 'ui-dot-success',
                    ][$inventoryAudit['color']] ?? 'ui-surface-muted-bg';
                    $accountantReceiptChange = collect($accountantReceiptChanges->get((string) $item->id, []));
                    $accountantChangedFields = collect($accountantReceiptChange->get('fields', []));
                    $hasAccountantReceiptChange = $accountantChangedFields->isNotEmpty();
                @endphp

                <details class="js-receive-item ui-disclosure ui-purchase-item-card"
                    data-search="{{ e($item->productName().' '.($item->receipt_notes ?? '').' '.($item->add_to_owner_purchases ? 'مشتريات مالك بدون مخزون' : '')) }}"
                    data-changed="{{ $hasAccountantReceiptChange ? '1' : '0' }}"
                    data-variance="{{ abs($variance) > 0.01 ? '1' : '0' }}"
                    data-unresolved="{{ ! $item->product_id && ! $item->matched_product_id && ! $item->add_to_owner_purchases ? '1' : '0' }}"
                    data-owner="{{ $item->add_to_owner_purchases ? '1' : '0' }}">
                    <summary class="ui-disclosure-summary">
                        <span class="flex items-center gap-2 min-w-0">
                            <strong class="ui-title break-words">{{ $item->productName() }}</strong>
                            @if($hasAccountantReceiptChange)
                                <span class="ui-badge ui-badge-info">عدّله المحاسب</span>
                                <x-ui.help title="تعديل المحاسب" body="غيّر المحاسب: {{ $accountantChangedFields->map(fn ($field) => ['quantity' => 'الكمية', 'unit' => 'الوحدة', 'cost' => 'التكلفة', 'note' => 'الملاحظة'][$field] ?? $field)->implode('، ') }}. افتح البطاقة لمراجعة القيم." />
                            @endif
                            @if($item->add_to_owner_purchases)<span class="ui-badge ui-badge-warning">مشتريات مالك</span>@endif
                        </span>
                        <i class="fa-solid fa-chevron-down ui-text-soft ui-disclosure-chevron shrink-0" aria-hidden="true"></i>
                    </summary>
                    <div class="p-5 space-y-5 ui-text-muted">
                    <div class="space-y-3">
                        <div class="flex justify-between items-start gap-4">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="inline-flex w-2 h-2 rounded-full {{ $inventoryAuditDot }} flex-shrink-0" title="{{ $inventoryAudit['label'] ?? 'حالة الجرد' }}"></span>
                                <div class="flex items-center gap-2 flex-wrap min-w-0">
                                    <h3 class="font-bold text-base ui-title leading-snug break-words min-w-0">{{ $item->productName() }}</h3>
                                    @if($item->add_to_owner_purchases)
                                        <span class="inline-flex items-center ui-text-caption font-bold ui-status-warning">مشتريات مالك</span>
                                    @endif
                                </div>
                            </div>
                            <div id="variance-{{ $item->id }}" class="ui-text-caption font-bold px-3 py-1.5 rounded-lg whitespace-nowrap {{ $varianceClass }}">
                                {{ $varianceText }}
                            </div>
                        </div>

                        @if($item->receipt_notes)
                            <div class="text-sm ui-status-warning-bg border ui-border px-3 py-2 rounded-lg">
                                <strong>ملاحظة:</strong> {{ $item->receipt_notes }}
                            </div>
                        @endif

                        <div class="grid grid-cols-2 gap-3 text-sm ui-surface-muted-bg p-3 rounded-xl border ui-border">
                            <div>
                                @php $currentStock = $item->product?->quantity ?? $item->matchedProduct?->quantity ?? null; @endphp
                                @if($currentStock !== null)
                                    <span class="block ui-status-info ui-text-caption font-bold mb-1" title="الكمية المتوفرة حالياً في المخزون">المتوفر: {{ (float) $currentStock }}</span>
                                @endif
                                <span class="ui-text-muted block ui-text-caption mb-1">الكمية المطلوبة</span>
                                <span class="ui-title font-bold">{{ (float) $item->quantity_requested > 0 ? number_format($item->quantity_requested, 2) : 'غير محدد' }}</span>
                            </div>
                            <div>
                                @php
                                    $unitPriceOrder = ((float)$item->quantity_requested > 0) ? ((float)$item->cost_price_at_order / (float)$item->quantity_requested) : (float)$item->cost_price_at_order;
                                @endphp
                                @if((float)$item->cost_price_at_order > 0)
                                    <span class="block ui-status-info ui-text-caption font-bold mb-1" title="سعر الوحدة/الطقم/الرول">السعر المفرد: {{ number_format($unitPriceOrder, 2) }}</span>
                                @endif
                                <span class="ui-text-muted block ui-text-caption mb-1">التكلفة المسجلة</span>
                                <span class="ui-title font-bold">{{ number_format((float) $item->cost_price_at_order, 2) }} ر.س</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
                            <div class="space-y-1.5">
                                <label class="block ui-text-caption font-bold ui-text-muted">الكمية المستلمة الفعلية</label>
                                <input name="items[{{ $item->id }}][quantity_received]" type="number" step="0.01" min="0" value="{{ old('items.'.$item->id.'.quantity_received', $item->quantity_received) }}" placeholder="اتركه فارغاً للاستلام الكامل" class="ui-input text-sm">
                                <span class="js-receipt-expected ui-text-soft text-sm"></span>
                            </div>

                            @if($hasUnitChoices)
                                <div class="space-y-1.5">
                                    <label class="block ui-text-caption font-bold ui-text-muted">وحدة الاستلام</label>
                                    <select name="items[{{ $item->id }}][unit_type]" class="js-receipt-unit ui-input text-sm appearance-none">
                                        @foreach ($receiveUnits as $unitValue => $unit)
                                            <option value="{{ $unitValue }}" data-unit-cost="{{ (float) $unit['cost'] }}" {{ $defaultReviewUnit === $unitValue ? 'selected' : '' }}>
                                                {{ $unit['label'] }} @if($unit['cost'] > 0) ({{ number_format($unit['cost'], 2) }}) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                <input type="hidden" name="items[{{ $item->id }}][unit_type]" value="{{ $item->unit_type ?: 'unit' }}" class="js-receipt-unit" data-unit-cost="{{ $baseCost }}">
                            @endif

                            <div class="space-y-1.5 sm:col-span-2">
                                @php $receiptPriceRequired = $item->add_to_owner_purchases && (float) $item->cost_price_at_order <= 0; @endphp
                                <label class="block ui-text-caption font-bold {{ $item->add_to_owner_purchases ? 'ui-status-warning' : 'ui-text-muted' }}">
                                    سعر الاستلام الفعلي (للكمية كاملة){{ $receiptPriceRequired ? ' *' : '' }}
                                </label>
                                <input name="items[{{ $item->id }}][cost_price_at_receipt]" type="number" step="0.01" min="0" {{ $receiptPriceRequired ? 'required' : '' }} value="{{ old('items.'.$item->id.'.cost_price_at_receipt', $item->cost_price_at_receipt) }}" placeholder="{{ $receiptPriceRequired ? 'هذا الحقل إلزامي لعدم وجود سعر سابق' : 'اتركه فارغاً لاعتماد السعر المحفوظ' }}" class="ui-input text-sm js-receipt-price" data-order-price="{{ (float) $item->cost_price_at_order }}" data-requested-qty="{{ (float) $item->quantity_requested }}" data-variance-target="variance-{{ $item->id }}">
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 flex flex-col gap-3 mt-auto">
                        <div class="flex flex-col gap-3">
                            @if(!$item->add_to_owner_purchases)
                                <div class="space-y-3">
                                    <label class="flex items-center gap-3 cursor-pointer ui-text-muted select-none group">
                                        <input type="hidden" name="items[{{ $item->id }}][update_product_cost]" value="0">
                                        <input type="checkbox" name="items[{{ $item->id }}][update_product_cost]" value="1" @checked($item->update_product_cost) class="rounded ui-surface-muted-bg ui-border ui-status-info w-5 h-5 transition">
                                        <span class="text-sm group-ui-hover-info transition">اعتماد سعر التوريد الجديد كتكلفة المنتج</span>
                                    </label>
                                    <label class="flex items-center gap-3 cursor-pointer ui-text-muted select-none group">
                                        <input type="hidden" name="items[{{ $item->id }}][add_to_owner_purchases]" value="0">
                                        <input type="checkbox" name="items[{{ $item->id }}][add_to_owner_purchases]" value="1" class="rounded ui-surface-muted-bg ui-border ui-status-info w-5 h-5 transition">
                                        <span class="text-sm group-ui-hover-info transition">تسجيل هذا البند كمشتريات مالك بدل إضافته للمخزون</span>
                                    </label>
                                </div>
                            @else
                                <span class="text-sm ui-status-warning">مشتريات مالك</span>
                            @endif

                            @if(!$item->product_id && !$item->matched_product_id)
                                <button type="button"
                                        class="js-open-owner-product-modal ui-btn ui-btn-secondary ui-text-caption"
                                        data-owner-product-url="{{ route('user.stores.purchase-orders.items.owner-product.store', [$store->id, $order->id, $item->id]) }}"
                                        data-owner-product-name="{{ $item->productName() }}"
                                        data-owner-product-unit="{{ in_array($item->unit_type, ['piece', 'kit', 'roll'], true) ? $item->unit_type : 'piece' }}"
                                        data-owner-items-per-unit="{{ (int) $item->items_per_unit }}"
                                        data-owner-roll-length="{{ (float) $item->roll_length }}"
                                        data-owner-requested-quantity="{{ (float) $item->quantity_requested }}"
                                        data-owner-order-cost="{{ (float) $item->cost_price_at_order }}">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    ربط أو إنشاء منتج
                                </button>
                            @else
                                <span class="ui-badge ui-badge-success">المنتج مربوط</span>
                            @endif
                        </div>
                    </div>
                    </div>
                </details>
            @endforeach
        </div>

        <div class="ui-alert ui-alert-info">
            <strong class="ui-alert-title">ماذا يحدث بعد الاعتماد؟</strong>
            <span class="ui-alert-body">سيتم حفظ مراجعتك والانتقال إلى خطوة الاعتماد المخزني. لن تضاف الكميات إلى المخزون في هذه الخطوة.</span>
        </div>

        <div class="pt-4 flex justify-end">
            <button class="ui-btn ui-btn-primary w-full py-4 text-lg md:w-auto">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                {{ $isOwnerReceiptReview ? 'اعتماد مراجعة الاستلام والمتابعة' : 'حفظ بيانات الاستلام' }}
            </button>
        </div>
    </form>
    @endif

    @if($order->status === 'received' && $order->workflow_status === 'pending_inventory_approval')
        <span id="inventory-approval"></span>
        <form id="approveOrderForm" data-order-id="{{ $order->id }}" method="POST" action="{{ route('user.stores.purchase-orders.approve', [$store->id, $order->id]) }}" class="space-y-6">
            @csrf
            <section class="ui-card p-4">
                <span class="ui-text-soft block">تاريخ اعتماد المالك للجرد</span>
                <strong class="ui-title">{{ $latestCountApprovalEvent?->created_at?->format('Y-m-d H:i') ?: 'لم يطلب جرد' }}</strong>
            </section>
            @if($order->items->contains(fn ($item) => (bool) ($item->add_to_owner_purchases ?? false)))
                <div class="ui-alert ui-alert-warning">
                    <strong>الأسطر المختارة كمشتريات مالك</strong>
                    <x-ui.help variant="warning" title="مشتريات المالك" body="ستُسجل هذه العناصر مباشرة في المشتريات ولن تُضاف إلى المخزون." />
                </div>
            @endif
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach($order->items->where('excluded_after_count', false) as $approvalItem)
                    @php
                        $approvalProduct = $approvalItem->product ?: $approvalItem->matchedProduct;
                        $approvalUnit = $unitLabels[$approvalItem->unit_type ?: 'unit'] ?? 'وحدة';
                        $currentStock = (float) ($approvalProduct?->getRawOriginal('quantity') ?? 0);
                        $currentCost = (float) ($approvalProduct?->cost_price ?? $approvalItem->cost_price_at_order ?? 0);
                        $receiptCost = (float) ($approvalItem->cost_price_at_receipt ?? $approvalItem->cost_price_at_order ?? 0);
                        $approvedCost = $currentCost;
                        $receivedQuantity = (float) ($approvalItem->quantity_received ?? 0);
                        if ($approvalItem->update_product_cost && $approvalProduct && $receivedQuantity > 0 && $receiptCost > 0) {
                            $receivedUnitCost = $receiptCost / $receivedQuantity;
                            if (in_array($approvalItem->unit_type, ['meter', 'meters'], true) && (float) ($approvalProduct->roll_length ?? 0) > 0) {
                                $approvedCost = round($receivedUnitCost * (float) $approvalProduct->roll_length, 2);
                            } elseif ($approvalItem->unit_type === 'piece' && (int) ($approvalProduct->items_per_unit ?? 0) > 0) {
                                $approvedCost = round($receivedUnitCost * (int) $approvalProduct->items_per_unit, 2);
                            } else {
                                $approvedCost = round($receivedUnitCost, 2);
                            }
                        }
                    @endphp
                    <article class="ui-card-muted p-4 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h4 class="ui-title font-black">{{ $approvalItem->productName() }}</h4>
                            <span class="ui-badge {{ $approvalItem->add_to_owner_purchases ? 'ui-badge-warning' : 'ui-badge-info' }}">{{ $approvalItem->add_to_owner_purchases ? 'مشتريات مالك' : 'منتج مخزني' }}</span>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div><span class="ui-text-soft block">الكمية الآن</span><strong class="ui-title">{{ number_format($currentStock, 2) }}</strong></div>
                            <div><span class="ui-text-soft block">الكمية المستلمة</span><strong class="ui-title">{{ number_format((float) $approvalItem->quantity_received, 2) }} {{ $approvalUnit }}</strong></div>
                            <div><span class="ui-text-soft block">نوع المستلم</span><strong class="ui-title">{{ $approvalUnit }}</strong></div>
                            <div><span class="ui-text-soft block">تكلفة الاستلام</span><strong class="ui-title">{{ number_format($receiptCost, 2) }} ر.س</strong></div>
                            <div><span class="ui-text-soft block">التكلفة التي ستعتمد</span><strong class="ui-title">{{ number_format($approvedCost, 2) }} ر.س</strong></div>
                        </div>
                        @if($approvalItem->update_product_cost)
                            <span class="ui-badge ui-badge-warning">سيتم اعتماد تكلفة الاستلام الجديدة</span>
                        @endif
                    </article>
                @endforeach
            </div>
            <section class="ui-card p-5 space-y-4">
                <label class="block">
                    <span class="mb-2 flex items-center gap-2 font-bold ui-title">تاريخ الاعتماد وإضافة المخزون <x-ui.help title="تاريخ الاعتماد" body="تُسجل حركة التوريد وحفظ مشتريات المالك في هذا التاريخ. التاريخ الافتراضي هو يوم عمل المتجر الحالي." /></span>
                    <input type="date" name="business_date" value="{{ old('business_date', $currentBusinessDate) }}" required class="ui-input">
                </label>
            <div class="flex items-start gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="ui-title font-black text-xl">اعتماد الطلبية</h3>
                        <x-ui.help variant="warning" title="ماذا ينفذ اعتماد الطلبية؟" body="يضيف الكميات المستلمة لمنتجات البيع، ويسجل البنود المحددة كمشتريات مالك دون إضافتها للمخزون، ويطبق تحديث التكلفة على البنود التي اخترت لها السعر الجديد، ثم يحفظ سجل الحركة ويغلق الطلبية نهائيًا." />
                    </div>
                </div>
            </div>
            <div class="pt-4 flex justify-end">
                <button id="approveOrderButton" class="ui-btn ui-btn-primary ui-btn-borderless w-full md:w-auto px-10 disabled:cursor-not-allowed font-black py-4 flex justify-center items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span class="js-approve-button-text">اعتماد الطلبية</span>
                </button>
            </div>
            </section>
        </form>
    @endif
</div>

<div id="ownerProductModal" class="ui-modal-backdrop hidden" dir="rtl">
    <div class="ui-modal-panel ui-modal-panel-wide">
        <div class="ui-modal-header">
            <div class="flex items-center gap-2">
                <strong class="ui-title text-lg font-bold">حفظ المنتج وربطه بالطلبية</strong>
                <x-ui.help title="طريقة حفظ المنتج" body="اختر منتج بيع إذا كنت ستبيعه ويجب أن يظهر في المخزون ونقاط البيع. اختر مشتريات مالك إذا كان للاستخدام أو الشراء الخاص ولن يظهر ضمن منتجات البيع." />
            </div>
            <button type="button" id="closeOwnerProductModal" class="ui-modal-close-text-danger">إغلاق</button>
        </div>

        <form id="ownerProductForm" class="p-6 space-y-5" method="POST">
            @csrf
            <div id="ownerProductErrors" class="hidden rounded-xl border ui-border ui-status-danger-bg p-4 text-sm ui-status-danger"></div>

            <fieldset class="ui-card p-4 space-y-3">
                <legend class="ui-title font-black px-2">اختر الإجراء</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="ui-card-muted p-4 flex items-start gap-3 cursor-pointer">
                        <input type="radio" name="product_action" value="link" checked>
                        <span class="flex items-center gap-2"><strong class="ui-title">ربط بمنتج موجود</strong><x-ui.help title="ربط بمنتج موجود" body="الخيار الافتراضي؛ اختر منتجًا محفوظًا دون إنشاء نسخة جديدة." /></span>
                    </label>
                    <label class="ui-card-muted p-4 flex items-start gap-3 cursor-pointer">
                        <input type="radio" name="product_action" value="create">
                        <span class="flex items-center gap-2"><strong class="ui-title">إنشاء منتج جديد</strong><x-ui.help title="إنشاء منتج جديد" body="يعرض بيانات المنتج ونوع البيع والوحدات المناسبة للحبة أو الطقم أو الرول." /></span>
                    </label>
                </div>
            </fieldset>

            <section id="ownerProductLinkFields" class="ui-card-muted p-4 space-y-3">
                <div class="flex items-center gap-2"><strong class="ui-title">ربط بمنتج موجود</strong><x-ui.help title="الربط بمنتج" body="ابحث بالاسم أو الوصف ثم اختر المنتج. إذا لم يكن المنتج محفوظًا، اختر إنشاء منتج جديد من أعلى النافذة." /></div>
                <label for="ownerExistingProductSearch" class="block ui-text-caption font-bold ui-text-soft">البحث في منتجات المتجر المحفوظة</label>
                <input type="search" id="ownerExistingProductSearch" autocomplete="off" class="ui-input" placeholder="اكتب اسم المنتج أو وصفه للربط...">
                <select id="ownerExistingProduct" name="existing_product_id" class="ui-input">
                    <option value="">اختر المنتج الذي تريد ربطه</option>
                    @foreach($products as $existingProduct)
                        <option value="{{ $existingProduct->id }}" data-search="{{ $existingProduct->name }} {{ $existingProduct->description }}">{{ $existingProduct->name }}</option>
                    @endforeach
                </select>
            </section>

            <div id="ownerProductCreateFields" class="hidden space-y-5">
            <section class="ui-card-muted p-4 space-y-4">
                <div class="flex items-center gap-2"><strong class="ui-title">1. البيانات الأساسية</strong><x-ui.help title="البيانات الأساسية" body="أدخل الاسم والاستخدام وشكل المنتج، ثم اختر القسم لمنتج البيع فقط." /></div>
            <label class="block">
                <span class="block ui-text-caption font-bold ui-text-muted mb-1.5">اسم المنتج</span>
                <input id="ownerProductName" name="name" required class="ui-input">
            </label>

            <fieldset class="ui-card-muted p-4 space-y-3">
                <legend class="ui-title font-bold px-2">أين تريد حفظ المنتج؟</legend>
                <label class="flex items-start gap-3">
                    <input type="radio" name="usage_type" value="sale" class="mt-1" checked>
                    <span class="flex items-center gap-2"><strong class="ui-title">منتج بيع</strong><x-ui.help title="منتج بيع" body="يظهر في البيع، وتضاف الكمية المستلمة إلى مخزونه عند الاعتماد المخزني." /></span>
                </label>
                <label class="flex items-start gap-3">
                    <input type="radio" name="usage_type" value="owner_purchase" class="mt-1">
                    <span class="flex items-center gap-2"><strong class="ui-title">مشتريات مالك</strong><x-ui.help variant="warning" title="مشتريات مالك" body="يسجل كمشتريات خاصة ولا يظهر ضمن منتجات البيع أو المخزون." /></span>
                </label>
            </fieldset>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <label class="block">
                    <span class="block ui-text-caption font-bold ui-text-soft mb-1.5">شكل المنتج ووحدة التخزين</span>
                    <select id="ownerProductUnitChoice" class="ui-input">
                        <option value="piece">حبة / وحدة مفردة</option>
                        <option value="kit">طقم يمكن بيعه طقمًا أو حبة</option>
                        <option value="roll">رول يُخزن ويباع بالمتر</option>
                    </select>
                </label>
                <label class="block">
                    <span class="block ui-text-caption font-bold ui-text-soft mb-1.5">حالة المنتج</span>
                    <select name="status" class="ui-input"><option value="active">مفعل</option><option value="inactive">غير مفعل</option></select>
                </label>
            </div>

            <label id="ownerProductSellingPriceField" class="hidden block">
                <span class="mb-1.5 flex items-center gap-2 ui-text-caption font-bold ui-text-soft"><span id="ownerProductSellingPriceLabel">سعر بيع الحبة</span><x-ui.help title="سعر البيع" body="هذا السعر يدخله المالك للعميل، وهو مستقل تمامًا عن تكلفة الاستلام الظاهرة في قسم التكلفة." /></span>
                <input id="ownerProductSellingPrice" name="selling_price" type="number" min="0.01" step="0.01" class="ui-input" placeholder="أدخل سعر البيع">
            </label>

            <div id="ownerProductSaleFields" class="hidden grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="block ui-text-caption font-bold ui-text-soft mb-1.5">الحد الأدنى للمخزون</span>
                    <input name="min_stock" type="number" min="0" step="0.01" value="0" class="ui-input">
                </label>
                <label class="block">
                    <span class="block ui-text-caption font-bold ui-text-soft mb-1.5">الباركود</span>
                    <input name="barcode" maxlength="100" class="ui-input" placeholder="اختياري">
                </label>
                <label id="ownerProductPiecePriceField" class="hidden block sm:col-span-2">
                    <span class="block ui-text-caption font-bold ui-text-soft mb-1.5">سعر بيع الحبة عند المنتج القابل للتجزئة</span>
                    <input name="piece_price" type="number" min="0" step="0.01" class="ui-input" placeholder="اختياري">
                </label>
                <label id="ownerProductCartonField" class="block">
                    <span class="mb-1.5 flex items-center gap-2 ui-text-caption font-bold ui-text-soft"><span>عدد الحبات داخل الكرتون</span><x-ui.help title="سعة الكرتون" body="حقل اختياري للحبة والطقم فقط. اتركه فارغًا إذا لم يكن المنتج يورد بالكرتون." /></span>
                    <input name="carton_qty" type="number" min="1" step="1" class="ui-input" placeholder="اختياري">
                </label>
                <label id="ownerProductWasteField" class="hidden block">
                    <span class="mb-1.5 flex items-center gap-2 ui-text-caption font-bold ui-text-soft"><span>نسبة هالك الرول/المتر</span><x-ui.help title="هالك الرول" body="يظهر للرول فقط، ويزيد مقدار الأمتار المخصومة عند بيع خيار بالمتر." /></span>
                    <input name="waste_percentage" type="number" min="0" max="100" step="0.01" value="0" class="ui-input">
                </label>
            </div>

            <div id="ownerProductCategorySection">
                <label id="ownerProductSaleCategoryField" class="block">
                    <span class="block ui-text-caption font-bold ui-text-muted mb-1.5">القسم</span>
                    <select id="ownerProductCategory" name="category_id" required class="ui-input" data-owner-category-id="{{ $ownerPurchaseCategoryId }}">
                        <option value="">اختر القسم</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) $ownerPurchaseCategoryId === (int) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div id="ownerProductOwnerCategory" class="hidden ui-card-muted p-4"><span class="ui-text-soft">القسم</span><strong class="ui-title block mt-1">مشتريات المالك</strong></div>
            </div>
            </section>

            <section class="ui-card-muted p-4 space-y-4">
            <div class="flex items-center gap-2"><strong class="ui-title">2. الوحدة والتكلفة</strong><x-ui.help title="الوحدة والتكلفة" body="التكلفة محسوبة من فاتورة الاستلام وليست سعر البيع. حدد هنا عدد حبات الطقم أو طول الرول عند الحاجة." /></div>
            <div class="ui-card-muted p-4 flex items-center justify-between gap-3">
                <span class="ui-text-muted">وحدة المنتج</span>
                <strong id="ownerProductUnitLabel" class="ui-title">حبة</strong>
                <input type="hidden" id="ownerProductUnit" name="owner_unit_type" value="piece">
            </div>

            <label id="ownerProductQuickSaleField" class="hidden block">
                <span class="block ui-text-caption font-bold ui-text-soft mb-1.5">نوع البيع الافتراضي</span>
                <select name="quick_sale_default_unit" class="ui-input">
                    <option value="unit">الوحدة الكاملة</option>
                    <option value="piece">الحبة</option>
                </select>
            </label>

            <div class="ui-card-muted p-4 space-y-2">
                <div class="flex items-center justify-between gap-3">
                    <span class="flex items-center gap-2 ui-text-muted">تكلفة الوحدة من الاستلام <x-ui.help title="تكلفة الاستلام" body="هذه تكلفة شراء الوحدة من المورد، وليست سعر البيع للعميل." /></span>
                    <strong id="ownerProductUnitCost" class="ui-title">0.00 ر.س</strong>
                </div>
                <div id="ownerProductKitFields" class="hidden space-y-2">
                    <label class="block">
                        <span class="mb-1.5 flex items-center gap-2 ui-text-caption font-bold ui-text-muted"><span>عدد حبات الطقم</span><x-ui.help title="عدد حبات الطقم" body="حقل إلزامي للطقم، ويستخدم لحساب تكلفة وسعر الحبة المفردة." /></span>
                        <input id="ownerProductItemsPerUnitInput" name="items_per_unit" type="number" min="2" class="ui-input" placeholder="أدخل عدد الحبات">
                    </label>
                </div>
                <div id="ownerProductRollFields" class="hidden flex items-center justify-between gap-3">
                    <span class="ui-text-muted">طول الرول</span>
                    <strong id="ownerProductRollLength" class="ui-title">-</strong>
                    <input id="ownerProductRollLengthInput" name="roll_length" type="number" step="0.01" min="0.01" class="ui-input hidden" placeholder="أدخل طول الرول">
                </div>
            </div>
            </section>

            <section id="ownerProductFractions" class="hidden ui-card-muted p-4 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3"><div class="flex items-center gap-2"><strong class="ui-title">3. خيارات بيع الرول بالمتر</strong><x-ui.help title="خيارات بيع الرول" body="لكل خيار أدخل اسمه، وعدد الأمتار المخصومة، وسعر البيع للعميل." /></div><button type="button" id="addOwnerProductFraction" class="ui-btn ui-btn-secondary">إضافة خيار بيع</button></div>
                <div id="ownerProductFractionsList" class="space-y-3"></div>
            </section>

            <section class="ui-card-muted p-4 space-y-4">
            <div><strong class="ui-title">4. بيانات إضافية</strong></div>
            <input type="hidden" id="ownerProductReceiptTotalCost" name="receipt_total_cost">
            <input type="hidden" id="ownerProductReceivedQuantity" name="received_quantity">

            <label class="block">
                <span class="block ui-text-caption font-bold ui-text-muted mb-1.5">وصف اختياري</span>
                <textarea name="description" rows="3" class="ui-input"></textarea>
            </label>
            <label class="block">
                <span class="block ui-text-caption font-bold ui-text-muted mb-1.5">صورة المنتج (اختياري)</span>
                <input type="file" name="image" accept="image/*" class="ui-input">
            </label>
            </section>
            </div>

            <div class="ui-section-divider-actions flex-col-reverse sm:flex-row">
                <button type="button" id="cancelOwnerProduct" class="ui-btn ui-btn-danger">إلغاء</button>
                <button type="submit" class="ui-btn ui-btn-primary"><span id="ownerProductSubmitText">ربط المنتج بالطلبية</span></button>
            </div>
        </form>
    </div>
</div>

@php
    $stockApprovalCostChanges = $order->status === 'received'
        ? $order->items->filter(fn ($item) => $item->update_product_cost)->map(function ($item) {
            $product = $item->product ?: $item->matchedProduct;
            $currentCost = (float) ($product?->cost_price ?? 0);
            $quantity = (float) ($item->quantity_received ?? 0);
            $receiptCost = (float) ($item->cost_price_at_receipt ?? 0);
            $newCost = $currentCost;

            if ($product && $quantity > 0 && $receiptCost > 0) {
                $unitReceiptCost = $receiptCost / $quantity;
                if (in_array($item->unit_type, ['meter', 'meters'], true) && (float) ($product->roll_length ?? 0) > 0) {
                    $newCost = round($unitReceiptCost * (float) $product->roll_length, 2);
                } elseif ($item->unit_type === 'piece' && (int) ($product->items_per_unit ?? 0) > 0) {
                    $newCost = round($unitReceiptCost * (int) $product->items_per_unit, 2);
                } else {
                    $newCost = round($unitReceiptCost, 2);
                }
            }

            return [
                'name' => $item->productName(),
                'current_cost' => $currentCost,
                'new_cost' => $newCost,
            ];
        })->values()
        : collect();
@endphp

@if($order->workflow_status === 'rejected' || ($order->status === 'draft' && !$inventoryReviewLocked && $order->inventory_review_status !== 'approved'))
<div class="ui-card p-5 space-y-4">
    <div class="flex items-center gap-2"><h2 class="ui-title text-xl font-black">رفض الطلبية</h2><x-ui.help title="رفض الطلبية" body="يسجل الرفض السبب ويوقف متابعة الطلبية مؤقتًا، ويمكن بعد ذلك إعادتها للمراجعة بدل البدء بطلبية جديدة." /></div>
    @if($order->workflow_status === 'rejected')
        <p class="ui-status-danger">{{ $order->rejection_reason }}</p>
        <form method="POST" action="{{ route('user.stores.purchase-orders.reopen', [$store->id, $order->id]) }}">@csrf<button class="ui-btn ui-btn-secondary">إعادة للمراجعة</button></form>
    @elseif($order->status === 'draft')
        <form method="POST" action="{{ route('user.stores.purchase-orders.reject', [$store->id, $order->id]) }}" class="space-y-3">
            @csrf
            <label class="ui-label">سبب الرفض<input maxlength="40" required name="rejection_reason" class="ui-input" value="{{ old('rejection_reason') }}"></label>
            @error('rejection_reason')<span class="ui-status-danger">{{ $message }}</span>@enderror
            <button class="ui-btn ui-btn-danger">رفض الطلبية</button>
        </form>
    @endif
</div>
@endif

@if($order->status === 'draft' && !$inventoryReviewLocked && $order->inventory_review_status !== 'approved')
<div class="ui-card p-5 space-y-4">
    <div class="flex items-center gap-2"><h2 class="ui-title text-xl font-black">إلغاء الطلبية</h2><x-ui.help title="إلغاء الطلبية" body="يوقف الإلغاء العمل على الطلبية ويحولها إلى طلبية ملغية. استخدمه عندما لم تعد الطلبية مطلوبة، وليس عند الحاجة إلى تصحيحها." /></div>
    <p class="ui-text-soft">إذا كانت الطلبية تحتاج تعديلًا فقط، استخدم بطاقة إعادة للمراجعة والتعديل بدل الإلغاء.</p>
    <form method="POST" action="{{ route('user.stores.purchase-orders.cancel', [$store->id, $order->id]) }}" data-ui-confirm="هل تريد إلغاء هذه الطلبية حقاً؟" data-ui-confirm-title="تأكيد إلغاء الطلبية">
        @csrf
        <button class="ui-btn ui-btn-danger">إلغاء الطلبية</button>
    </form>
</div>
@endif

@if(in_array($order->status, ['cancelled', 'approved'], true))
<div class="ui-card p-5 space-y-4">
    <div class="flex items-center gap-2"><h2 class="ui-title">حذف الطلبية</h2><x-ui.help variant="warning" title="حذف الطلبية" body="يخفي الطلبية من القائمة. لا تتراجع كميات التوريد، ولا تحذف المنتجات أو حركات المخزون." /></div>
    <form method="POST" action="{{ route('user.stores.purchase-orders.destroy', [$store->id, $order->id]) }}" data-ui-confirm="هل تريد حذف {{ $orderDisplayName }} من القائمة؟ لن تتغير المنتجات أو الكميات الموردة." data-ui-confirm-title="حذف الطلبية؟">
        @csrf @method('DELETE')
        <input type="hidden" name="confirmation" value="{{ $order->id }}">
        <button class="ui-btn ui-btn-danger">حذف الطلبية</button>
    </form>
</div>
@endif





<div class="hidden" data-purchase-order-show-config="{{ json_encode([
    'stockApprovalCostChanges' => $stockApprovalCostChanges,
    'orderStatus' => $order->status,
    'draftKey' => 'purchase-order-draft:' . auth()->id() . ':' . $store->id,
    'clearDraft' => session('success') === 'تم تجهيز الطلبية كمسودة. راجعها ثم اضغط اعتماد الطلبية لإرسالها للمورد.',
], JSON_HEX_APOS | JSON_HEX_QUOT) }}" aria-hidden="true"></div>
@endif
@endsection
