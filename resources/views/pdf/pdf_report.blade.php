<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $report_title ?? 'تقرير إغلاق الشفت' }}</title>
    <style>
        @page { margin: 10mm 9mm 12mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            direction: rtl;
            color: #0f172a;
            font-family: 'Cairo', 'DejaVu Sans', Arial, sans-serif;
            font-size: 10.8px;
            line-height: 1.65;
            background: #eef2f7;
        }

        .report-shell {
            background: #ffffff;
            border: 1px solid #d8dee9;
            overflow: hidden;
        }

        .hero {
            position: relative;
            padding: 0;
            color: #ffffff;
            background: #0f2f4f;
            border-bottom: 4px solid #f59e0b;
        }

        .hero:before {
            content: "";
            position: absolute;
            right: 0;
            top: 0;
            width: 70%;
            height: 100%;
            background: #123c69;
        }

        .hero:after {
            content: "";
            position: absolute;
            left: -58px;
            top: -70px;
            width: 205px;
            height: 205px;
            border-radius: 105px;
            background: #1d9a8a;
            opacity: .20;
        }

        .hero-table {
            position: relative;
            z-index: 2;
            width: 100%;
            min-height: 104px;
            border-collapse: collapse;
        }

        .brand-cell {
            width: 54%;
            padding: 18px 22px 17px;
            vertical-align: middle;
        }

        .brand-layout {
            width: 100%;
            border-collapse: collapse;
        }

        .brand-icon-cell {
            width: 58px;
            vertical-align: middle;
        }

        .report-emblem {
            width: 46px;
            height: 46px;
            padding: 8px 7px;
            background: #f59e0b;
            border: 2px solid #ffd58a;
            border-radius: 14px;
        }

        .report-line {
            display: block;
            height: 4px;
            margin-bottom: 5px;
            background: #ffffff;
            border-radius: 4px;
        }

        .report-line-short { width: 62%; }
        .report-line-mid { width: 82%; }
        .report-line-wide { width: 100%; margin-bottom: 0; }

        .brand-text-cell { vertical-align: middle; }

        .project-name {
            margin-bottom: 4px;
            color: #ffe8ad;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.3px;
        }

        .report-title {
            margin: 0;
            color: #ffffff;
            font-size: 21px;
            line-height: 1.25;
            font-weight: 700;
        }

        .report-subtitle {
            margin: 5px 0 0;
            color: #dbeafe;
            font-size: 10.5px;
            font-weight: 700;
        }

        .hero-side {
            width: 46%;
            padding: 14px 18px 14px 22px;
            vertical-align: middle;
        }

        .meta-card {
            width: 100%;
            padding: 9px 11px 8px;
            color: #ffffff;
            background: #123c69;
            border: 1px solid #315c86;
            border-right: 4px solid #1d9a8a;
            border-radius: 12px;
        }

        .meta-card-title {
            margin-bottom: 6px;
            padding-bottom: 5px;
            color: #ffffff;
            border-bottom: 1px solid #315c86;
            font-size: 11px;
            font-weight: 700;
        }

        .meta-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-grid td {
            padding: 2px 0;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
        }

        .meta-label {
            display: inline-block;
            min-width: 72px;
            color: #b9d4f1;
            font-weight: 700;
        }

        .block {
            margin: 12px 15px 0;
            padding: 0;
            border: 1px solid #dbe4ef;
            border-radius: 14px;
            overflow: hidden;
            background: #ffffff;
        }

        .block-title {
            margin: 0;
            padding: 9px 12px;
            color: #123c69;
            background: #f8fafc;
            border-bottom: 1px solid #dbe4ef;
            font-size: 13px;
            font-weight: 700;
        }

        .block-body { padding: 10px 12px 12px; }

        .cards {
            width: 100%;
            border-collapse: separate;
            border-spacing: 7px;
            margin: -7px;
        }

        .cards td {
            width: 25%;
            min-height: 68px;
            padding: 9px 10px;
            vertical-align: top;
            background: #f8fafc;
            border: 1px solid #dbe4ef;
            border-top: 4px solid #123c69;
            border-radius: 12px;
        }

        .cards tr:first-child td:nth-child(1) { border-top-color: #0f766e; }
        .cards tr:first-child td:nth-child(2) { border-top-color: #b45309; }
        .cards tr:first-child td:nth-child(3) { border-top-color: #f59e0b; }
        .cards tr:first-child td:nth-child(4) { border-top-color: #1e3a8a; }
        .cards tr:nth-child(2) td:nth-child(3) { border-top-color: #047857; }

        .k-label {
            display: block;
            margin-bottom: 4px;
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
        }

        .k-value {
            color: #0f172a;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.35;
        }

        .note-box {
            margin-bottom: 10px;
            padding: 9px 10px;
            color: #334155;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-right: 5px solid #f59e0b;
            border-radius: 12px;
            font-size: 10px;
        }

        .note-info {
            background: #eff6ff;
            border-color: #bfdbfe;
            border-right-color: #3b82f6;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
        }

        table.grid th,
        table.grid td {
            padding: 7px 8px;
            border-bottom: 1px solid #edf2f7;
            text-align: right;
            font-size: 10.2px;
            vertical-align: top;
        }

        table.grid th {
            color: #334155;
            background: #f8fafc;
            font-weight: 700;
        }

        table.grid tr:nth-child(even) td { background: #fbfdff; }
        table.grid tr:last-child td { border-bottom: 0; }

        .muted { color: #64748b; }
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }

        .badge {
            display: inline-block;
            padding: 2px 7px 3px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .b-cash { background: #dcfce7; color: #166534; border-color: #86efac; }
        .b-card { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
        .b-mixed { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
        .b-credit { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .b-other { background: #e5e7eb; color: #374151; border-color: #d1d5db; }

        .products-list { margin: 0; padding: 0; list-style: none; }
        .products-list li { margin: 0 0 4px 0; }

        .breakdown-grid th { width: 34%; }
        .breakdown-grid td { font-weight: 700; }

        .operations-grid th,
        .operations-grid td {
            padding: 6px 5px;
            font-size: 10px;
        }

        .sale-products-row td {
            background: #f8fafc !important;
            border-bottom: 1px solid #dbe4ef;
        }

        .sale-products-cell {
            color: #475569;
            font-size: 10px;
            line-height: 1.75;
        }

        .sale-products-title {
            color: #123c69;
            font-weight: 700;
            margin-left: 6px;
        }

        .amount-positive { color: #047857; font-weight: 700; }
        .amount-negative { color: #b91c1c; font-weight: 700; }

        .status-good { color: #047857; font-weight: 700; }
        .status-bad { color: #b91c1c; font-weight: 700; }

        .footer {
            margin: 12px 15px 14px;
            padding-top: 8px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            font-size: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
@php
    $dataArray = $data ?? [];

    $storeName = $dataArray['store_name'] ?? ($store->name ?? '-');
    $accountantName = $dataArray['accountant_name'] ?? ($accountant->name ?? 'غير معروف');

    $totalSales = (float) ($dataArray['total_sales'] ?? 0);
    $salesValueAtSellPrice = (float) ($dataArray['products_details']['sales_value'] ?? $totalSales);
    $costValue = (float) ($dataArray['products_details']['cost_value'] ?? 0);
    $productsProfit = (float) ($dataArray['products_details']['profit'] ?? ($salesValueAtSellPrice - $costValue));
    $recognizedCost = (float) ($dataArray['products_details']['recognized_cost'] ?? 0);
    $uncoveredCost = (float) ($dataArray['products_details']['uncovered_cost'] ?? 0);
    $recognizedProfit = (float) ($dataArray['products_details']['recognized_profit'] ?? $productsProfit);
    $deferredProfit = (float) ($dataArray['products_details']['deferred_profit'] ?? 0);
    $cashSales = (float) ($dataArray['sales_breakdown']['cash_from_new_sales'] ?? 0);
    $cardSales = (float) ($dataArray['sales_breakdown']['card_from_new_sales'] ?? 0);
    $creditSales = (float) ($dataArray['sales_breakdown']['credit_sales'] ?? 0);
    $officialCreditSales = (float) ($dataArray['sales_breakdown']['official_credit'] ?? 0);
    $paymentGaps = (float) ($dataArray['sales_breakdown']['payment_gaps'] ?? 0);
    $internalUse = (float) ($dataArray['sales_breakdown']['internal_use'] ?? 0);
    $cashCollections = (float) ($dataArray['credit_collections']['total'] ?? 0);
    $currentPeriodCollections = (float) ($dataArray['credit_collections']['from_current_period'] ?? 0);
    $oldPeriodCollections = (float) ($dataArray['credit_collections']['from_old_period'] ?? 0);
    $outgoingTotal = (float) ($dataArray['outgoing_today']['total'] ?? 0);
    $laborTotal = (float) ($dataArray['labor_total'] ?? 0);
    $netProfit = (float) ($dataArray['net_profit'] ?? 0);

    $salesRows = $dataArray['details_tables']['all_sales'] ?? [];
    $salesRows = is_iterable($salesRows) ? $salesRows : [];
    $operationsCount = is_countable($salesRows) ? count($salesRows) : 0;
    // هذه الأعلام تتحكم في إظهار الأعمدة الاختيارية داخل PDF فقط:
    // إذا لم يوجد موظف أو أجرة يد في كل العمليات لا نطبع عمودًا فارغًا حتى يبقى التقرير مختصرًا وواضحًا.
    $showEmployeeColumn = collect($salesRows)->contains(fn ($sale) => trim((string) (((array) $sale)['employee'] ?? '')) !== '');
    $showLaborColumn = collect($salesRows)->contains(fn ($sale) => (float) (((array) $sale)['labor_total'] ?? 0) > 0);
    $salesTableColumns = 9 + ($showEmployeeColumn ? 1 : 0) + ($showLaborColumn ? 1 : 0);

    $expensesRows = $dataArray['details_tables']['expenses_list'] ?? [];
    $expensesRows = is_iterable($expensesRows) ? $expensesRows : [];

    $withdrawalsRows = $dataArray['details_tables']['withdrawals_list'] ?? [];
    $withdrawalsRows = is_iterable($withdrawalsRows) ? $withdrawalsRows : [];

    $collectionsRows = $dataArray['details_tables']['collections'] ?? [];
    $collectionsRows = is_iterable($collectionsRows) ? $collectionsRows : [];
    $accountantDebtRows = $dataArray['details_tables']['accountant_debts'] ?? [];
    $accountantDebtRows = is_iterable($accountantDebtRows) ? $accountantDebtRows : [];
    $includedDebtIds = collect($dataArray['accountant_finance_movements']['included_debt_rows'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();

    $expectedCash = (float) ($dataArray['cash_details']['expected'] ?? 0);
    $actualCash = (float) ($dataArray['cash_details']['actual'] ?? 0);
    $diffCash = (float) ($dataArray['cash_details']['difference'] ?? 0);

    $paymentBadge = fn ($type) => \App\Support\PaymentTypeLabel::reportBadge($type);
@endphp

<div class="report-shell">
    <div class="hero">
        <table class="hero-table">
            <tr>
                <td class="brand-cell">
                    <table class="brand-layout">
                        <tr>
                            <td class="brand-icon-cell">
                                <div class="report-emblem">
                                    <span class="report-line report-line-short"></span>
                                    <span class="report-line report-line-mid"></span>
                                    <span class="report-line report-line-wide"></span>
                                </div>
                            </td>
                            <td class="brand-text-cell">
                                <div class="project-name">CARLED</div>
                                <h1 class="report-title">تقرير الإقفال التفصيلي</h1>
                                <p class="report-subtitle">سجل العمليات والمصروفات ومطابقة الصندوق</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="hero-side">
                    <div class="meta-card">
                        <div class="meta-card-title">بيانات التقرير</div>
                        <table class="meta-grid">
                            <tr>
                                <td><span class="meta-label">المتجر</span>{{ $storeName }}</td>
                            </tr>
                            <tr>
                                <td><span class="meta-label">المحاسب</span>{{ $accountantName }}</td>
                            </tr>
                            @if(empty($dataArray['hide_period']))
                            <tr>
                                <td><span class="meta-label">الفترة</span>{{ $dataArray['start_time'] ?? '-' }} إلى {{ $dataArray['end_time'] ?? '-' }}</td>
                            </tr>
                            @endif
                            @if(!empty($dataArray['business_date']))
                            <tr>
                                <td><span class="meta-label">التاريخ</span>{{ $dataArray['business_date'] }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td><span class="meta-label">الإصدار</span>{{ $dataArray['report_date'] ?? now()->format('Y-m-d H:i') }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="block">
        <h2 class="block-title">ملخص التقرير المفصل</h2>
        <div class="block-body">
            {{-- نعرض فقط ملاحظة الإغلاق الخاصة بالشفت، بدون أي رسائل سياسة حذف هنا. --}}
            <div class="note-box note-info">
                <strong>تنبيه مهم:</strong>
                هذا التقرير متاح لمدة 90 يوماً من تاريخ الإصدار، وبعدها قد لا يمكن الوصول إليه.
            </div>

            @if(!empty($dataArray['notes']))
                <div class="note-box">
                    <strong>ملاحظة الإغلاق:</strong>
                    {{ $dataArray['notes'] }}
                </div>
            @endif

            <table class="cards">
            <tr>
                <td><span class="k-label">تكلفة المنتجات بسعر البيع</span><span class="k-value">{{ number_format($salesValueAtSellPrice, 2) }} ر.س</span></td>
                <td><span class="k-label">تكلفة المنتجات الفعلية</span><span class="k-value">{{ number_format($costValue, 2) }} ر.س</span></td>
                <td><span class="k-label">كاش المبيعات</span><span class="k-value">{{ number_format($cashSales, 2) }} ر.س</span></td>
                <td><span class="k-label">شبكة المبيعات</span><span class="k-value">{{ number_format($cardSales, 2) }} ر.س</span></td>
            </tr>
            <tr>
                <td><span class="k-label">المنصرفات (مصروف + سحب)</span><span class="k-value">{{ number_format($outgoingTotal, 2) }} ر.س</span></td>
                @if($laborTotal > 0)
                    <td><span class="k-label">أجرة اليد</span><span class="k-value">{{ number_format($laborTotal, 2) }} ر.س</span></td>
                @endif
                <td><span class="k-label">صافي الربح</span><span class="k-value">{{ number_format($netProfit, 2) }} ر.س</span></td>
                <td><span class="k-label">عدد عمليات البيع</span><span class="k-value">{{ number_format($operationsCount) }}</span></td>
            </tr>
            </table>
        </div>
    </div>

    <div class="block">
        <h2 class="block-title">تفصيل المبيعات اليومية</h2>
        <div class="block-body">
            <table class="grid breakdown-grid">
                <tbody>
                    <tr>
                        <th>إجمالي المبيعات المسجلة</th>
                        <td>{{ number_format($totalSales, 2) }} ر.س</td>
                        <th>عدد عمليات البيع</th>
                        <td>{{ number_format($operationsCount) }}</td>
                    </tr>
                    <tr>
                        <th>مبيعات الكاش</th>
                        <td>{{ number_format($cashSales, 2) }} ر.س</td>
                        <th>مبيعات الشبكة</th>
                        <td>{{ number_format($cardSales, 2) }} ر.س</td>
                    </tr>
                    {{-- لا نعرض صفوف الآجل إذا كانت كلها صفر حتى لا يظهر في التقرير بنود غير مستخدمة في الشفت. --}}
                    @if($creditSales > 0 || $officialCreditSales > 0)
                    <tr>
                        @if($creditSales > 0)
                            <th>مبيعات الآجل</th>
                            <td>{{ number_format($creditSales, 2) }} ر.س</td>
                        @endif
                        @if($officialCreditSales > 0)
                            <th>الآجل الرسمي</th>
                            <td>{{ number_format($officialCreditSales, 2) }} ر.س</td>
                        @endif
                    </tr>
                    @endif
                    {{-- فروقات الدفع والاستخدام الداخلي تظهر فقط عند وجود قيمة فعلية. --}}
                    @if($paymentGaps > 0 || $internalUse > 0)
                    <tr>
                        @if($paymentGaps > 0)
                            <th>فروقات الدفع</th>
                            <td>{{ number_format($paymentGaps, 2) }} ر.س</td>
                        @endif
                        @if($internalUse > 0)
                            <th>الاستخدام الداخلي</th>
                            <td>{{ number_format($internalUse, 2) }} ر.س</td>
                        @endif
                    </tr>
                    @endif
                    <tr>
                        <th>تكلفة المنتجات بسعر البيع</th>
                        <td>{{ number_format($salesValueAtSellPrice, 2) }} ر.س</td>
                        <th>تكلفة المنتجات الفعلية</th>
                        <td>{{ number_format($costValue, 2) }} ر.س</td>
                    </tr>
                    <tr>
                        <th>ربح العملية الكامل</th>
                        <td class="{{ $productsProfit >= 0 ? 'amount-positive' : 'amount-negative' }}">{{ number_format($productsProfit, 2) }} ر.س</td>
                        <th>الربح المحتسب</th>
                        <td class="{{ $recognizedProfit >= 0 ? 'amount-positive' : 'amount-negative' }}">{{ number_format($recognizedProfit, 2) }} ر.س</td>
                    </tr>
                    @if($recognizedCost > 0 || $uncoveredCost > 0)
                    <tr>
                        <th>تكلفة مغطاة</th>
                        <td>{{ number_format($recognizedCost, 2) }} ر.س</td>
                        <th>تكلفة غير مغطاة</th>
                        <td>{{ number_format($uncoveredCost, 2) }} ر.س</td>
                    </tr>
                    @endif
                    @if($deferredProfit > 0 || $laborTotal > 0)
                    <tr>
                        <th>ربح مؤجل</th>
                        <td>{{ number_format($deferredProfit, 2) }} ر.س</td>
                        @if($laborTotal > 0)
                            <th>أجرة اليد</th>
                            <td>{{ number_format($laborTotal, 2) }} ر.س</td>
                        @else
                            <th></th>
                            <td></td>
                        @endif
                    </tr>
                    @endif
                    {{-- التحصيلات تزيد الكاش عند تنفيذ المحاسب لها وتظهر مفصولة عن المبيعات. --}}
                    @if($cashCollections > 0)
                    <tr>
                        <th>التحصيلات</th>
                        <td>{{ number_format($cashCollections, 2) }} ر.س</td>
                        <th>من نفس الفترة / سابق</th>
                        <td>{{ number_format($currentPeriodCollections, 2) }} / {{ number_format($oldPeriodCollections, 2) }} ر.س</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <div class="block">
        <h2 class="block-title">سجل عمليات البيع التفصيلي</h2>
        <div class="block-body">
        <table class="grid operations-grid">
            <thead>
                <tr>
                    <th class="text-center" style="width: 42px;">#</th>
                    <th class="text-center" style="width: 58px;">الوقت</th>
                    <th class="text-center" style="width: 66px;">الدفع</th>
                    @if($showEmployeeColumn)<th style="width: 70px;">الموظف</th>@endif
                    <th style="width: 70px;">المحاسب</th>
                    <th class="text-center" style="width: 54px;">منتجات</th>
                    <th class="text-center" style="width: 66px;">الإجمالي</th>
                    <th class="text-center" style="width: 66px;">التكلفة</th>
                    <th class="text-center" style="width: 66px;">الربح</th>
                    @if($showLaborColumn)<th class="text-center" style="width: 62px;">أجرة اليد</th>@endif
                    <th class="text-center" style="width: 66px;">المستلم</th>
                </tr>
            </thead>
            <tbody>
            @forelse($salesRows as $sale)
                @php
                    $sale = (array) $sale;
                    $badge = $paymentBadge($sale['type'] ?? '');
                    $products = $sale['products'] ?? [];
                    $hasProducts = is_array($products) && count($products) > 0;
                    $hasLaborOnly = !$hasProducts && ((float)($sale['labor_total'] ?? 0) > 0);
                    $saleProfit = (float) ($sale['profit'] ?? 0);
                    $operationName = trim((string) ($sale['operation_name'] ?? ''));
                @endphp
                <tr>
                    <td class="text-center">{{ $sale['id'] ?? '-' }}</td>
                    <td class="text-center">{{ $sale['time'] ?? '--' }}</td>
                    <td class="text-center"><span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span></td>
                    @if($showEmployeeColumn)<td>{{ $sale['employee'] ?? '' }}</td>@endif
                    <td>{{ $sale['accountant'] ?? '---' }}</td>
                    <td class="text-center">{{ number_format((int)($sale['products_count'] ?? ($hasProducts ? count($products) : 0))) }}</td>
                    <td class="text-center">{{ number_format((float)($sale['total'] ?? 0), 2) }}</td>
                    <td class="text-center">{{ number_format((float)($sale['cost'] ?? 0), 2) }}</td>
                    <td class="text-center {{ $saleProfit >= 0 ? 'amount-positive' : 'amount-negative' }}">{{ number_format($saleProfit, 2) }}</td>
                    @if($showLaborColumn)<td class="text-center">{{ number_format((float)($sale['labor_total'] ?? 0), 2) }}</td>@endif
                    <td class="text-center">{{ number_format((float)($sale['received'] ?? 0), 2) }}</td>
                </tr>
                <tr class="sale-products-row">
                    <td colspan="{{ $salesTableColumns }}" class="sale-products-cell">
                        <span class="sale-products-title">{{ $operationName !== '' ? 'اسم العملية:' : 'تفاصيل العملية:' }}</span>
                        @if($operationName !== '')
                            <strong>{{ $operationName }}</strong>
                        @elseif($hasProducts)
                            <ul class="products-list">
                                @foreach($products as $product)
                                    <li>
                                        {{-- المطلوب في التقرير اليومي: المنتج والكمية فقط، بدون سعر الوحدة أو إجمالي السطر. --}}
                                        {{ $product['name'] ?? 'منتج' }} —
                                        الكمية {{ $product['quantity'] ?? 0 }}
                                    </li>
                                @endforeach
                            </ul>
                        @elseif($hasLaborOnly)
                            <strong>شغل يد</strong>
                            <span class="muted">{{ $sale['labor_desc'] ?? 'بدون وصف' }}</span>
                        @else
                            <span class="muted">لا توجد منتجات مرتبطة بهذه العملية.</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $salesTableColumns }}" class="text-center muted">لا توجد عمليات بيع ضمن الفترة المحددة.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- إخفاء سجل المصروفات بالكامل إذا لم توجد مصروفات فعلية داخل الفترة. --}}
    @if(is_countable($expensesRows) && count($expensesRows) > 0)
    <div class="block">
        <h2 class="block-title">سجل المصروفات</h2>
        <div class="block-body">
        <table class="grid">
            <thead>
                <tr>
                    <th class="text-center" style="width: 80px;">الوقت</th>
                    <th style="width: 100px;">النوع</th>
                    <th>البيان / الملاحظة</th>
                    <th class="text-center" style="width: 100px;">المبلغ</th>
                </tr>
            </thead>
            <tbody>
            @forelse($expensesRows as $exp)
                <tr>
                    <td class="text-center">{{ $exp['time'] ?? '--' }}</td>
                    <td>{{ $exp['category'] ?? 'مصروف عام' }}</td>
                    <td>{{ $exp['reason'] ?? '—' }}</td>
                    <td class="text-center">{{ number_format((float)($exp['amount'] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center muted">لا توجد مصروفات ضمن هذه الفترة.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @endif

    <div class="block">
        <h2 class="block-title">مطابقة الصندوق</h2>
        <div class="block-body">
        <table class="grid">
            <tbody>
                <tr>
                    <td style="width: 220px;"><strong>الكاش المتوقع</strong></td>
                    <td>{{ number_format($expectedCash, 2) }} ر.س</td>
                </tr>
                @if(($dataArray['accountant_finance_movements']['included_debt_total'] ?? 0) > 0)
                <tr>
                    <td><strong>مديونيات مختارة ضمن الموازنة</strong></td>
                    <td>- {{ number_format((float) $dataArray['accountant_finance_movements']['included_debt_total'], 2) }} ر.س</td>
                </tr>
                @endif
                <tr>
                    <td><strong>الكاش الفعلي المسلم</strong></td>
                    <td>{{ number_format($actualCash, 2) }} ر.س</td>
                </tr>
                <tr>
                    <td><strong>الحالة</strong></td>
                    <td class="{{ $diffCash == 0 ? 'status-good' : ($diffCash > 0 ? 'status-good' : 'status-bad') }}">
                        @if($diffCash == 0)
                            مطابق تمامًا
                        @elseif($diffCash > 0)
                            فائض {{ number_format($diffCash, 2) }} ر.س
                        @else
                            عجز {{ number_format(abs($diffCash), 2) }} ر.س
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>

    {{-- جدول التحصيلات التفصيلي يعرض اسم العملية/الموظف ونوع التحصيل دون خلطه مع بيع جديد. --}}
    @if($cashCollections > 0 && is_countable($collectionsRows) && count($collectionsRows) > 0)
    <div class="block">
        <h2 class="block-title">تحصيلات الأجل والمديونية</h2>
        <div class="block-body">
        <table class="grid">
            <thead>
                <tr>
                    <th class="text-center" style="width: 80px;">الوقت</th>
                    <th style="width: 105px;">نوع التحصيل</th>
                    <th>اسم العملية</th>
                    <th style="width: 110px;">الموظف</th>
                    <th class="text-center" style="width: 90px;">التاريخ</th>
                    <th class="text-center" style="width: 80px;">كاش</th>
                    <th class="text-center" style="width: 80px;">شبكة</th>
                    <th class="text-center" style="width: 100px;">المبلغ</th>
                    <th class="text-center" style="width: 100px;">المتبقي</th>
                </tr>
            </thead>
            <tbody>
            @foreach($collectionsRows as $col)
                @php
                    $collectionOperationName = trim((string) ($col['credit_note'] ?? $col['operation_name'] ?? $col['description'] ?? ''));
                    if ($collectionOperationName === '') {
                        $collectionOperationName = $col['employee_name'] ?? 'عملية تحصيل';
                    }
                    $collectionBase = ($col['collection_kind'] ?? null) === 'debt' ? 'مديونية' : 'أجل';
                    $collectionType = ($col['type'] ?? '') === 'full_collection' ? "تحصيل {$collectionBase} كلي" : (($col['type'] ?? '') === 'partial_collection' ? "تحصيل {$collectionBase} جزئي" : "تحصيل {$collectionBase}");
                    $collectedAmount = (float)($col['collected_in_shift'] ?? $col['amount'] ?? 0);
                @endphp
                <tr>
                    <td class="text-center">{{ isset($col['collection_date']) ? \Carbon\Carbon::parse($col['collection_date'])->format('h:i A') : '--' }}</td>
                    <td>{{ $collectionType }}</td>
                    <td>{{ $collectionOperationName }}</td>
                    <td>{{ $col['employee_name'] ?? '—' }}</td>
                    <td class="text-center">{{ $col['operation_date'] ?? (isset($col['collection_date']) ? \Carbon\Carbon::parse($col['collection_date'])->format('Y-m-d') : '—') }}</td>
                    <td class="text-center">{{ number_format((float)($col['cash_amount'] ?? 0), 2) }}</td>
                    <td class="text-center">{{ number_format((float)($col['card_amount'] ?? 0), 2) }}</td>
                    <td class="text-center">{{ number_format($collectedAmount, 2) }}</td>
                    <td class="text-center">{{ isset($col['remaining_after_collection']) ? number_format((float)$col['remaining_after_collection'], 2) : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div>
    @endif

    @if(is_countable($accountantDebtRows) && count($accountantDebtRows) > 0)
    <div class="block">
        <h2 class="block-title">مديونيات سجلها المحاسب</h2>
        <div class="block-body">
        <table class="grid">
            <thead>
                <tr>
                    <th class="text-center" style="width: 80px;">الوقت</th>
                    <th>الموظف</th>
                    <th>البيان</th>
                    <th class="text-center" style="width: 90px;">التاريخ</th>
                    <th class="text-center" style="width: 100px;">المبلغ</th>
                    <th style="width: 160px;">أثرها في الموازنة</th>
                </tr>
            </thead>
            <tbody>
            @foreach($accountantDebtRows as $row)
                @php($isIncluded = in_array((int)($row['id'] ?? 0), $includedDebtIds, true))
                <tr>
                    <td class="text-center">{{ $row['time'] ?? '--' }}</td>
                    <td>{{ $row['employee_name'] ?? '—' }}</td>
                    <td>{{ $row['description'] ?? 'مديونية مسجلة على الموظف' }}</td>
                    <td class="text-center">{{ $row['operation_date'] ?? '—' }}</td>
                    <td class="text-center">{{ number_format((float)($row['amount'] ?? 0), 2) }}</td>
                    <td>{{ $isIncluded ? 'مضافة للموازنة وتخصم من الكاش' : 'توضيحي فقط خارج الحساب' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div>
    @endif

    @if(is_countable($withdrawalsRows) && count($withdrawalsRows) > 0)
    <div class="block">
        <h2 class="block-title">سجل السحوبات</h2>
        <div class="block-body">
        <table class="grid">
            <thead>
                <tr>
                    <th class="text-center" style="width: 80px;">الوقت</th>
                    <th style="width: 120px;">الموظف</th>
                    <th style="width: 120px;">من نفذ العملية</th>
                    <th>البيان</th>
                    <th class="text-center" style="width: 100px;">المبلغ</th>
                </tr>
            </thead>
            <tbody>
            @foreach($withdrawalsRows as $w)
                <tr>
                    <td class="text-center">{{ $w['time'] ?? '--' }}</td>
                    <td>{{ $w['employee_name'] ?? '—' }}</td>
                    <td>{{ $w['actor_name'] ?? '—' }}</td>
                    <td>{{ $w['reason'] ?? 'سحب نقدي' }}</td>
                    <td class="text-center">{{ number_format((float)($w['amount'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div>
    @endif

    <div class="footer">
        تم إنشاء هذا التقرير آلياً من CARLED — تقرير الإقفال التفصيلي
    </div>
</div>
</body>
</html>
