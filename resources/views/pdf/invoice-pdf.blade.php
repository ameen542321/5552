<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Cairo', 'Amiri', 'DejaVu Sans', sans-serif; /* 2026-05-16: خط عربي مناسب لتوليد PDF عبر mPDF */
            direction: rtl;
            text-align: right;
            font-size: 12px;
            color: #1a202c;
        }
        .invoice-wrapper {
            padding: 20px;
        }
        /* رأس الفاتورة باستخدام الجداول لضمان التموضع */
        .header-table {
            width: 100%;
            border-bottom: 2px solid #111827;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        .store-info h1 { margin: 0; font-size: 20px; }
        .store-details p { margin: 2px 0; font-size: 11px; color: #4b5563; }

        /* بيانات العميل والمركبة */
        .info-container {
            width: 100%;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }
        .info-table {
            width: 100%;
            padding: 10px;
        }
        .info-label { font-size: 10px; color: #64748b; font-weight: bold; }
        .info-value { font-size: 13px; font-weight: bold; display: block; }

        /* جدول الأصناف */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background: #f1f5f9;
            padding: 10px;
            border-top: 2px solid #111827;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
        }
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #f1f5f9;
            text-align: center;
        }

        /* المخلص المالي */
        .footer-section { width: 100%; }
        .notes-column { width: 55%; vertical-align: top; border-left: 2px solid #e2e8f0; padding-left: 10px; }
        .totals-column { width: 40%; vertical-align: top; }

        .total-line { width: 100%; margin-bottom: 5px; }
        .grand-total {
            border-top: 2px solid #111827;
            padding-top: 8px;
            font-weight: bold;
            font-size: 16px;
        }

        .bank-info {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
            border-top: 1px dashed #e5e7eb;
            padding-top: 10px;
        }
    </style>
</head>
<body>
@php
    $sale = $invoice->sale;
    $store = optional($sale)->store;
    $saleItems = collect(optional($sale)->items ?? []);
    $serviceDescriptionRaw = $invoice->description ?? $invoice->notes ?? optional($sale)->description ?? 'أجور يد وتركيب';
    $serviceDescription = trim((string) preg_replace('/\s*\(الكمية:\s*.*?=\s*[\d\.,]+\s*ر\.س\)/u', '', (string) $serviceDescriptionRaw));
    $serviceDescription = trim((string) preg_replace('/^وصف العمل:\s*[-–—]?\s*/u', '', $serviceDescription));
    if ($serviceDescription === '') {
        $serviceDescription = 'أجور يد وتركيب';
    }
@endphp

<div class="invoice-wrapper">
    <table class="header-table">
        <tr>
            <td style="width: 70%;">
                <div class="store-info">
                    <h1>{{ $store->name ?? 'اسم المتجر غير متوفر' }}</h1>
                    <div class="store-details">
                        <p>العنوان: {{ $store->address ?? 'غير محدد' }}</p>
                        @if(!empty($store->commercial_registration ?? $store->commercial_register ?? null))
                            <p>السجل التجاري: {{ $store->commercial_registration ?? $store->commercial_register }}</p>
                        @endif
                        <p>الهاتف: {{ $store->phone ?? 'غير مسجل' }}</p>
                        @if(!empty($store->tax_number))
                            <p>الرقم الضريبي: <strong>{{ $store->tax_number }}</strong></p>
                        @endif
                    </div>
                </div>
            </td>
            <td style="width: 30%; text-align: left;">
                {{-- الباركود بصيغة SVG لضمان العمل بدون Imagick --}}
                {{-- نستخدم QrCodeSvg بدل QrCode:: مباشرة لتجنب خطأ Class "QrCode" not found عند غياب الحزمة الخارجية. --}}
                <img src="{{ \App\Support\QrCodeSvg::size(130)->toDataUri($invoice->zatca_qr_code) }}" alt="ZATCA QR" style="width:130px;height:130px;display:inline-block;" />
                <div style="font-weight: bold; font-size: 10px; margin-top: 5px;">فاتورة ضريبية مبسطة</div>
            </td>
        </tr>
    </table>

    <div style="margin-bottom: 15px;">
        <span>رقم الفاتورة: <strong>#{{ $invoice->invoice_number }}</strong></span>
        <span style="margin-right: 30px;">التاريخ: <strong>{{ $invoice->created_at->format('Y/m/d H:i') }}</strong></span>
    </div>

    <div class="info-container">
        <table class="info-table">
            <tr>
                <td style="width: 50%;">
                    <span class="info-label">بيانات العميل</span><br>
                    <span class="info-value">{{ $invoice->customer_name }}</span>
                    <span style="font-size: 11px;">{{ $invoice->customer_phone }}</span>
                    @if($invoice->tax_number)
                        <br><span style="font-size: 10px; color: #64748b;">رقم ضريبي: {{ $invoice->tax_number }}</span>
                    @endif
                </td>
                <td style="width: 50%; text-align: left;">
                    <span class="info-label">بيانات المركبة</span><br>
                    <span class="info-value">{{ $invoice->vehicle_type }}</span>
                    <span style="color: #059669; font-weight: bold;">{{ $invoice->plate_number }}</span>
                </td>
            </tr>
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="text-align: right;">الوصف</th>
                <th>الكمية</th>
                <th>سعر الوحدة</th>
                <th>الضريبة</th>
                <th style="text-align: left;">الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @forelse($saleItems as $item)
            <tr>
                <td style="text-align: right;">
                    {{ optional($item->product)->name ?? $item->custom_name ?? 'بند بدون اسم' }}
                    @if($item->is_custom && $item->custom_name)
                        <br><small style="color: #64748b;">({{ $item->custom_name }})</small>
                    @endif
                </td>
                <td>
                    @if($item->is_custom && $item->custom_meters)
                        {{ number_format($item->custom_meters, 2) }} متر
                    @else
                        {{ $item->quantity }}
                    @endif
                </td>
                <td>{{ number_format($item->price, 2) }}</td>
                <td>{{ number_format($item->total * ($invoice->tax_rate / 100), 2) }}</td>
                <td style="text-align: left;">{{ number_format($item->total + ($item->total * ($invoice->tax_rate / 100)), 2) }}</td>
            </tr>
            @empty
            @endforelse

            @if((float) optional($sale)->labor_total > 0)
            <tr>
                <td style="text-align: right;">{{ $serviceDescription }}</td>
                <td>1</td>
                <td>{{ number_format((float) optional($sale)->labor_total, 2) }}</td>
                <td>0.00</td>
                <td style="text-align: left;">{{ number_format((float) optional($sale)->labor_total, 2) }}</td>
            </tr>
            @endif

            @if($saleItems->isEmpty() && (filled($invoice->description) || filled($invoice->notes)))
            <tr>
                <td style="text-align: right;">{{ $serviceDescription }}</td>
                <td>1</td>
                <td>{{ number_format((float) $invoice->subtotal, 2) }}</td>
                <td>{{ number_format((float) $invoice->tax_amount, 2) }}</td>
                <td style="text-align: left;">{{ number_format((float) $invoice->total_amount, 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <table style="width: 100%;">
        <tr>
            <td class="notes-column">
                <strong>ملاحظات:</strong><br>
                <div style="margin-top: 5px;">{{ $invoice->notes ?? 'لا توجد ملاحظات.' }}</div>
                <div style="margin-top: 15px; color: #94a3b8; font-size: 10px;">
                    * ضريبة القيمة المضافة تُطبق على قطع الغيار والمواد فقط.
                </div>
            </td>
            <td class="totals-column">
                <table style="width: 100%;">
                    <tr>
                        <td>المجموع الصافي:</td>
                        <td style="text-align: left;">{{ number_format($invoice->subtotal, 2) }} ر.س</td>
                    </tr>
                    <tr>
                        <td>الضريبة ({{ $invoice->tax_rate }}%):</td>
                        <td style="text-align: left;">{{ number_format($invoice->tax_amount, 2) }} ر.س</td>
                    </tr>
                    <tr class="grand-total">
                        <td>الإجمالي:</td>
                        <td style="text-align: left;">{{ number_format($invoice->total_amount, 2) }} ر.س</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @php
        $saleTypeLabel = match(optional($sale)->sale_type) {
            'cash' => 'نقداً',
            'card' => 'شبكة',
            'credit' => 'آجل',
            'mixed' => 'مختلط',
            default => null,
        };
        $bankAccountsInfo = optional(optional($sale)->store)->bank_accounts_info;
    @endphp

    @if(!empty($bankAccountsInfo))
        <div class="bank-info">
            {{ $bankAccountsInfo }}
        </div>
    @endif

    @if($saleTypeLabel)
        <div style="margin-top: 15px; text-align: center; font-size: 10px; color: #94a3b8;">
            طريقة الدفع: {{ $saleTypeLabel }}
        </div>
    @endif
</div>

</body>
</html>
