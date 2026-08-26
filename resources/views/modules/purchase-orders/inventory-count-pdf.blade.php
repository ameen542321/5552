<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Cairo', 'DejaVu Sans', Arial, sans-serif; direction: rtl; font-size: 12px; color: #172033; margin: 0; padding: 10px; }
        .header { border: 1px solid #d7deea; border-radius: 8px; padding: 10px; background: #f8fafc; margin-bottom: 10px; }
        .brand { font-size: 20px; font-weight: 800; color: #0f766e; }
        .title { font-size: 16px; font-weight: 800; margin: 5px 0; }
        .meta { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .meta td { padding: 5px; border: 1px solid #e5e7eb; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th { background: #0f766e; color: #fff; padding: 8px; border: 1px solid #0f766e; font-size: 11px; text-align: center; }
        table.items td { padding: 8px; border: 1px solid #d8dee9; text-align: center; font-size: 11px; }
        .product-name { text-align: right; }
        .empty-value { color: #94a3b8; }
        .footer { margin-top: 20px; text-align: center; color: #64748b; font-size: 10px; }
        .signatures { margin-top: 20px; width: 100%; }
        .signatures td { width: 50%; padding: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">CARLED</div>
        <div class="title">مستند جرد طلبية توريد — {{ e($order->referenceCode()) }}</div>
        <table class="meta">
            <tr>
                <td>المتجر: {{ e($store->name ?? 'غير محدد') }}</td>
                <td>المورد: {{ e($order->supplier_name ?: 'غير محدد') }}</td>
                <td>تاريخ طلب الجرد: {{ optional($order->inventory_returned_at)->format('Y-m-d H:i') ?: optional($order->created_at)->format('Y-m-d H:i') ?: 'غير محدد' }}</td>
            </tr>
            <tr>
                <td>مالك المتجر: {{ e($store->user?->name ?: 'غير محدد') }}</td>
                <td>المحاسب: {{ e($order->accountant?->name ?: $store->accountants?->firstWhere('status', 'active')?->name ?: 'غير محدد') }}</td>
                <td>نوع الوثيقة: جرد طلبية توريد</td>
            </tr>
        </table>
    </div>

    <div class="header">
        <strong>المطلوب من المحاسب:</strong>
        عدّ المنتجات الظاهرة فقط، واختر وحدة الجرد الصحيحة، ثم اكتب الكمية الفعلية وأعد النتيجة إلى مالك المتجر.
        @if($order->inventory_review_note)<div>ملاحظة المالك: {{ e($order->inventory_review_note) }}</div>@endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>المنتج</th>
                <th>الكمية المطلوبة</th>
                <th>كمية الجرد</th>
                <th>ملاحظات</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items->where('inventory_count_required', true) as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="product-name">{{ e($item->productName()) }}</td>
                    @php
                        $requestedUnit = ['unit' => 'وحدة', 'piece' => 'حبة', 'kit' => 'طقم', 'roll' => 'رول', 'meter' => 'متر', 'meters' => 'متر'][$item->unit_type ?: 'unit'] ?? 'وحدة';
                        $countUnit = ['unit' => 'وحدة', 'piece' => 'حبة', 'kit' => 'طقم', 'roll' => 'رول', 'meter' => 'متر'][$item->inventory_count_unit ?: $item->unit_type ?: 'unit'] ?? 'وحدة';
                    @endphp
                    <td>{{ number_format((float) $item->quantity_requested, 2) }} {{ $requestedUnit }}</td>
                    <td class="{{ $item->inventory_count_quantity === null ? 'empty-value' : '' }}">
                        {{ $item->inventory_count_quantity !== null ? number_format((float) $item->inventory_count_quantity, 2).' '.$countUnit : '—' }}
                    </td>
                    <td>{{ e($item->inventory_count_note ?: '—') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td>توقيع المحاسب: ..........................</td>
            <td>توقيع {{ e($store->user?->name ?: 'صاحب المتجر') }}: ..........................</td>
        </tr>
    </table>
    <div class="footer">وثيقة جرد طلبية توريد — CARLED</div>
</body>
</html>
