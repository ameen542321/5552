@props(['store', 'stores', 'products', 'action', 'backUrl', 'title', 'currentBusinessDate'])

@php
    $productRows = $products->map(fn ($product) => [
        'id' => (string) $product->id,
        'name' => $product->name,
        'quantity' => (float) $product->quantity,
        'product_type' => $product->product_type,
        'is_splittable' => (bool) $product->is_splittable,
    ])->values();
    $oldItems = collect(old('items', []))->map(fn ($item) => [
        'sender_product_id' => (string) ($item['sender_product_id'] ?? ''),
        'quantity' => $item['quantity'] ?? '',
        'unit_type' => $item['unit_type'] ?? 'unit',
    ])->values();
@endphp

<div class="max-w-5xl mx-auto p-4 sm:p-6 space-y-6" dir="rtl"
     data-store-transfer-system
     x-data="storeTransferBuilder(@js($productRows), @js($oldItems))">
    <div class="ui-card p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-black ui-title">{{ $title }}</h1>
                    <x-ui.help variant="warning" title="كيف يعمل النقل؟" body="اختر تاريخًا من الشهر الحالي. عند إرسال الطلب تنقص الكمية من المتجر المرسل وتظهر في سجل إدارة المخزون، وعند قبول الطلب تضاف الكمية إلى المتجر المستلم بالتاريخ الذي يختاره المستلم." />
                </div>
            </div>
        </div>
        <a href="{{ $backUrl }}" class="ui-btn ui-btn-secondary px-4 py-2">رجوع</a>
    </div>

    @if ($errors->any())
        <div class="ui-status-danger-bg border ui-status-danger-border rounded-xl p-4 ui-status-danger">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $action }}" class="ui-card p-4 sm:p-6 space-y-5">
        @csrf
        <div>
            <label class="block ui-text-soft font-bold mb-2">اختر التاريخ</label>
            <input type="date" name="business_date" value="{{ old('business_date', $currentBusinessDate) }}" min="{{ now()->startOfMonth()->toDateString() }}" max="{{ now()->endOfMonth()->toDateString() }}" required class="ui-input px-4 py-3">
            <p class="ui-text-caption ui-text-muted mt-2">متاح اختيار تاريخ من الشهر الحالي فقط.</p>
        </div>
        <div>
            <label class="block ui-text-soft font-bold mb-2">المتجر المستلم</label>
            <select name="receiver_store_id" required class="ui-input px-4 py-3">
                <option value="">اختر المتجر المستلم</option>
                @foreach($stores as $receiverStore)
                    <option value="{{ $receiverStore->id }}" @selected(old('receiver_store_id') == $receiverStore->id)>{{ $receiverStore->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center justify-between gap-3">
            <h2 class="ui-title font-bold">بنود النقل</h2>
            <button type="button" class="ui-btn ui-btn-info px-4 py-2 ui-text-caption" @click="addItem()">
                <i class="fa-solid fa-plus"></i> إضافة منتج
            </button>
        </div>

        <div class="space-y-3">
            <template x-for="(item, index) in items" :key="item.key">
                <div class="ui-card-muted p-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    <div class="md:col-span-6 relative">
                        <label class="block ui-text-caption ui-text-soft font-bold mb-2">المنتج</label>
                        <input type="hidden" :name="`items[${index}][sender_product_id]`" x-model="item.sender_product_id">
                        <input type="text" x-model="item.query" @focus="item.open = true" @input="item.sender_product_id = ''; item.open = true"
                               autocomplete="off" placeholder="ابحث باسم المنتج..." class="ui-input px-4 py-3" required>
                        <div x-show="item.open" @click.outside="item.open = false" x-cloak class="absolute z-50 mt-2 w-full max-h-64 overflow-y-auto rounded-xl border ui-border ui-surface-strong-bg shadow-2xl">
                            <template x-for="product in filteredProducts(item)" :key="product.id">
                                <button type="button" class="block w-full px-4 py-3 text-right ui-text-caption ui-title ui-hover-surface"
                                        @click="selectProduct(item, product)">
                                    <span x-text="product.name"></span>
                                    <span class="ui-text-muted mr-2" x-text="`المتوفر: ${product.quantity}`"></span>
                                </button>
                            </template>
                            <p x-show="filteredProducts(item).length === 0" class="p-4 ui-text-caption ui-text-muted">لا توجد نتائج.</p>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block ui-text-caption ui-text-soft font-bold mb-2">الكمية</label>
                        <input type="number" :name="`items[${index}][quantity]`" x-model="item.quantity" step="0.001" min="0.001" required class="ui-input px-3 py-3">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block ui-text-caption ui-text-soft font-bold mb-2">الوحدة</label>
                        <select :name="`items[${index}][unit_type]`" x-model="item.unit_type" required class="ui-input px-3 py-3">
                            <template x-for="unit in unitsFor(item)" :key="unit.value">
                                <option :value="unit.value" x-text="unit.label"></option>
                            </template>
                        </select>
                    </div>
                    <div class="md:col-span-1">
                        <button type="button" class="ui-btn ui-btn-danger w-full py-3" @click="removeItem(index)" :disabled="items.length === 1" aria-label="حذف المنتج">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <div>
            <label class="block ui-text-soft font-bold mb-2">ملاحظات</label>
            <textarea name="notes" rows="3" class="ui-input px-4 py-3" placeholder="اختياري">{{ old('notes') }}</textarea>
        </div>

        <button class="ui-btn ui-btn-primary w-full py-3">إرسال طلب النقل</button>
    </form>
</div>
