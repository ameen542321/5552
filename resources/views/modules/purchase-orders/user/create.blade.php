@extends('dashboard.app')
@section('title', isset($order) ? 'تعديل طلبية توريد' : 'إرسال طلبية توريد')
@section('content')
@php
    $isEdit = isset($order);
    $isAccountantContext = ($purchaseOrderContext ?? null) === 'accountant';
    $formAction = $isAccountantContext
        ? ($isEdit ? route('accountant.purchase-orders.update', $order->id) : route('accountant.purchase-orders.store'))
        : ($isEdit ? route('user.stores.purchase-orders.update', [$store->id, $order->id]) : route('user.stores.purchase-orders.store', $store->id));
    $backRoute = $isAccountantContext
        ? ($isEdit ? route('accountant.purchase-orders.show', $order->id) : route('accountant.purchase-orders.index'))
        : route('user.stores.purchase-orders.index', $store->id);
    $draftActorId = $isAccountantContext ? ('accountant:' . auth('accountant')->id()) : ('user:' . auth()->id());
    $productOptions = $products->map(function ($product) use ($store, $isAccountantContext) {
        $unitOptions = [];
        if (($product->product_type ?? null) === 'fractional' || (float) $product->roll_length > 0) {
            $unitOptions = [['value' => 'roll', 'label' => 'رول'], ['value' => 'meter', 'label' => 'متر']];
        } elseif ($product->is_splittable) {
            $unitOptions = [['value' => 'kit', 'label' => 'طقم'], ['value' => 'piece', 'label' => 'حبة']];
        }

        $inventoryAudit = $isAccountantContext ? ['color' => null] : $product->inventoryAuditStatus($store);
        $inventoryAuditDot = $isAccountantContext
            ? ['class' => 'hidden', 'label' => '']
            : ([
                'red' => ['class' => 'ui-dot-danger', 'label' => 'غير مكتمل'],
                'yellow' => ['class' => 'ui-dot-warning', 'label' => 'غير مؤكد'],
                'green' => ['class' => 'ui-dot-success', 'label' => 'مؤكد'],
            ][$inventoryAudit['color']] ?? ['class' => 'ui-surface-muted-bg', 'label' => 'غير معروف']);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'carton_qty' => (int) ($product->carton_qty ?? 0),
            'is_splittable' => (bool) $product->is_splittable,
            'quantity' => $isAccountantContext ? null : (float) $product->quantity,
            'description' => (string) ($product->description ?? ''),
            'cost_price' => $isAccountantContext ? null : (float) ($product->cost_price ?? 0),
            'price' => $isAccountantContext ? null : (float) ($product->price ?? $product->selling_price ?? 0),
            'unit_options' => $unitOptions,
            'default_unit' => (string) ($product->quick_sale_default_unit ?: ($unitOptions[0]['value'] ?? 'unit')),
            'is_owner_purchase' => $product->isOwnerPurchaseOnly(),
            'audit_color' => $inventoryAudit['color'] ?? null,
            'audit_label' => $inventoryAuditDot['label'],
            'audit_dot_class' => $inventoryAuditDot['class'],
        ];
    })->values();

    $existingProductRows = $isEdit ? $order->items->whereNotNull('product_id')->map(function ($item) {
        return [
            'product_id' => $item->product_id,
            'quantity_requested' => (float) $item->quantity_requested,
            'unit_type' => $item->unit_type ?: 'unit',
            'receipt_notes' => (string) ($item->receipt_notes ?? ''),
        ];
    })->values() : collect();

    $existingCustomRows = $isEdit ? $order->items->whereNull('product_id')->map(function ($item) {
        return [
            'custom_product_name' => (string) $item->custom_product_name,
            'quantity_requested' => (float) $item->quantity_requested,
            'unit_type' => $item->unit_type ?: 'unit',
            'items_per_unit' => $item->items_per_unit,
            'roll_length' => $item->roll_length,
            'receipt_notes' => (string) ($item->receipt_notes ?? ''),
            'cost_price_at_order' => (float) ($item->cost_price_at_order ?? 0),
            'add_to_owner_purchases' => (bool) ($item->add_to_owner_purchases ?? false),
        ];
    })->values() : collect();
@endphp

<div class="max-w-5xl mx-auto p-4 md:p-6 space-y-4" dir="rtl">
    <form method="POST" action="{{ $formAction }}" class="space-y-4" id="purchaseOrderForm">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="flex items-center justify-between gap-3 flex-wrap ui-card p-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-black ui-title">{{ $isEdit ? 'تعديل الطلبية' : 'إنشاء طلبية توريد' }}</h1>
                    <x-ui.help :title="$isEdit ? 'تعديل الطلبية' : 'إنشاء طلبية'" :body="$isEdit ? 'راجع البنود والكميات، ثم احفظ تعديلاتك.' : 'اختر المنتجات وحدد الكمية المطلوبة لكل منتج.'" />
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ $backRoute }}" class="ui-btn ui-btn-secondary">رجوع</a>
                <button type="submit" class="ui-btn ui-btn-primary px-6 py-2.5">
                    {{ $isEdit ? 'حفظ التعديلات' : 'حفظ الطلبية' }}
                </button>
            </div>
        </div>

        @if(!$isAccountantContext && !$isEdit)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 ui-card p-4">
                <div>
                    <label class="block ui-text-muted text-sm mb-1">اسم المورد / المندوب <span class="ui-status-danger">(إجباري)</span></label>
                    <input name="supplier_name" required value="{{ old('supplier_name', $order->supplier_name ?? '') }}" placeholder="اسم المورد أو الموزع" class="w-full rounded-lg ui-surface-muted-bg border ui-border ui-title px-3 py-2 text-sm focus:outline-none ">
                </div>
                <div>
                    <label class="block ui-text-muted text-sm mb-1">ملاحظة داخلية <span class="ui-text-muted">(اختياري)</span></label>
                    <input maxlength="40" name="notes" value="{{ old('notes', $order->notes ?? '') }}" placeholder="لا تظهر للمورد" class="w-full rounded-lg ui-surface-muted-bg border ui-border ui-title px-3 py-2 text-sm focus:outline-none ">
                </div>
            </div>
        @endif

        <div class="ui-card p-4 space-y-4">
            <div class="flex flex-col gap-3">
                @unless($isAccountantContext)
                    <div class="flex flex-wrap items-center justify-end gap-2 ui-text-caption ui-text-muted" dir="rtl">
                        <span class="ui-text-muted">حالة الجرد:</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full ui-dot-success"></span>مؤكد</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full ui-dot-warning"></span>غير مؤكد</span>
                        <span class="inline-flex items-center gap-1"><span class="inline-block w-2 h-2 rounded-full ui-dot-danger"></span>غير مكتمل</span>
                    </div>
                @endunless
                <div class="flex flex-col md:flex-row gap-3">
                    <div class="relative flex-1" id="productPicker">
                        <label for="productPickerInput" class="mb-1 block ui-text-caption font-bold ui-text-soft">البحث في منتجات المتجر لإضافة منتج</label>
                        <input type="search" id="productPickerInput" autocomplete="off" placeholder="اكتب اسم المنتج أو وصفه لإضافته..." class="ui-input w-full font-bold">
                        <div id="productPickerMenu" class="hidden absolute z-30 mt-2 w-full max-h-72 overflow-y-auto rounded-xl border ui-border ui-surface-muted-bg shadow-2xl"></div>
                        <button type="button" id="draftRestoreNotice" class="hidden mt-2 w-full rounded-lg border ui-border ui-status-info-bg px-3 py-2 text-right ui-text-caption font-bold ui-status-info">
                            توجد بيانات مخزنة سابقًا، هل تريد استعادتها؟ اضغط هنا
                        </button>
                    </div>
                    <button type="button" id="addCustom" class="px-5 py-3 rounded-xl ui-status-warning-bg ui-title font-bold transition whitespace-nowrap flex items-center gap-2">
                        <span>+ إضافة منتج مخصص</span>
                    </button>
                </div>
            </div>

            <div class="rounded-xl border ui-border ui-surface-muted-bg p-3">
                <label for="orderRowsSearch" class="mb-1 block ui-text-caption font-bold ui-text-muted">البحث داخل بنود الطلبية الحالية فقط</label>
                <input type="search" id="orderRowsSearch" autocomplete="off" placeholder="صفِّ البنود المضافة بالاسم أو الملاحظة..." class="w-full rounded-lg border ui-border ui-surface-muted-bg px-3 py-2 text-sm font-bold ui-title placeholder-gray-500  focus:outline-none">
                <p id="orderRowsSearchCount" class="mt-2 ui-text-caption ui-text-muted"></p>
            </div>

            <div id="orderItemsList" class="space-y-2"></div>
        </div>
    </form>
</div>


{{-- عقد إعداد منشئ الطلبية؛ لا يغير حقول البنود أو المسودة أو مسار الحفظ. --}}
<div class="hidden" data-purchase-order-form-config="{{ json_encode([
    'products' => $productOptions,
    'existingProductRows' => $existingProductRows,
    'existingCustomRows' => $existingCustomRows,
    'isEdit' => $isEdit,
    'hasServerErrors' => $errors->any(),
    'serverError' => $errors->first(),
    'draftKey' => 'purchase-order-draft:' . $draftActorId . ':' . $store->id,
    'hideInventoryValues' => $isAccountantContext,
    'skipConfirmation' => $isAccountantContext,
], JSON_HEX_APOS | JSON_HEX_QUOT) }}" aria-hidden="true"></div>
@endsection
