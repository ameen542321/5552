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
        .blank-value { height: 26px; }
        .footer { margin-top: 20px; text-align: center; color: #64748b; font-size: 10px; }
        .signatures { margin-top: 20px; width: 100%; }
        .signatures td { width: 50%; padding: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">CARLED</div>
        <div class="title">مستند جرد مخزني — {{ e($session->referenceCode()) }}</div>
        <table class="meta">
            <tr>
                <td>المتجر: {{ e($store->name ?? 'غير محدد') }}</td>
                <td>مالك المتجر: {{ e($store->user?->name ?: 'غير محدد') }}</td>
                <td>تاريخ الإصدار: {{ $issuedAt->format('Y-m-d H:i') }}</td>
            </tr>
            <tr>
                <td>المحاسب: {{ e($session->accountant?->name ?: 'غير محدد') }}</td>
                <td>عدد المنتجات: {{ $session->items->count() }}</td>
                <td>نوع الوثيقة: جلسة جرد مخزني</td>
            </tr>
        </table>
    </div>

    <div class="header">
        <strong>المطلوب من المحاسب:</strong>
        عدّ المنتجات الظاهرة، واكتب الكمية الفعلية والملاحظة عند الحاجة، ثم أدخل النتائج في جلسة الجرد الإلكترونية.
        @if($session->note)<div>ملاحظة المالك: {{ e($session->note) }}</div>@endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>المنتج</th>
                <th>الوحدة</th>
                <th>كمية الجرد</th>
                <th>ملاحظات</th>
            </tr>
        </thead>
        <tbody>
            @foreach($session->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="product-name">{{ e($item->product_name_snapshot) }}</td>
                    <td>{{ ['piece'=>'حبة','kit'=>'طقم','meter'=>'متر','roll'=>'رول','unit'=>'وحدة'][$item->unit_type] ?? $item->unit_type }}</td>
                    <td class="blank-value"></td>
                    <td class="blank-value"></td>
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
    <div class="footer">وثيقة جلسة جرد مخزني — CARLED</div>
</body>
</html>
