@extends('dashboard.app')

@section('title', 'تعديل فاتورة #' . $invoice->invoice_number)

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8 space-y-6 text-right" dir="rtl">
    @php
        $isOwnerContext = isset($store);

        $backUrl = $isOwnerContext
            ? route('user.stores.invoices.show', [$store->id, $invoice->id])
            : route('accountant.invoices.show', $invoice->id);

        $updateUrl = $isOwnerContext
            ? route('user.stores.invoices.update', [$store->id, $invoice->id])
            : route('accountant.invoices.update', $invoice->id);

        $descriptionText = (string) ($invoice->description ?? '');
        $serviceLinesFromDescription = collect(preg_split('/\r\n|\r|\n/', $descriptionText))
            ->map(fn($line) => trim((string) $line))
            ->filter(fn($line) => str_starts_with($line, '-'))
            ->map(function ($line) {
                $line = trim((string) preg_replace('/^-\s*/u', '', $line));
                preg_match('/^(.*?)\s*\(الكمية:\s*([0-9.,]+)\s*×\s*السعر:\s*([0-9.,]+)/u', $line, $matches);

                return [
                    'name' => trim((string) ($matches[1] ?? $line)),
                    'qty' => (float) str_replace(',', '', (string) ($matches[2] ?? 1)),
                    'price' => isset($matches[3]) ? (float) str_replace(',', '', (string) $matches[3]) : '',
                ];
            })
            ->values();

        $serviceLines = old('service_lines', $serviceLinesFromDescription->pluck('name')->all() ?: ['']);
        $serviceQtys = old('service_qtys', $serviceLinesFromDescription->pluck('qty')->all() ?: [1]);
        $serviceValues = old('service_values', $serviceLinesFromDescription->pluck('price')->all() ?: ['']);
    @endphp

    <div class="ui-card p-5 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl md:text-2xl font-black ui-title">تعديل الفاتورة <span class="font-mono ui-status-warning">#{{ $invoice->invoice_number }}</span></h1>
            <p class="ui-text-caption ui-text-muted mt-1">يمكنك تحديث بيانات العميل والحالة من هذه الصفحة.</p>
        </div>

        <a href="{{ $backUrl }}" class="ui-btn ui-btn-secondary px-4 py-2 text-sm">
            رجوع للتفاصيل
        </a>
    </div>

    <div class="ui-card p-6">
        <form method="POST" action="{{ $updateUrl }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf
            @method('PUT')

            <div>
                <label class="ui-text-muted ui-text-caption block mb-1">اسم العميل</label>
                <input type="text" name="customer_name" value="{{ old('customer_name', $invoice->customer_name) }}" class="w-full ui-card px-3 py-2 ui-title">
            </div>

            <div>
                <label class="ui-text-muted ui-text-caption block mb-1">الهاتف</label>
                <input type="text" name="customer_phone" value="{{ old('customer_phone', $invoice->customer_phone) }}" class="w-full ui-card px-3 py-2 ui-title">
            </div>

            <div>
                <label class="ui-text-muted ui-text-caption block mb-1">نوع المركبة</label>
                <input type="text" name="vehicle_type" value="{{ old('vehicle_type', $invoice->vehicle_type) }}" class="w-full ui-card px-3 py-2 ui-title">
            </div>

            <div>
                <label class="ui-text-muted ui-text-caption block mb-1">رقم اللوحة</label>
                <input type="text" name="plate_number" value="{{ old('plate_number', $invoice->plate_number) }}" class="w-full ui-card px-3 py-2 ui-title">
            </div>

            <div>
                <label class="ui-text-muted ui-text-caption block mb-1">الرقم الضريبي</label>
                <input type="text" name="tax_number" value="{{ old('tax_number', $invoice->tax_number) }}" class="w-full ui-card px-3 py-2 ui-title">
            </div>

            <div>
                <label class="ui-text-muted ui-text-caption block mb-1">الحالة</label>
                <select name="status" class="w-full ui-card px-3 py-2 ui-title">
                    @foreach(['paid' => 'مدفوعة', 'pending' => 'معلقة', 'printed' => 'مطبوعة', 'canceled' => 'ملغاة'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $invoice->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="ui-text-muted ui-text-caption block mb-1">ملاحظات</label>
                <textarea name="notes" rows="4" class="w-full ui-card px-3 py-2 ui-title">{{ old('notes', $invoice->notes) }}</textarea>
            </div>

            <div class="md:col-span-2 ui-card p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <label class="ui-text-muted text-sm font-black">المنتجات</label>
                    <button type="button" id="add-service-line"
                            class="ui-btn ui-btn-secondary px-3 py-2 ui-text-caption">
                        + إضافة صف
                    </button>
                </div>

                <div class="hidden md:grid md:grid-cols-12 gap-2 ui-text-caption ui-text-muted px-1">
                    <div class="md:col-span-5">المنتج</div>
                    <div class="md:col-span-2 text-center">الكمية</div>
                    <div class="md:col-span-2 text-center">السعر</div>
                    <div class="md:col-span-2 text-center">الإجمالي</div>
                    <div class="md:col-span-1 text-center">حذف</div>
                </div>

                <div id="service-lines-list" class="space-y-2">
                    @foreach($serviceLines as $index => $line)
                        <div class="grid grid-cols-12 gap-2 items-center service-line-row">
                            <div class="col-span-12 md:col-span-5">
                                <input type="text" name="service_lines[]" value="{{ $line }}"
                                       class="w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title text-sm"
                                       placeholder="اسم المنتج / الخدمة">
                            </div>
                            <div class="col-span-4 md:col-span-2">
                                <input type="number" step="1" min="0" name="service_qtys[]" value="{{ $serviceQtys[$index] ?? 1 }}"
                                       class="w-full ui-surface-muted-bg border ui-border rounded-lg px-2 py-2 ui-title text-sm text-center service-qty-input"
                                       placeholder="الكمية">
                            </div>
                            <div class="col-span-4 md:col-span-2">
                                <input type="number" step="0.01" min="0" name="service_values[]" value="{{ $serviceValues[$index] ?? '' }}"
                                       class="w-full ui-surface-muted-bg border ui-border rounded-lg px-2 py-2 ui-title text-sm text-center service-value-input"
                                       placeholder="السعر">
                            </div>
                            <div class="col-span-3 md:col-span-2">
                                <input type="number" step="0.01" min="0" name="service_totals[]" value="{{ (float) ($serviceQtys[$index] ?? 1) * (float) ($serviceValues[$index] ?? 0) }}"
                                       class="w-full ui-surface-muted-bg border ui-border rounded-lg px-2 py-2 ui-status-success text-sm text-center service-total-input"
                                       placeholder="الإجمالي" readonly>
                            </div>
                            <div class="col-span-1 md:col-span-1">
                                <button type="button"
                                        class="remove-service-line ui-btn ui-btn-danger w-full h-[38px] ui-text-caption" aria-label="حذف السطر">✕</button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="ui-text-caption ui-text-muted">يمكنك إضافة/تعديل صفوف المنتجات أو الخدمات مع الكمية والسعر والإجمالي.</p>
            </div>

            <div class="md:col-span-2">
                <label class="ui-text-muted ui-text-caption block mb-1">وصف إضافي (اختياري)</label>
                <textarea name="description" rows="3" class="w-full ui-card px-3 py-2 ui-title">{{ old('description') }}</textarea>
            </div>

            <div>
                <label class="ui-text-muted ui-text-caption block mb-1">المجموع قبل الضريبة</label>
                <input type="number" step="0.01" min="0" name="subtotal" value="{{ old('subtotal', $invoice->subtotal) }}" class="w-full ui-card px-3 py-2 ui-title">
            </div>

            <div>
                <label class="ui-text-muted ui-text-caption block mb-1">نسبة الضريبة (%)</label>
                <input type="number" step="0.01" min="0" name="tax_rate" value="{{ old('tax_rate', $invoice->tax_rate ?? optional($invoice->sale)->tax_rate) }}" class="w-full ui-card px-3 py-2 ui-title">
            </div>

            <div class="md:col-span-2">
                <div class="ui-text-muted text-sm font-black mb-3">المنتجات</div>

                @if(($invoice->sale->items ?? collect())->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($invoice->sale->items as $index => $item)
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 ui-card p-3">
                                <input type="hidden" name="item_ids[]" value="{{ $item->id }}">

                                <div class="md:col-span-6">
                                    <label class="ui-text-muted ui-text-caption block mb-1">اسم المنتج</label>
                                    <input type="text" value="{{ optional($item->product)->name ?: ($item->custom_name ?: 'منتج') }}" class="w-full ui-card px-3 py-2 ui-text-muted" readonly>
                                </div>

                                <div class="md:col-span-3">
                                    <label class="ui-text-muted ui-text-caption block mb-1">الكمية</label>
                                    <input type="number" step="0.01" min="0" name="item_quantities[]" value="{{ old('item_quantities.' . $index, $item->quantity) }}" class="w-full ui-card px-3 py-2 ui-title">
                                </div>

                                <div class="md:col-span-3">
                                    <label class="ui-text-muted ui-text-caption block mb-1">السعر</label>
                                    <input type="number" step="0.01" min="0" name="item_prices[]" value="{{ old('item_prices.' . $index, $item->price) }}" class="w-full ui-card px-3 py-2 ui-title">
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="ui-card p-4 text-sm ui-text-muted">
                        لا توجد منتجات مرتبطة بهذه الفاتورة. يمكنك تعديل وصف العمل والمبالغ من الحقول أعلاه.
                    </div>
                @endif
            </div>

            <div class="md:col-span-2 flex justify-end gap-3">
                <a href="{{ $backUrl }}" class="ui-btn ui-btn-danger px-5 py-2.5">إلغاء</a>
                <button type="submit" class="ui-btn ui-btn-primary px-5 py-2.5">حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>

{{-- علامة واجهة فقط لتفعيل وحدة تعديل الفاتورة المركزية. --}}
<div class="hidden" data-invoice-edit-interface aria-hidden="true"></div>
@endsection
