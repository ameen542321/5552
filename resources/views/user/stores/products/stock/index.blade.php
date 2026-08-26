@extends('dashboard.app')

@section('title', 'إدارة مخزون المنتج – ' . $product->name)

@section('content')

<div class="max-w-5xl mx-auto py-10 px-4 sm:px-6" data-inventory-system>

    {{-- الهيدر --}}
    <div class="flex items-center justify-between mb-8 gap-4">
        <a href="{{ request('return_to') === 'audit' ? route('user.stores.products.audit', $store->id) : route('user.stores.products.index', $store->id) }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg ui-surface-muted-bg border ui-border ui-text-muted ui-hover-info transition shadow-sm">
            <i class="fa-solid fa-arrow-right text-sm"></i>
            <span class="text-sm font-medium">رجوع</span>
        </a>

        <div class="text-center flex-1">
            <div class="flex flex-wrap items-center justify-center gap-2">
                <h1 class="text-xl md:text-2xl font-bold ui-title">إدارة مخزون المنتج</h1>
                @if(($inventoryAuditStatus['color'] ?? null) === 'green' && ($inventoryAuditStatus['confirmed_at'] ?? null))
                    <x-ui.badge variant="success" title="تم تأكيد جرد المنتج في الدورة الحالية">
                        <span class="flex flex-col items-center leading-tight">
                            <span><i class="fa-solid fa-circle-check" aria-hidden="true"></i> تم الجرد</span>
                            <span class="text-[0.625rem] font-normal">{{ $inventoryAuditStatus['confirmed_at']->format('Y-m-d') }}</span>
                        </span>
                    </x-ui.badge>
                @endif
            </div>
            <p class="ui-status-info ui-text-caption md:text-sm font-medium mt-1">
                {{ $product->name }}
                <span class="ui-text-muted mx-2">|</span>
                @if($product->product_type === 'fractional')
                    <i class="fa-solid fa-scissors ui-text-caption ml-1"></i> منتج مجزأ (أمتار)
                @elseif($product->is_splittable)
                    <i class="fa-solid fa-boxes-stacked ui-text-caption ml-1"></i> نظام أطقم (قابل للتجزئة)
                @else
                    <i class="fa-solid fa-box ui-text-caption ml-1"></i> منتج عادي
                @endif
            </p>
        </div>

        <div class="hidden md:block w-32"></div>
    </div>

    @php
        $isFractional = ($product->product_type === 'fractional' && $product->roll_length > 0);
        $isSet = $product->is_splittable;

        if ($isFractional) {
            $displayQuantity = $product->quantity / $product->roll_length;
            $displayMinStock = $product->min_stock;
            $unitLabel = 'رول';
        } elseif ($isSet) {
            $displayQuantity = $product->quantity;
            $displayMinStock = $product->min_stock;
            $unitLabel = 'طقم';
        } else {
            $displayQuantity = $product->quantity;
            $displayMinStock = $product->min_stock;
            $unitLabel = 'حبة';
        }
        $defaultSaleUnitLabel = \App\Support\ProductQuantityFormatter::inventoryDefaultUnit($product);
        $minimumStockLabel = \App\Support\ProductQuantityFormatter::minimumStock(
            $product,
            $isSet && $product->quick_sale_default_unit === 'piece' ? 'piece' : 'unit'
        );
        $isLowStock = $displayQuantity <= $displayMinStock;
        $rawStoredQuantity = (float) ($product->getRawOriginal('quantity') ?? $product->quantity);
        $currentQuantityUnitLabel = $defaultSaleUnitLabel;
        $currentQuantityValue = $isSet && $product->quick_sale_default_unit === 'piece'
            ? round($rawStoredQuantity * (int) $product->items_per_unit)
            : $rawStoredQuantity;
        $currentQuantityLabel = \App\Support\ProductQuantityFormatter::storedNumber($currentQuantityValue);
    @endphp

    {{-- بطاقات المعلومات السريعة --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
        {{-- الرصيد الحالي --}}
        <div class="ui-surface-muted-bg border ui-border p-6 rounded-2xl shadow-lg relative overflow-hidden">
            <div class="relative z-10 text-center">
                <p class="ui-text-muted ui-text-caption uppercase font-bold tracking-widest mb-2">الكمية الحالية ({{ $currentQuantityUnitLabel }})</p>
                <p class="text-4xl font-black ui-title">{{ $currentQuantityLabel }}</p>
                @if($isSet)
                    <div class="mt-3 flex flex-col items-center justify-center gap-2">
                        <span class="ui-text-caption ui-text-soft font-bold">
                            الطقم = {{ number_format($product->items_per_unit, 0) }} حبة
                        </span>
                    </div>
                @elseif($isFractional)
                    <div class="mt-3 flex items-center justify-center">
                         <span class="ui-status-info font-bold">إجمالي الرصيد: {{ number_format($product->quantity, 2) }} متر</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- حد التنبيه --}}
        <div class="ui-surface-muted-bg border ui-border p-6 rounded-2xl shadow-lg text-center">
            <p class="ui-text-muted ui-text-caption uppercase font-bold tracking-widest mb-2">الحد الأدنى للمخزون</p>
            <p class="text-4xl font-black ui-text-muted">{{ $minimumStockLabel }}</p>
            <p class="ui-text-caption ui-text-soft mt-2 font-bold">نوع البيع الافتراضي: <span class="ui-status-info">{{ $defaultSaleUnitLabel }}</span></p>
        </div>

        {{-- الحالة --}}
        <div class="ui-surface-muted-bg border ui-border p-6 rounded-2xl shadow-lg flex flex-col items-center justify-center">
            <p class="ui-text-muted ui-text-caption uppercase font-bold tracking-widest mb-3">حالة المستودع</p>
            @if($isLowStock)
                <div class="ui-status-danger-bg border ui-border ui-status-danger px-4 py-2 rounded-full flex items-center gap-2 font-bold text-sm">
                    <span class="w-2 h-2 ui-status-danger-bg rounded-full animate-ping"></span>
                    مخزون منخفض
                </div>
            @else
                <div class="ui-status-success-bg border ui-border ui-status-success px-4 py-2 rounded-full flex items-center gap-2 font-bold text-sm">
                    <i class="fa-solid fa-check-circle"></i>
                    مستوى آمن
                </div>
            @endif
        </div>

        {{-- أزرار الجرد وملاحظته دون تكرار نص الحالة الموجود في صفحة الجرد. --}}
        @php
            $auditButtonClass = match ($inventoryAuditStatus['color']) {
                'yellow' => 'ui-status-warning-bg ui-title',
                'green' => 'ui-btn ui-btn-success',
                default => 'ui-surface-muted-bg ui-text-muted cursor-not-allowed',
            };
        @endphp
        <div class="ui-surface-muted-bg border ui-border p-6 rounded-2xl shadow-lg flex flex-col justify-between gap-4">
            <div class="text-center">
                @if($inventoryAuditStatus['confirmed_at'] ?? null)
                    <p class="ui-text-caption ui-status-success mt-2">
                        آخر تأكيد في هذه الدورة: {{ $inventoryAuditStatus['confirmed_at']->format('Y-m-d') }}
                    </p>
                @elseif($latestInventoryAudit)
                    <p class="ui-text-caption ui-text-muted mt-2">
                        آخر جرد سابق: {{ optional($latestInventoryAudit->business_date)->format('Y-m-d') ?: $latestInventoryAudit->created_at->format('Y-m-d') }}
                    </p>
                @endif
            </div>

            @if($canConfirmAudit)
                <form action="{{ route('user.stores.products.stock.audit-confirm', [$store->id, $product->id]) }}" method="POST" data-confirm-submit data-confirm-title="تأكيد جرد المنتج" data-confirm-text="سيتم تسجيل تأكيد جرد لهذا المنتج في الدورة الحالية. هل تريد المتابعة؟" data-confirm-icon="warning">
                    @csrf
                    <label class="mb-3 block">
                        <span class="mb-1 block ui-text-caption font-bold ui-title">تاريخ الجرد</span>
                        <input type="date" name="business_date" value="{{ old('business_date', $currentBusinessDate) }}" required class="ui-input">
                    </label>
                    <textarea name="audit_note" rows="2" maxlength="255" class="ui-input mb-3" placeholder="ملاحظات تأكيد الجرد (اختياري)"></textarea>
                    <button type="submit" class="w-full {{ $auditButtonClass }} font-black py-3 rounded-xl transition-all active:scale-[0.98]">
                        تأكيد جرد المنتج
                    </button>
                </form>
            @else
                <button type="button" disabled class="w-full {{ $auditButtonClass }} font-black py-3 rounded-xl">
                    {{ $inventoryAuditStatus['can_confirm'] ? 'تم تأكيد الجرد هذا الشهر' : 'أكمل البيانات أولاً' }}
                </button>
            @endif
            @if($canCancelAudit)
                <form action="{{ route('user.stores.products.stock.audit-confirm.cancel', [$store->id, $product->id]) }}" method="POST" data-ui-confirm="سيتم إلغاء آخر تأكيد جرد مسموح في الفترة الحالية." data-ui-confirm-title="إلغاء تأكيد الجرد">
                    @csrf @method('DELETE')
                    <button class="ui-btn ui-btn-danger w-full">إلغاء تأكيد الجرد</button>
                </form>
            @endif
        </div>
    </div>

    {{-- نماذج العمليات (توريد / سحب) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">

        {{-- نموذج الزيادة --}}
        <div class="ui-surface-muted-bg border ui-border p-6 rounded-2xl shadow-xl border-t-green-600">
            <h3 class="ui-title font-bold text-lg mb-6 flex items-center gap-2">
                <i class="fa-solid fa-circle-plus ui-status-success"></i> توريد للمخزن
            </h3>
            <form action="{{ route('user.stores.products.stock.increase', [$store->id, $product->id]) }}" method="POST" data-disable-on-submit>
                @csrf
                <div class="space-y-4">
                    <label class="block">
                        <span class="mb-1 block ui-text-caption font-bold ui-title">تاريخ الإضافة</span>
                        <input type="date" name="business_date" value="{{ old('business_date', $currentBusinessDate) }}" required class="ui-input">
                    </label>
                    <div class="flex gap-3">
                        <div class="flex-[2]">
                            <label class="block ui-text-muted ui-text-caption uppercase font-bold mb-1 ml-1">الكمية</label>
                            <input type="number" name="quantity" step="0.01" min="0.01" required
                                   class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-4 py-3   outline-none transition"
                                   placeholder="0.00">
                            @if($isFractional)
                                <p class="ui-text-caption ui-status-info mt-1">اختر هل الكمية المدخلة بالرول أو بالمتر.</p>
                            @endif
                        </div>
                        @if($isSet || $isFractional)
                        <div class="flex-1">
                            <label class="block ui-text-muted ui-text-caption uppercase font-bold mb-1 ml-1">الوحدة</label>
                            <select name="unit_type" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-2 py-3   outline-none cursor-pointer">
                                @if($isFractional)
                                    <option value="roll">رول</option>
                                    <option value="meter">متر</option>
                                @else
                                    <option value="unit">طقم</option>
                                    <option value="piece">حبة مفردة</option>
                                @endif
                            </select>
                        </div>
                        @endif
                    </div>

                    <div>
                        <label class="block ui-text-muted ui-text-caption uppercase font-bold mb-1 ml-1">ملاحظة العمليّة</label>
                        <input type="text" name="note" placeholder="مثلاً: توريد بضاعة جديدة، مرتجع من عميل..."
                               class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-4 py-3   outline-none transition">
                    </div>

                    <button type="submit" class="w-full ui-status-success-bg ui-title font-black py-4 rounded-xl transition-all active:scale-[0.98] shadow-lg ">
                        تأكيد زيادة المخزون
                    </button>
                </div>
            </form>
        </div>

        {{-- نموذج السحب --}}
        <div class="ui-surface-muted-bg border ui-border p-6 rounded-2xl shadow-xl border-t-red-600">
            <h3 class="ui-title font-bold text-lg mb-6 flex items-center gap-2">
                <i class="fa-solid fa-circle-minus ui-status-danger"></i> سحب / عجز / إتلاف
            </h3>
            <form action="{{ route('user.stores.products.stock.decrease', [$store->id, $product->id]) }}" method="POST" data-confirm-submit data-validate-available-stock data-current-stock="{{ $rawStoredQuantity }}" data-product-type="{{ $product->product_type }}" data-is-splittable="{{ $isSet ? '1' : '0' }}" data-items-per-unit="{{ (int) ($product->items_per_unit ?: 1) }}" data-roll-length="{{ (float) ($product->roll_length ?: 0) }}" data-empty-title="لا يمكن سحب المخزون" data-empty-text="رصيد المنتج الحالي صفر، ولا توجد كمية متاحة للسحب." data-insufficient-title="الكمية غير متوفرة" data-insufficient-text="الكمية المطلوب سحبها أكبر من الكمية الموجودة في المخزون." data-confirm-title="تأكيد سحب المخزون" data-confirm-text="سيتم خصم الكمية المدخلة من مخزون المنتج. تأكد من الكمية والوحدة قبل المتابعة." data-confirm-icon="warning">
                @csrf
                @error('quantity')
                    <div class="ui-status-danger-bg ui-status-danger rounded-xl border ui-border p-3 text-sm font-bold">{{ $message }}</div>
                @enderror
                <div class="space-y-4">
                    <label class="block">
                        <span class="mb-1 block ui-text-caption font-bold ui-title">تاريخ السحب</span>
                        <input type="date" name="business_date" value="{{ old('business_date', $currentBusinessDate) }}" required class="ui-input">
                    </label>
                    <div class="flex gap-3">
                        <div class="flex-[2]">
                            <label class="block ui-text-muted ui-text-caption uppercase font-bold mb-1 ml-1">الكمية</label>
                            <input type="number" name="quantity" step="0.01" min="0.01" required
                                   class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-4 py-3   outline-none transition"
                                   placeholder="0.00">
                            @if($isFractional)
                                <p class="ui-text-caption ui-status-info mt-1">اختر هل الكمية المدخلة بالرول أو بالمتر.</p>
                            @endif
                        </div>
                        @if($isSet || $isFractional)
                        <div class="flex-1">
                            <label class="block ui-text-muted ui-text-caption uppercase font-bold mb-1 ml-1">الوحدة</label>
                            <select name="unit_type" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-2 py-3   outline-none cursor-pointer">
                                @if($isFractional)
                                    <option value="roll">رول</option>
                                    <option value="meter">متر</option>
                                @else
                                    <option value="unit">طقم</option>
                                    <option value="piece">حبة مفردة</option>
                                @endif
                            </select>
                        </div>
                        @endif
                    </div>

                    <div>
                        <label class="block ui-text-muted ui-text-caption uppercase font-bold mb-1 ml-1">سبب السحب</label>
                        <input type="text" name="note" placeholder="مثلاً: كسر، تالف، جرد دوري..."
                               class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-4 py-3   outline-none transition">
                    </div>

                    <button type="submit" class="w-full ui-status-danger-bg ui-title font-black py-4 rounded-xl transition-all active:scale-[0.98] shadow-lg ">
                        تأكيد سحب الكمية
                    </button>
                </div>
            </form>
        </div>

    </div>

    {{-- سجل الحركات الأخير --}}
        <div class="ui-card shadow-2xl overflow-hidden">
            <div class="p-6 border-b ui-border flex items-center justify-between">
                <div>
                    <h3 class="ui-title font-bold flex items-center gap-2">
                        <i class="fa-solid fa-history ui-status-info"></i> سجل الحركات الأخيرة
                    </h3>
                    <p class="ui-text-caption ui-text-muted mt-1">
                        آخر تأكيد جرد:
                        <span class="{{ $latestInventoryAudit ? 'ui-status-success' : 'ui-text-muted' }}">
                            {{ $latestInventoryAudit ? (optional($latestInventoryAudit->business_date)->format('Y-m-d') ?: $latestInventoryAudit->created_at->format('Y-m-d')) : 'لا يوجد تأكيد جرد بعد' }}
                        </span>
                    </p>
                </div>
            </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="ui-surface-muted-bg ui-text-muted uppercase ui-text-caption tracking-widest">
                    <tr>
                        <th class="py-4 px-6 font-medium">العمليّة</th>
                        <th class="py-4 px-6 font-medium">الكمية وقت الحركة</th>
                        <th class="py-4 px-6 font-medium">الرصيد قبل</th>
                        <th class="py-4 px-6 font-medium">الرصيد بعد</th>
                        <th class="py-4 px-6 font-medium">التاريخ والوقت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ui-border">
                    @forelse($movements as $move)
                        @php
                            $movementUnitLabel = $move->snapshotUnitLabel($product);
                            $hasPosReference = preg_match('/POS\s*#(\d+)/u', (string) $move->note, $posMatch);
                            $moveQty = $move->quantityInSnapshotUnit((float) $move->quantity, $product);
                            $hasBalanceSnapshot = (!is_null($move->balance_before) && !is_null($move->balance_after))
                                || (!is_null($move->roll_length_at_movement) && !is_null($move->meters));
                            $beforeQty = $hasBalanceSnapshot ? $move->quantityInSnapshotUnit($move->previous_balance, $product) : null;
                            $afterQty = $hasBalanceSnapshot ? $move->quantityInSnapshotUnit($move->current_balance, $product) : null;
                            $isAuditConfirmation = str_starts_with((string) $move->note, 'تأكيد جرد المنتج');
                            $isPosSale = $move->operation_label === 'بيع' && $hasPosReference;
                            $movementOperationLabel = $isPosSale
                                ? 'بيع (' . \App\Support\ProductQuantityFormatter::number($moveQty) . ' ' . $movementUnitLabel . ') (POS #' . $posMatch[1] . ')'
                                : $move->operation_label;
                        @endphp
                        <tr class="ui-surface-muted-bg transition-colors">
                            <td class="py-4 px-6">
                                <div class="flex flex-col">
                                    <span class="font-bold {{ $isAuditConfirmation ? 'ui-status-info' : ($move->type === 'increase' ? 'ui-status-success' : 'ui-status-danger') }}">
                                        {{ $movementOperationLabel }}
                                    </span>
                                    @if($move->note && ! $isPosSale)
                                        <span class="ui-text-caption ui-text-muted italic mt-1">{{ $move->note }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <span class="font-mono font-bold ui-title text-base">
                                    {{ \App\Support\ProductQuantityFormatter::number($moveQty) }}
                                </span>
                                <span class="ui-text-caption ui-text-muted mr-1">{{ $movementUnitLabel }}</span>
                            </td>
                            <td class="py-4 px-6">
                                @if($hasBalanceSnapshot)
                                    <span class="font-mono ui-text-muted">
                                        {{ \App\Support\ProductQuantityFormatter::number($beforeQty) }}
                                    </span>
                                    <span class="ui-text-caption ui-text-muted mr-1">{{ $movementUnitLabel }}</span>
                                @else
                                    <span class="ui-text-muted ui-text-caption">غير متوفر</span>
                                @endif
                            </td>
                            <td class="py-4 px-6">
                                @if($hasBalanceSnapshot)
                                    <span class="font-mono ui-status-info font-bold">
                                        {{ \App\Support\ProductQuantityFormatter::number($afterQty) }}
                                    </span>
                                    <span class="ui-text-caption ui-text-muted mr-1">{{ $movementUnitLabel }}</span>
                                @else
                                    <span class="ui-text-muted ui-text-caption">غير متوفر</span>
                                @endif
                            </td>
                            <td class="py-4 px-6 ui-text-caption ui-text-muted font-mono">
                                {{ optional($move->business_date)->format('Y-m-d') ?: $move->created_at->format('Y-m-d') }}<br>
                                {{ $move->created_at->format('H:i A') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center">
                                <i class="fa-solid fa-inbox ui-text-muted text-5xl mb-4 block"></i>
                                <p class="ui-text-muted italic">لا توجد حركات مخزنية مسجلة حتى الآن</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($movements->hasPages())
            <div class="p-4 border-t ui-border">
                {{ $movements->links() }}
            </div>
        @endif
    </div>
</div>

@endsection
