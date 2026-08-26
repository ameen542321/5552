<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Cairo', 'Amiri', 'DejaVu Sans', sans-serif; /* 2026-05-16: اعتماد Amiri أولاً ثم Cairo لتوليد PDF عبر mPDF */
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
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 12px 14px;
            vertical-align: top;
        }
        .info-table td + td {
            border-right: 1px solid #e2e8f0;
        }
        .info-label { font-size: 10px; color: #64748b; font-weight: bold; }
        .info-value { font-size: 14px; font-weight: bold; display: block; margin-top: 3px; }
        .info-sub { font-size: 11px; color: #374151; display: block; margin-top: 4px; }
        .info-list {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .info-list td {
            padding: 6px 0;
            border: 0;
            font-size: 12px;
        }
        .info-list .field-label {
            color: #64748b;
            font-weight: bold;
            width: 42%;
        }
        .info-list .field-value {
            color: #111827;
            font-weight: bold;
            text-align: left;
        }
        .section-title {
            display: inline-block;
            background: #e2e8f0;
            color: #0f172a;
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 999px;
            margin-bottom: 8px;
        }
        .dotted-separator {
            border-top: 1px dashed #cbd5e1;
            margin: 8px 0 4px;
        }

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

        .print-actions { margin-bottom: 12px; text-align: left; }
        .print-action {
            display: inline-block;
            margin-left: 6px;
            padding: 8px 12px;
            border: 0;
            border-radius: 6px;
            color: #ffffff;
            text-decoration: none;
            font: inherit;
            font-weight: bold;
            cursor: pointer;
        }
        .print-action-secondary { background: #1e293b; }
        .print-action-info { background: #1e3a8a; }
        .print-action-success { background: #166534; }

        @page {
            margin: 10mm;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            html, body {
                margin: 0;
                padding: 0;
            }

            .invoice-wrapper {
                padding: 8mm;
                box-sizing: border-box;
            }
        }
    </style>
</head>
<body>

<div class="invoice-wrapper">
    @if(empty($forPdf))
    <div class="no-print print-actions">
        <a href="{{ auth('web')->check() && optional($invoice->sale)->store_id ? route('user.stores.invoices.index', optional($invoice->sale)->store_id) : route('accountant.invoices.index') }}"
           class="print-action print-action-secondary">
            الرجوع لصفحة الفواتير
        </a>
        <a href="{{ auth('web')->check() && optional($invoice->sale)->store_id ? route('user.stores.invoices.pdf', [optional($invoice->sale)->store_id, $invoice->id]) : route('accountant.quick-sale.invoice.pdf', $invoice->id) }}"
           class="print-action print-action-info">
            تحميل PDF
        </a>
        {{-- إصلاح مطبق: الطباعة تستخدم عقد الإجراء المشترك بدل onclick المضمّن. --}}
        <button type="button" data-ui-print class="print-action print-action-success">
            طباعة الفاتورة
        </button>
    </div>
    @endif

    <table class="header-table">
        <tr>
            <td style="width: 70%;">
                <div class="store-info">
                    <h1>{{ optional(optional($invoice->sale)->store)->name ?? 'المتجر' }}</h1>
                    <div class="store-details">
                        <p>العنوان: {{ optional(optional($invoice->sale)->store)->address ?? 'غير محدد' }}</p>
                        @if(!empty(optional(optional($invoice->sale)->store)->commercial_registration))
                            <p>السجل التجاري: {{ optional(optional($invoice->sale)->store)->commercial_registration }}</p>
                        @endif
                        <p>الهاتف: {{ optional(optional($invoice->sale)->store)->phone ?? 'غير مسجل' }}</p>
                        @if(!empty(optional(optional($invoice->sale)->store)->tax_number))
                            <p>الرقم الضريبي: <strong>{{ optional(optional($invoice->sale)->store)->tax_number }}</strong></p>
                        @endif
                    </div>
                </div>
            </td>
            <td style="width: 30%; text-align: left;">
                {{-- الباركود بصيغة SVG لضمان العمل بدون Imagick --}}
                {{-- نستخدم QrCodeSvg بدل QrCode:: مباشرة لتجنب خطأ Class "QrCode" not found عند غياب الحزمة الخارجية. --}}
                <img src="{{ \App\Support\QrCodeSvg::size(130)->toDataUri($invoice->zatca_qr_code ?? ($invoice->invoice_number ?? 'invoice')) }}" alt="ZATCA QR" style="width:130px;height:130px;display:inline-block;" />
                <div style="font-weight: bold; font-size: 10px; margin-top: 5px;">فاتورة ضريبية مبسطة</div>
            </td>
        </tr>
    </table>

    <div style="margin-bottom: 15px;">
        <span>رقم الفاتورة: <strong>#{{ $invoice->invoice_number }}</strong></span>
        <span style="margin-right: 30px;">التاريخ: <strong>{{ optional($invoice->created_at)->format('Y/m/d H:i') }}</strong></span>
    </div>

    <div class="info-container">
        <table class="info-table">
            <tr>
                <td style="width: 50%;">
                    <span class="section-title">بيانات العميل</span>
                    <div class="dotted-separator"></div>
                    <table class="info-list">
                        <tr>
                            <td class="field-label">اسم العميل</td>
                            <td class="field-value">{{ $invoice->customer_name ?? 'غير متوفر' }}</td>
                        </tr>
                        <tr>
                            <td class="field-label">الهاتف</td>
                            <td class="field-value">{{ $invoice->customer_phone ?? 'غير متوفر' }}</td>
                        </tr>
                        @if(!empty($invoice->tax_number))
                        <tr>
                            <td class="field-label">الرقم الضريبي</td>
                            <td class="field-value">{{ $invoice->tax_number }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
                <td style="width: 50%; text-align: right;">
                    <span class="section-title">بيانات المركبة</span>
                    <div class="dotted-separator"></div>
                    <table class="info-list">
                        <tr>
                            <td class="field-label">نوع السيارة</td>
                            <td class="field-value">{{ $invoice->vehicle_type ?? 'غير متوفر' }}</td>
                        </tr>
                        <tr>
                            <td class="field-label">رقم اللوحة</td>
                            <td class="field-value" style="color: #059669;">{{ $invoice->plate_number ?? 'غير متوفر' }}</td>
                        </tr>
                    </table>
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
            @php
                $manualDescriptionLines = collect(preg_split('/\r\n|\r|\n/', (string) ($invoice->description ?? '')))
                    ->map(fn ($line) => trim((string) $line))
                    ->filter()
                    ->reject(fn ($line) => in_array($line, ['وصف العمل:', 'المنتجات المدخلة:'], true))
                    ->map(function ($line) use ($invoice) {
                        $line = preg_replace('/^-\s*/u', '', $line);
                        $line = trim((string) $line);

                        preg_match('/^(.*?)\s*\(الكمية:\s*([0-9.,]+)\s*×\s*السعر:\s*([0-9.,]+)(?:\s*=\s*([0-9.,]+))?/u', $line, $matches);

                        $description = trim((string) ($matches[1] ?? $line));
                        $qty = (float) str_replace(',', '', (string) ($matches[2] ?? 1));
                        $unitPrice = (float) str_replace(',', '', (string) ($matches[3] ?? 0));
                        $lineSubtotal = $qty * $unitPrice;
                        $lineTax = $lineSubtotal * (((float) ($invoice->tax_rate ?? 0)) / 100);
                        $lineTotal = $lineSubtotal + $lineTax;

                        return [
                            'description' => $description,
                            'quantity' => $qty > 0 ? $qty : 1,
                            'unit_price' => $unitPrice,
                            'tax' => $lineTax,
                            'total' => $lineTotal,
                        ];
                    })
                    ->filter()
                    ->values();
            @endphp

            @forelse(optional($invoice->sale)->items ?? [] as $item)
            <tr>
                <td style="text-align: right;">{{ optional($item->product)->name ?? '—' }}</td>
                <td>{{ $item->quantity ?? 0 }}</td>
                <td>{{ number_format((float) ($item->price ?? 0), 2) }}</td>
                <td>{{ number_format((float) (($item->total ?? 0) * (($invoice->tax_rate ?? 0) / 100)), 2) }}</td>
                <td style="text-align: left;">{{ number_format((float) (($item->total ?? 0) + (($item->total ?? 0) * (($invoice->tax_rate ?? 0) / 100))), 2) }}</td>
            </tr>
            @empty
                @forelse($manualDescriptionLines as $line)
                <tr>
                    <td style="text-align: right;">{{ $line['description'] }}</td>
                    <td>{{ number_format((float) $line['quantity'], 0) }}</td>
                    <td>{{ number_format((float) $line['unit_price'], 2) }}</td>
                    <td>{{ number_format((float) $line['tax'], 2) }}</td>
                    <td style="text-align: left;">{{ number_format((float) $line['total'], 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">لا توجد أصناف</td>
                </tr>
                @endforelse
            @endforelse

            @if((optional($invoice->sale)->labor_total ?? 0) > 0)
            <tr>
                <td style="text-align: right;">{{ optional($invoice->sale)->description ?? 'أجور يد وتركيب' }}</td>
                <td>1</td>
                <td>{{ number_format((float) optional($invoice->sale)->labor_total, 2) }}</td>
                <td>0.00</td>
                <td style="text-align: left;">{{ number_format((float) optional($invoice->sale)->labor_total, 2) }}</td>
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
                        <td style="text-align: left;">{{ number_format((float) ($invoice->subtotal ?? 0), 2) }} ر.س</td>
                    </tr>
                    <tr>
                        <td>الضريبة ({{ $invoice->tax_rate ?? 0 }}%):</td>
                        <td style="text-align: left;">{{ number_format((float) ($invoice->tax_amount ?? 0), 2) }} ر.س</td>
                    </tr>
                    <tr class="grand-total">
                        <td>الإجمالي:</td>
                        <td style="text-align: left;">{{ number_format((float) ($invoice->total_amount ?? 0), 2) }} ر.س</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @php
        $bankAccountsInfo = optional(optional($invoice->sale)->store)->bank_accounts_info;
    @endphp

    @if(!empty($bankAccountsInfo))
        <div class="bank-info">
            {{ $bankAccountsInfo }}
        </div>
    @endif

    @php
        $paymentType = \App\Support\PaymentTypeLabel::invoiceLabel(optional($invoice->sale)->sale_type);
    @endphp
    @if($paymentType)
        <div style="margin-top: 15px; text-align: center; font-size: 10px; color: #94a3b8;">
            طريقة الدفع: {{ $paymentType }}
        </div>
    @endif
</div>

</body>
</html>
