<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>تقرير المشتريات الشهري</title>
    <style>
        body { direction: rtl; text-align: right; margin: 0; padding: 12mm; font-family: 'Cairo', 'Amiri', 'DejaVu Sans', sans-serif; font-size: 11px; color: #0f172a; box-sizing: border-box; }
        .report-header { border-bottom: 2px solid #334155; padding-bottom: 8px; margin-bottom: 16px; }
        .report-title { font-size: 18px; font-weight: bold; margin: 0 0 3px; }
        .report-meta { font-size: 11px; color: #475569; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .summary { margin-bottom: 15px; }
        .summary td { border: 1px solid #dbe3ed; padding: 8px; text-align: center; }
        .summary-label { font-size: 10px; color: #475569; }
        .summary-value { font-size: 14px; font-weight: bold; }
        .records th, .records td { border: 1px solid #dbe3ed; padding: 7px; font-size: 10px; }
        .records th { background: #eef3f8; }
        .amount { font-weight: bold; }
        .empty { text-align: center; padding: 16px; }
    </style>
</head>
<body>
<div class="report-header">
    <p class="report-title">تقرير المشتريات الشهري - {{ $store->name }}</p>
    <p class="report-meta">الشهر: {{ str_pad($month, 2, '0', STR_PAD_LEFT) }}/{{ $year }} | الفترة: {{ $reportData['startDate'] }} إلى {{ $reportData['endDate'] }}</p>
</div>

<table class="summary">
    <tr>
        <td>
            <div class="summary-label">استهلاك المحاسب</div>
            <div class="summary-value">{{ number_format($reportData['summary']['accountant_total'], 2) }} ر.س</div>
        </td>
        <td>
            <div class="summary-label">مشتريات المالك</div>
            <div class="summary-value">{{ number_format($reportData['summary']['owner_total'], 2) }} ر.س</div>
        </td>
        <td>
            <div class="summary-label">الإجمالي</div>
            <div class="summary-value">{{ number_format($reportData['summary']['grand_total'], 2) }} ر.س</div>
        </td>
        <td>
            <div class="summary-label">عدد العمليات</div>
            <div class="summary-value">{{ $reportData['summary']['count'] }}</div>
        </td>
    </tr>
</table>

<table class="records">
    <thead>
        <tr>
            <th>#</th><th>المصدر</th><th>النوع</th><th>الوصف</th><th>المبلغ</th><th>التاريخ</th>
        </tr>
    </thead>
    <tbody>
        @forelse($reportData['records'] as $index => $row)
            <tr>
                <td>{{ $index + 1 }}</td><td>{{ $row['source'] }}</td><td>{{ $row['type'] }}</td><td>{{ $row['description'] }}</td>
                <td class="amount">{{ number_format($row['amount'], 2) }} ر.س</td><td>{{ \Carbon\Carbon::parse($row['operation_date'])->format('Y-m-d') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="empty">لا توجد بيانات لهذا الشهر.</td>
            </tr>
        @endforelse
    </tbody>
</table>
</body>
</html>
