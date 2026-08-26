<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير الموظف الشهري</title>
    <style>
        body { font-family: 'Cairo', 'Amiri', 'DejaVu Sans', sans-serif; color: #0f172a; font-size: 12px; background: #f8fafc; }
        .card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; background: #fff; }
        .title { margin: 0 0 10px; font-size: 20px; color: #111827; }
        .subtitle { margin: 0 0 14px; font-size: 12px; color: #475569; }
        .meta-grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .meta-grid td { border: 1px solid #e2e8f0; padding: 7px; background: #f8fafc; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #dbe3ee; padding: 9px; text-align: right; }
        th { background: #eff6ff; color: #1e3a8a; }
        .total { font-weight: bold; color: #065f46; background: #ecfdf5; }
    </style>
</head>
<body>
    <div class="card">
        <h1 class="title">تقرير الموظف الشهري</h1>
        <p class="subtitle">ملخص شهري للراتب والسحوبات والمديونية والغياب</p>

        <table class="meta-grid">
            <tr>
                <td><strong>المتجر:</strong> {{ $store->name }}</td>
                <td><strong>الموظف:</strong> {{ $employee->name }}</td>
            </tr>
            <tr>
                <td><strong>الشهر:</strong> {{ $start->locale('ar')->translatedFormat('F Y') }}</td>
                <td><strong>الفترة:</strong> {{ $start->format('Y-m-d') }} إلى {{ $end->format('Y-m-d') }}</td>
            </tr>
        </table>

        {{-- نفس بنود التقرير الشهري للموظف في الواجهة (نسخة قابلة للطباعة) --}}
        <table>
            <tr><th>الراتب الأساسي</th><td>{{ number_format($salary, 2) }} ر.س</td></tr>
            <tr><th>السحوبات</th><td>{{ number_format($withdrawals, 2) }} ر.س</td></tr>
            <tr><th>المديونية</th><td>{{ number_format($debts, 2) }} ر.س</td></tr>
            <tr><th>عدد أيام الغياب</th><td>{{ number_format($absencesCount) }}</td></tr>
            <tr><th>خصم الغياب</th><td>{{ number_format($absencePenalty ?? 0, 2) }} ر.س</td></tr>
            <tr><th class="total">صافي الراتب</th><td class="total">{{ number_format($netSalary, 2) }} ر.س</td></tr>
        </table>
    </div>
</body>
</html>
