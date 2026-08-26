<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Cairo', 'Amiri', 'DejaVu Sans', sans-serif; font-size: 12px; color: #222; }
        h1, h2 { margin: 0 0 8px 0; }
        .muted { color: #666; font-size: 11px; }
        .stats { margin: 12px 0; }
        .stats td { padding: 6px 10px; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 7px; text-align: right; }
        th { background: #f5f5f5; }
        .badge-op { color: #1d4ed8; font-weight: bold; }
        .badge-owner { color: #047857; font-weight: bold; }
    </style>
</head>
<body>
    <h1>تقرير المصروفات الشهري</h1>
    <p class="muted">المتجر: {{ $store->name ?? '---' }}</p>
    <p class="muted">الفترة: {{ $year }}-{{ str_pad((string)$month, 2, '0', STR_PAD_LEFT) }}</p>
    <p class="muted">تاريخ التوليد: {{ $generatedAt }}</p>

    <table class="stats">
        <tr>
            <td>إجمالي المصروفات</td>
            <td><strong>{{ number_format($total, 2) }} ر.س</strong></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>النوع</th>
                <th>الوصف</th>
                <th>المبلغ</th>
                <th>التاريخ</th>
            </tr>
        </thead>
        <tbody>
            @forelse($expenses as $index => $expense)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $expense->type }}</td>
                    <td>{{ $expense->description ?: '-' }}</td>
                    <td>{{ number_format($expense->amount, 2) }} ر.س</td>
                    <td>{{ optional($expense->business_date)->format('Y-m-d') ?: $expense->created_at->format('Y-m-d') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center; color:#666;">لا توجد بيانات لهذه الفترة</td>
                </tr>
            @endforelse
        </tbody>
    </table>


</body>
</html>
