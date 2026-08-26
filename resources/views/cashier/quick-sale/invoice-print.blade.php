<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاتورة ضريبية #{{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'Cairo', 'Amiri', sans-serif;
            direction: rtl;
            text-align: right;
            background-color: #fff;
            margin: 0;
            padding: 0;
        }

        .invoice-wrapper {
            max-width: 850px;
            margin: 0 auto;
            border: 1px solid #d1d5db;
            padding: 40px;
            position: relative;
        }

        /* رأس الفاتورة */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111827;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .store-info h1 {
            margin: 0;
            font-size: 24px;
            color: #111827;
        }

        .store-details {
            font-size: 12px;
            color: #4b5563;
            margin-top: 5px;
        }

        .qr-section {
            text-align: left;
        }

        .qr-box {
            width: 140px;
            height: 140px;
            border: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9fafb;
        }

        /* صف بيانات العميل والمركبة */
        .customer-vehicle-row {
            display: flex;
            justify-content: space-between;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 15px 25px;
            margin-bottom: 25px;
            border-radius: 4px;
        }

        .info-block {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 11px;
            color: #64748b;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        /* الجدول */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        table th {
            background: #f1f5f9;
            color: #475569;
            padding: 12px 10px;
            font-size: 13px;
            border-top: 2px solid #111827;
            border-bottom: 1px solid #e2e8f0;
        }

        table td {
            padding: 12px 10px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }

        /* المخلص المالي */
        .footer-flex {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .notes-area {
            width: 50%;
            font-size: 12px;
            border-right: 3px solid #e2e8f0;
            padding-right: 15px;
        }

        .totals-area {
            width: 35%;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 13px;
        }

        .grand-total-line {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            padding: 10px 0;
            border-top: 2px solid #111827;
            font-weight: bold;
            font-size: 18px;
            color: #111827;
        }

        .bank-info-footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px dashed #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
        }

        .print-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #111827;
            color: #fff;
            padding: 10px 25px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            z-index: 9999;
        }

        .nav-bar {
            background: #ffffff;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 20px;
        }

        .print-actions { display: flex; gap: 10px; }
        .print-action {
            display: inline-block;
            padding: 10px 20px;
            border: 0;
            border-radius: 8px;
            color: #ffffff;
            text-decoration: none;
            font: inherit;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }
        .print-action-secondary { background: #1e293b; }
        .print-action-success { background: #166534; }
        .print-action-info { background: #1e3a8a; padding-inline: 25px; }

        @page {
            margin: 10mm;
        }

        @media print {
            .no-print, .print-btn, .nav-bar { display: none !important; }
            html, body { margin: 0; padding: 0; background-color: white !important; }
            .invoice-wrapper { border: none; max-width: 100%; width: 100%; padding: 8mm; box-sizing: border-box; }
        }
    </style>
</head>
<body>
@php
    $isOwnerContext = auth('web')->check() && !auth('accountant')->check();
    $sale = $invoice->sale;
    $store = optional($sale)->store;
    $storeId = $sale->store_id ?? request()->route('store');
    $bankAccountsInfo = optional($store)->bank_accounts_info;
    $saleItems = collect(optional($sale)->items ?? []);
    $serviceDescriptionRaw = $invoice->description ?? $invoice->notes ?? optional($sale)->description ?? 'أجور يد وتركيب';
    $serviceDescription = trim((string) preg_replace('/\s*\(الكمية:\s*.*?=\s*[\d\.,]+\s*ر\.س\)/u', '', (string) $serviceDescriptionRaw));
    $serviceDescription = trim((string) preg_replace('/^وصف العمل:\s*[-–—]?\s*/u', '', $serviceDescription));
    if ($serviceDescription === '') {
        $serviceDescription = 'أجور يد وتركيب';
    }
    $saleTypeLabel = \App\Support\PaymentTypeLabel::invoiceLabel(optional($sale)->sale_type);
    $homeRoute = $isOwnerContext ? route('user.dashboard') : route('accountant.dashboard');
    $pdfRoute = $isOwnerContext
        ? route('user.invoices.pdf', ['invoice' => $invoice->id])
        : route('accountant.quick-sale.invoice.pdf', $invoice->id);
@endphp

@if(empty($forPdf))
<div class="nav-bar no-print" dir="rtl">
    <div class="print-actions">
        <a href="{{ $homeRoute }}" class="print-action print-action-secondary">
            🏠 الرئيسية
        </a>
    </div>
    <div class="print-actions">
        <a href="{{ $pdfRoute }}" class="print-action print-action-success">
            📥 تحميل PDF
        </a>
        {{-- إصلاح مطبق: أزرار طباعة البيع السريع تستخدم عقد الطباعة المشترك. --}}
        <button type="button" data-ui-print class="print-action print-action-info">
            🖨️ طباعة الآن
        </button>
    </div>
</div>
@endif

<div class="invoice-wrapper">
    <div class="header-section">
        <div class="store-info">
            <h1>{{ $store->name ?? 'اسم المتجر غير متوفر' }}</h1>
            <div class="store-details">
                <p>العنوان: {{ $store->address ?? 'غير محدد' }}</p>
                @if(!empty($store->commercial_registration ?? $store->commercial_register ?? null))
                    <p>السجل التجاري: {{ $store->commercial_registration ?? $store->commercial_register }}</p>
                @endif
                <p>الهاتف: {{ $store->phone ?? 'غير مسجل' }}</p>
                @if(!empty($store->tax_number))
                    <p>الرقم الضريبي للمتجر: <strong>{{ $store->tax_number }}</strong></p>
                @endif
            </div>
        </div>
        <div class="qr-section">
            <div class="qr-box">
                {{-- نستخدم QrCodeSvg بدل QrCode:: مباشرة لتجنب خطأ Class "QrCode" not found عند غياب الحزمة الخارجية. --}}
                <img src="{{ \App\Support\QrCodeSvg::size(130)->toDataUri($invoice->zatca_qr_code) }}" alt="ZATCA QR" style="width:130px;height:130px;display:block;" />
            </div>
            <p style="font-size: 11px; margin-top: 5px; font-weight: bold;">فاتورة ضريبية مبسطة</p>
        </div>
    </div>

    <div style="margin-bottom: 20px; font-size: 13px;">
        <span>رقم الفاتورة: <strong>#{{ $invoice->invoice_number }}</strong></span>
        <span style="margin-right: 20px;">التاريخ: <strong>{{ $invoice->created_at->format('Y/m/d H:i') }}</strong></span>
    </div>

    <div class="customer-vehicle-row">
        <div class="info-block" style="text-align: right;">
            <span class="info-label">بيانات العميل</span>
            <span class="info-value">{{ $invoice->customer_name }}</span>
            <span style="font-size: 12px; color: #64748b;">{{ $invoice->customer_phone }}</span>
            @if($invoice->tax_number)
                <span style="font-size: 11px; color: #64748b;">رقم ضريبي: {{ $invoice->tax_number }}</span>
            @endif
        </div>

        <div class="info-block" style="text-align: left;">
            <span class="info-label">بيانات المركبة</span>
            <span class="info-value">{{ $invoice->vehicle_type }}</span>
            <span style="font-size: 14px; color: #059669; font-weight: bold;">{{ $invoice->plate_number }}</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="text-align: right;">الوصف</th>
                <th style="text-align: center;">الكمية</th>
                <th style="text-align: center;">سعر الوحدة</th>
                <th style="text-align: center;">الضريبة</th>
                <th style="text-align: left;">الإجمالي (شامل)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($saleItems as $item)
            @php
                $item_tax = $item->total * ($invoice->tax_rate / 100);
            @endphp
            <tr>
                <td>
                    {{ optional($item->product)->name ?? $item->custom_name ?? 'بند بدون اسم' }}
                    @if($item->is_custom && $item->custom_name)
                        <br><small style="color: #64748b;">({{ $item->custom_name }})</small>
                    @endif
                </td>
                <td style="text-align: center;">
                    @if($item->is_custom && $item->custom_meters)
                        {{ number_format($item->custom_meters, 2) }} متر
                    @else
                        {{ $item->quantity }}
                    @endif
                </td>
                <td style="text-align: center;">{{ number_format($item->price, 2) }}</td>
                <td style="text-align: center;">{{ number_format($item_tax, 2) }}</td>
                <td style="text-align: left;">{{ number_format($item->total + $item_tax, 2) }}</td>
            </tr>
            @empty
            @endforelse

            @if((float) optional($sale)->labor_total > 0)
            <tr>
                <td>{{ $serviceDescription }}</td>
                <td style="text-align: center;">1</td>
                <td style="text-align: center;">{{ number_format((float) optional($sale)->labor_total, 2) }}</td>
                <td style="text-align: center;">0.00</td>
                <td style="text-align: left;">{{ number_format((float) optional($sale)->labor_total, 2) }}</td>
            </tr>
            @endif

            @if($saleItems->isEmpty() && (filled($invoice->description) || filled($invoice->notes)))
            <tr>
                <td>{{ $serviceDescription }}</td>
                <td style="text-align: center;">1</td>
                <td style="text-align: center;">{{ number_format((float) $invoice->subtotal, 2) }}</td>
                <td style="text-align: center;">{{ number_format((float) $invoice->tax_amount, 2) }}</td>
                <td style="text-align: left;">{{ number_format((float) $invoice->total_amount, 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="footer-flex">
        <div class="notes-area">
            <strong>ملاحظات الفاتورة:</strong><br>
            {{ $invoice->notes ?? 'لا توجد ملاحظات.' }}
            <div style="margin-top: 10px; color: #94a3b8; font-size: 10px;">
                * ضريبة القيمة المضافة تُطبق على قطع الغيار والمواد فقط (غير مطبقة على أجور اليد).
            </div>
        </div>

        <div class="totals-area">
            <div class="total-line">
                <span>المجموع (غير شامل):</span>
                <span>{{ number_format($invoice->subtotal, 2) }} ر.س</span>
            </div>
            <div class="total-line">
                <span>الضريبة ({{ $invoice->tax_rate }}%):</span>
                <span>{{ number_format($invoice->tax_amount, 2) }} ر.س</span>
            </div>
            <div class="grand-total-line">
                <span>الإجمالي النهائي:</span>
                <span>{{ number_format($invoice->total_amount, 2) }} ر.س</span>
            </div>
        </div>
    </div>

    @if(!empty($bankAccountsInfo))
        <div class="bank-info-footer">
            {{ $bankAccountsInfo }}
        </div>
    @endif

    @if($saleTypeLabel)
        <div style="margin-top: 20px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 10px;">
            طريقة الدفع: {{ $saleTypeLabel }}
        </div>
    @endif
</div>

@if(empty($forPdf))
<button type="button" class="print-btn" data-ui-print>طباعة الفاتورة 🖨️</button>
@endif

</body>
</html>
