<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Cairo', 'Amiri', serif; direction: rtl; text-align: right; font-size: 12px; line-height: 1.7; color: #1f2933; padding: 18px; background: #ffffff; word-break: break-word; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 18px; border: 1px solid #d7dde5; }
        .header td { border: none; padding: 14px; vertical-align: top; }
        .header .side { width: 32%; background: #f8fafc; }
        .header .center { width: 36%; text-align: center; background: #eef4ff; border-right: 1px solid #d7dde5; border-left: 1px solid #d7dde5; }
        .brand { font-size: 18px; font-weight: bold; color: #1f3a5f; margin-bottom: 4px; }
        .report-title { font-size: 20px; font-weight: bold; color: #172554; margin: 7px 0 3px; }
        .month { font-size: 13px; color: #475569; }
        .info-title { font-size: 12px; font-weight: bold; color: #475569; margin-bottom: 6px; border-bottom: 1px solid #d7dde5; padding-bottom: 4px; }
        .info-line { margin: 3px 0; color: #334155; }
        .info-line span { color: #64748b; }
        .summary { width: 100%; border-collapse: collapse; margin: 12px 0 18px; }
        .summary td { width: 25%; padding: 10px; border: 1px solid #d7dde5; background: #fbfdff; text-align: center; }
        .summary .label { display: block; color: #64748b; font-size: 11px; }
        .summary .value { display: block; color: #0f172a; font-size: 15px; font-weight: bold; margin-top: 3px; }
        h2 { margin: 18px 0 8px; font-size: 15px; font-weight: bold; color: #0f172a; border-right: 4px solid #2563eb; padding-right: 8px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 10.5px; table-layout: fixed; }
        table.data th, table.data td { border: 1px solid #d7dde5; padding: 6px; text-align: center; vertical-align: top; overflow-wrap: anywhere; word-break: break-word; }
        table.data th { background: #f1f5f9; color: #334155; font-weight: bold; }
        table.data tbody tr:nth-child(even) { background: #fbfdff; }
        .note { color: #64748b; }
        .amount-add { color: #b91c1c; font-weight: bold; }
        .amount-collect { color: #047857; font-weight: bold; }
        .empty-box { border: 1px dashed #cbd5e1; background: #f8fafc; color: #64748b; padding: 10px; margin-top: 18px; text-align: center; }
        .footer { margin-top: 20px; font-size: 11px; text-align: center; border-top: 1px solid #d7dde5; padding-top: 8px; color: #64748b; }
        .text-cell { text-align: right; line-height: 1.6; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body>
@php
    $store = $person->store;
    $owner = $store?->user;
    // توحيد عرض تواريخ PDF كتاريخ عملية فقط بدون وقت أو عبارة تاريخ الشفت.
    $formatOperationDateOnly = function ($operationDateValue) {
        if (blank($operationDateValue)) {
            return '—';
        }

        try {
            return \Carbon\Carbon::parse($operationDateValue)->format('Y-m-d');
        } catch (\Throwable $exception) {
            return '—';
        }
    };

    $resolveOperationDateOnly = function ($employeeOperation) use ($formatOperationDateOnly) {
        return $formatOperationDateOnly($employeeOperation->business_date ?? $employeeOperation->date ?? $employeeOperation->created_at ?? null);
    };

    $resolveActorName = function ($employeeOperation) {
        $actorId = $employeeOperation->added_by ?? null;

        if ($actorId) {
            return optional(\App\Models\Accountant::withTrashed()->find($actorId))->name
                ?? $employeeOperation->addedBy?->name
                ?? optional(\App\Models\User::withTrashed()->find($actorId))->name
                ?? '—';
        }

        if ($employeeOperation->addedBy?->name) {
            return $employeeOperation->addedBy->name;
        }

        return '—';
    };

    $otherStoreName = function ($employeeOperation) use ($store) {
        $operationStore = $employeeOperation->store ?? null;

        if (! $operationStore || (int) $operationStore->id === (int) ($store->id ?? 0)) {
            return '—';
        }

        return $operationStore->name ?? '—';
    };


    $reportOperations = collect()
        ->merge($withdrawals ?? collect())
        ->merge($absences ?? collect())
        ->merge($debts ?? collect())
        ->merge($creditSalesPending ?? collect())
        ->merge($creditSalesCollected ?? collect());

    $showOtherStoreColumn = $reportOperations->contains(fn ($employeeOperation) => $otherStoreName($employeeOperation) !== '—');
    $operationActorNames = $reportOperations
        ->map(fn ($employeeOperation) => $resolveActorName($employeeOperation))
        ->filter(fn ($actorName) => filled($actorName) && $actorName !== '—')
        ->unique()
        ->values();
    $activeStoreAccountants = $store
        ? \App\Models\Accountant::where('store_id', $store->id)->where('status', 'active')->pluck('name')->filter()->unique()->values()
        : collect();
    $singleReportAccountantName = ($activeStoreAccountants->count() === 1 && $operationActorNames->count() <= 1)
        ? $activeStoreAccountants->first()
        : null;
    $showActorColumn = blank($singleReportAccountantName);
@endphp

<table class="header">
    <tr>
        <td class="side">
            <div class="info-title">بيانات العامل</div>
            <div class="info-line"><span>الاسم:</span> {{ $person->name }}</div>
            <div class="info-line"><span>الجوال:</span> {{ $person->phone ?? '—' }}</div>
            <div class="info-line"><span>راتب التقرير:</span> {{ number_format($historical_salary ?? $person->salary ?? 0, 2) }} ريال</div>
            <div class="info-line"><span>أيام التقرير:</span> {{ $salary_worked_days ?? $salary_total_days ?? '—' }} / {{ $salary_total_days ?? '—' }}</div>
        </td>
        <td class="center">
            <div class="brand">{{ config('app.name', 'Carled') }}</div>
            <div class="report-title">تقرير شهري</div>
            <div class="month">{{ $report_month }}</div>
        </td>
        <td class="side">
            <div class="info-title">بيانات المتجر</div>
            <div class="info-line"><span>المتجر:</span> {{ $store->name ?? '—' }}</div>
            <div class="info-line"><span>المالك:</span> {{ $owner->name ?? '—' }}</div>
            @if($singleReportAccountantName)
                <div class="info-line"><span>المحاسب:</span> {{ $singleReportAccountantName }}</div>
            @endif
            <div class="info-line"><span>تاريخ الإصدار:</span> {{ now()->format('Y-m-d') }}</div>
        </td>
    </tr>
</table>

<table class="summary">
    <tr>
        <td><span class="label">الراتب المستحق</span><span class="value">{{ number_format($salary_payable ?? 0, 2) }}</span></td>
        <td><span class="label">السحوبات</span><span class="value">{{ number_format($withdrawals_total ?? 0, 2) }}</span></td>
        <td><span class="label">خصم الغياب</span><span class="value">{{ number_format($absence_penalty ?? 0, 2) }}</span></td>
        <td><span class="label">الصافي</span><span class="value">{{ number_format($salary_net ?? 0, 2) }}</span></td>
    </tr>
</table>

@if($withdrawals->isNotEmpty())
    <h2>السحوبات</h2>
    <table class="data">
        <thead><tr><th>#</th><th>المبلغ</th><th>التاريخ</th>@if($showOtherStoreColumn)<th>متجر آخر</th>@endif @if($showActorColumn)<th>سجّل بواسطة</th>@endif<th>الملاحظات</th></tr></thead>
        <tbody>
        @foreach($withdrawals as $withdrawalIndex => $withdrawalOperation)
            <tr>
                <td>{{ $withdrawalIndex + 1 }}</td>
                <td>{{ number_format($withdrawalOperation->amount, 2) }} ريال</td>
                <td class="nowrap">{{ $resolveOperationDateOnly($withdrawalOperation) }}</td>
                @if($showOtherStoreColumn)<td>{{ $otherStoreName($withdrawalOperation) }}</td>@endif
                @if($showActorColumn)<td>{{ $resolveActorName($withdrawalOperation) }}</td>@endif
                <td class="text-cell">{{ $withdrawalOperation->description ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if($absences->isNotEmpty())
    <h2>الغيابات</h2>
    <table class="data">
        <thead><tr><th>#</th><th>التاريخ</th>@if($showOtherStoreColumn)<th>متجر آخر</th>@endif @if($showActorColumn)<th>سجّل بواسطة</th>@endif<th>الأثر ضمن الأيام</th><th>الملاحظات</th></tr></thead>
        <tbody>
        @foreach($absences as $absenceIndex => $absenceOperation)
            <tr>
                <td>{{ $absenceIndex + 1 }}</td>
                <td class="nowrap">{{ $resolveOperationDateOnly($absenceOperation) }}</td>
                @if($showOtherStoreColumn)<td>{{ $otherStoreName($absenceOperation) }}</td>@endif
                @if($showActorColumn)<td>{{ $resolveActorName($absenceOperation) }}</td>@endif
                <td>يُحتسب يوم غياب ضمن أيام التقرير ويُخصم من الراتب</td>
                <td class="text-cell">{{ $absenceOperation->description ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if($debts->isNotEmpty())
    <h2>المديونيات والتحصيلات</h2>
    <table class="data">
        <thead><tr><th>#</th><th>النوع</th><th>المبلغ</th><th>التاريخ</th>@if($showActorColumn)<th>سجّل بواسطة</th>@endif<th>الملاحظات</th></tr></thead>
        <tbody>
        @foreach($debts as $debtIndex => $debtOperation)
            @php($isDebtCollectionOperation = (float) $debtOperation->amount < 0)
            <tr>
                <td>{{ $debtIndex + 1 }}</td>
                <td>{{ $isDebtCollectionOperation ? 'تحصيل' : 'إضافة مديونية' }}</td>
                <td class="{{ $isDebtCollectionOperation ? 'amount-collect' : 'amount-add' }}">{{ number_format(abs((float) $debtOperation->amount), 2) }} ريال</td>
                <td class="nowrap">{{ $resolveOperationDateOnly($debtOperation) }}</td>
                @if($showActorColumn)<td>{{ $resolveActorName($debtOperation) }}</td>@endif
                <td class="text-cell">{{ $debtOperation->description ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if($creditSalesPending->isNotEmpty())
    <h2>البيع الآجل غير المحصل</h2>
    <table class="data">
        <thead><tr><th>#</th><th>القيمة</th><th>المتبقي</th><th>التاريخ</th>@if($showActorColumn)<th>سجّل بواسطة</th>@endif<th>الملاحظات</th></tr></thead>
        <tbody>
        @foreach($creditSalesPending as $pendingCreditSaleIndex => $pendingCreditSaleOperation)
            <tr>
                <td>{{ $pendingCreditSaleIndex + 1 }}</td>
                <td>{{ number_format($pendingCreditSaleOperation->amount, 2) }} ريال</td>
                <td>{{ number_format($pendingCreditSaleOperation->remaining_amount, 2) }} ريال</td>
                <td class="nowrap">{{ $resolveOperationDateOnly($pendingCreditSaleOperation) }}</td>
                @if($showActorColumn)<td>{{ $resolveActorName($pendingCreditSaleOperation) }}</td>@endif
                <td class="text-cell">{{ $pendingCreditSaleOperation->credit_note ?: ($pendingCreditSaleOperation->description ?? '—') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if($creditSalesCollected->isNotEmpty())
    <h2>البيع الآجل المحصل والتحصيلات</h2>
    <table class="data">
        <thead><tr><th>#</th><th>القيمة</th><th>التاريخ</th>@if($showActorColumn)<th>سجّل بواسطة</th>@endif<th>التحصيلات</th><th>الملاحظات</th></tr></thead>
        <tbody>
        @foreach($creditSalesCollected as $collectedCreditSaleIndex => $collectedCreditSaleOperation)
            <tr>
                <td>{{ $collectedCreditSaleIndex + 1 }}</td>
                <td>{{ number_format($collectedCreditSaleOperation->amount, 2) }} ريال</td>
                <td class="nowrap">{{ $resolveOperationDateOnly($collectedCreditSaleOperation) }}</td>
                @if($showActorColumn)<td>{{ $resolveActorName($collectedCreditSaleOperation) }}</td>@endif
                <td class="text-cell">
                    @forelse(collect($collectedCreditSaleOperation->collection_payments ?? []) as $collectionPayment)
                        {{ number_format((float) ($collectionPayment['amount'] ?? 0), 2) }} ريال
                        - {{ $formatOperationDateOnly($collectionPayment['date'] ?? null) }}
                        - {{ $collectionPayment['added_by_name'] ?? 'غير محدد' }}
                        @if(!empty($collectionPayment['description'])) ({{ $collectionPayment['description'] }}) @endif
                        @if(!$loop->last)<br>@endif
                    @empty
                        <span class="note">لا توجد تفاصيل تحصيل محفوظة</span>
                    @endforelse
                </td>
                <td class="text-cell">{{ $collectedCreditSaleOperation->credit_note ?: ($collectedCreditSaleOperation->description ?? '—') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if($emptySections->isNotEmpty())
    <div class="empty-box">
        لا توجد بيانات في هذا التقرير للأقسام التالية: {{ $emptySections->implode('، ') }}.
    </div>
@endif

<div class="footer">
    <div>تم إنشاء التقرير بواسطة: CARLED</div>
    <div style="font-size: 10px; margin-top: 3px;">هذا المستند قابل للمراجعه خلال 10 ايام من تاريخ اصدارة</div>
</div>
</body>
</html>