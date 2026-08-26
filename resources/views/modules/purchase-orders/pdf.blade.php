
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Cairo', 'DejaVu Sans', Arial, sans-serif; direction: rtl; font-size: 12px; color: #172033; margin: 0; padding: 10px; }
        
        .header { border: 1px solid #d7deea; border-radius: 8px; padding: 10px; background: #f8fafc; margin-bottom: 10px }
        .brand { font-size: 20px; font-weight: 800; color: #0f766e; }
        .title { font-size: 16px; font-weight: 800; margin: 5px 0; }
        
        .meta { width: 100%; border-collapse: collapse; margin-top: 5px }
        .meta td { padding: 5px; border: 1px solid #e5e7eb }
        
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th { background: #0f766e; color: #fff; padding: 8px; border: 1px solid #0f766e; font-size: 11px; text-align: center; }
        table.items td { padding: 8px; border: 1px solid #d8dee9; text-align: center; font-size: 11px; }
        
        .write-space { color: #ccc; }
        .footer { margin-top: 20px; text-align: center; color: #64748b; font-size: 10px; }
        
        .signatures { margin-top: 20px; width: 100%; }
        .signatures td { width: 50%; padding: 10px; text-align: center; }
        .text-right { text-align: right; }
        .total-label { text-align: left; font-weight: bold; background: #f8fafc; }
        .total-value { text-align: right; font-weight: bold; color: #0f766e; }
        .document-notes { margin-top: 25px; width: 100%; }
        .document-alert { margin-bottom: 15px; padding: 12px 15px; border: 1px solid #ef4444; border-radius: 8px; background: #fef2f2; }
        .document-alert-title { margin: 0 0 8px; color: #b91c1c; font-size: 13px; font-weight: bold; }
        .document-alert-list { margin: 0; padding-right: 20px; font-size: 11px; color: #7f1d1d; line-height: 1.8; }
        .document-help { padding: 12px 15px; border: 1px solid #22c55e; border-radius: 8px; background: #f0fdf4; }
        .document-help-title { margin: 0 0 8px; color: #15803d; font-size: 13px; font-weight: bold; }
        .document-help-list { margin: 0; padding-right: 20px; font-size: 11px; color: #166534; line-height: 1.8; }
    </style>
</head>
<body>
    @php
        $documentTypeLabel = ($isInventoryApprovalPdf ?? false) ? 'سجل اعتماد مخزني' : (($isReceiptPdf ?? false) ? 'سجل تأكيد الاستلام' : (($isReceiptWorksheet ?? false) ? 'مستند تأكيد الاستلام' : 'طلبية توريد'));
        $hidePrices = (bool) ($hidePrices ?? false);
        $storeOwnerName = $store->user?->name ?: 'غير محدد';
        $accountantName = $order->accountant?->name
            ?: ($store->accountants?->firstWhere('status', 'active')?->name ?: 'غير محدد');
    @endphp

    <div class="header">
        <div class="brand">CARLED</div>
        <div class="title">{{ $documentTypeLabel }} — {{ e($order->referenceCode()) }}</div>
        <table class="meta">
            <tr>
                <td>المتجر: {{ e($store->name ?? 'غير محدد') }}</td>
                <td>المورد: {{ e($order->supplier_name ?? 'غير محدد') }}</td>
                <td>التاريخ: {{ ($isInventoryApprovalPdf ?? false) ? (optional($order->approved_business_date)->format('Y-m-d') ?? optional($order->approved_at)->format('Y-m-d') ?? '---') : (optional($order->created_at)->format('Y-m-d') ?? '---') }}</td>
            </tr>
            <tr>
                <td>مالك المتجر: {{ e($storeOwnerName) }}</td>
                <td>المحاسب: {{ e($accountantName) }}</td>
                <td>نوع الوثيقة: {{ $documentTypeLabel }}</td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>المنتج</th>
                @if($isInventoryApprovalPdf ?? false)
                    <th>الزيادة</th>
                    <th>المخزون قبل</th>
                    <th>المخزون بعد</th>
                    @unless($hidePrices)
                        <th>التكلفة قبل</th>
                        <th>التكلفة بعد</th>
                    @endunless
                    <th>نتيجة الاعتماد</th>
                @else
                    <th>{{ ($isReceiptPdf ?? false) ? 'الكمية المستلمة' : 'الكمية المطلوبة' }}</th>
                    @unless($hidePrices)
                        <th>{{ ($isReceiptPdf ?? false) ? 'سعر الاستلام المعتمد' : 'تكلفة الطلب' }}</th>
                    @endunless
                @endif
                @if(!($isReceiptPdf ?? false) && !($isInventoryApprovalPdf ?? false))
                    <th>المستلمة</th>
                    @unless($hidePrices)<th>السعر</th>@endunless
                @endif
                <th>ملاحظات</th>
                @if(!($isInventoryApprovalPdf ?? false))
                    <th>الجرد</th>
                @endif
            </tr>
        </thead>
        <tbody>
        @foreach($order->items as $index => $item)
                   @php
                $currentUnit = $item->unit_type;
                if (!$currentUnit && $item->product) {
                    if ((($item->product->product_type ?? null) === 'fractional') || (float) $item->product->roll_length > 0) {
                        $currentUnit = 'roll';
                    } elseif ($item->product->is_splittable) {
                        $currentUnit = 'kit';
                    } else {
                        $currentUnit = 'piece';
                    }
                }
                $unitLabel = in_array($currentUnit, ['meter','meters']) ? 'متر' : ($currentUnit === 'piece' ? 'حبة' : ($currentUnit === 'roll' ? 'رول' : ($currentUnit === 'kit' ? 'طقم' : '')));
                $usesReceiptValues = ($isReceiptPdf ?? false) || ($isInventoryApprovalPdf ?? false);
                $displayQuantity = $usesReceiptValues
                    ? ($item->quantity_received ?? $item->quantity_requested)
                    : $item->quantity_requested;
                $displayCost = $usesReceiptValues
                    ? ($item->cost_price_at_receipt ?? $item->cost_price_at_order)
                    : $item->cost_price_at_order;
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td class="text-right">{{ e($item->productName()) }}</td>
                @if($isInventoryApprovalPdf ?? false)
                    @if($item->add_to_owner_purchases)
                        <td colspan="{{ $hidePrices ? 3 : 5 }}">مشتريات مالك: {{ number_format((float) $displayQuantity, 2) }} {{ $unitLabel }}@unless($hidePrices) بتكلفة {{ number_format((float) $displayCost, 2) }}@endunless</td>
                        <td>{{ $item->owner_purchase_id ? 'تم تسجيل مشتريات مالك' : ((float) $displayQuantity > 0 ? 'لم يحفظ سجل المشتريات' : 'لم يعتمد - كمية صفر') }}</td>
                    @else
                        <td>{{ (float)$displayQuantity > 0 ? number_format((float) $displayQuantity, 2) : '---' }} <span class="unit-text">{{ $unitLabel }}</span></td>
                        <td>{{ $item->stock_quantity_before !== null ? number_format((float) $item->stock_quantity_before, 2) : '---' }}</td>
                        <td>{{ $item->stock_quantity_after !== null ? number_format((float) $item->stock_quantity_after, 2) : '---' }}</td>
                        @unless($hidePrices)
                            <td>{{ $item->cost_price_before !== null ? number_format((float) $item->cost_price_before, 2) : '---' }}</td>
                            <td>{{ $item->cost_price_after !== null ? number_format((float) $item->cost_price_after, 2) : '---' }}</td>
                        @endunless
                        <td>
                            @if((float) $displayQuantity <= 0)
                                لم يعتمد - كمية صفر
                            @elseif($item->stock_quantity_before !== null && $item->stock_quantity_after !== null && $item->cost_price_before !== null && $item->cost_price_after !== null)
                                تمت إضافة المخزون
                            @else
                                لم يحفظ سجل الاعتماد
                            @endif
                        </td>
                    @endif
                @else
                    <td>{{ (float)$displayQuantity > 0 ? number_format((float) $displayQuantity, 2) : '---' }}
                        <span class="unit-text">{{ $unitLabel }}</span>
                    </td>
                    @unless($hidePrices)<td>{{ number_format((float)$displayCost, 2) }}</td>@endunless
                @endif
                @if(!($isReceiptPdf ?? false) && !($isInventoryApprovalPdf ?? false))
                    <td class="write-space">____</td>
                    @unless($hidePrices)<td class="write-space">____</td>@endunless
                @endif
                <td class="">
                    <!-- {{ $item->receipt_notes ?: '____' }} -->
                    
                 <span dir="rtl">{{ $customNotes[$item->id] ?? '' }}</span>
                </td> 
                @if(!($isInventoryApprovalPdf ?? false))
                    <td class="write-space">____</td>
                @endif
            </tr>
        @endforeach
        </tbody>
        @unless($hidePrices)
        <tfoot>
            <tr>
                <td colspan="3" class="total-label">{{ ($isInventoryApprovalPdf ?? false) ? 'إجمالي ما تم اعتماده مخزنيًا' : (($isReceiptPdf ?? false) ? 'إجمالي تكلفة الاستلام المعتمدة' : 'إجمالي التكلفة التقديرية') }}</td>
                <td colspan="{{ ($isInventoryApprovalPdf ?? false) ? 6 : (($isReceiptPdf ?? false) ? 3 : 5) }}" class="total-value">
                    {{ number_format($totalCost, 2) }}
                </td>
            </tr>
        </tfoot>
        @endunless
    </table>
<table class="signatures">
        <tr>
            <td>توقيع المستلم: ..........................</td>
            <td>توقيع الموزع: ..........................</td>
        </tr>
    </table>

    <!-- الحسبة الذكية المطورة لحساب أسطر النصوص الطويلة -->
    @php
        $virtualRows = 0;

        foreach($order->items as $item) {
            $rowHeight = 1; // كل صنف يبدأ كسطر واحد أساسي
            
            // فحص طول اسم المنتج: إذا تجاوز 30 حرفاً، نعتبر أنه سينقسم لسطر إضافي
            $productNameLength = mb_strlen($item->productName() ?? '');
            if ($productNameLength > 30) {
                $rowHeight += floor($productNameLength / 30);
            }

            // فحص طول الملاحظات الكلية: عمود الملاحظات أضيق، لذا إذا تجاوز 22 حرفاً يحسب سطر إضافي
            $noteText = $customNotes[$item->id] ?? '';
            $noteLength = mb_strlen($noteText);
            if ($noteLength > 22) {
                $rowHeight += floor($noteLength / 22);
            }
            
            // إضافة وزن هذا الصنف إلى مجموع الأسطر الكلية
            $virtualRows += $rowHeight;
        }

        // أبعاد الصفحات بناءً على الأسطر الفعلية للنصوص
        $firstPageMax = 28;     
        $otherPagesMax = 31;    
        $safeLimitForTerms = 15; // جعلنا الحد الآمن 15 سطر لترك هامش احتياطي مريح للإطارات
        
        $showTerms = false;
        
        if ($virtualRows <= $safeLimitForTerms) {
            $showTerms = true;
        } elseif ($virtualRows > $firstPageMax) {
            // حساب المساحة المتبقية بناءً على الأسطر الافتراضية الذكية
            $rowsInLastPage = ($virtualRows - $firstPageMax) % $otherPagesMax;
            
            if ($rowsInLastPage == 0 || $rowsInLastPage <= $safeLimitForTerms) {
                $showTerms = true;
            }
        }
    @endphp

    <!-- عرض الشروط والتوضيحات بناءً على أسطر النصوص الحقيقية -->
    @if($showTerms)
    <div class="document-notes">
        
        <!-- الإطار الأول (الأحمر) : تنبيه هام -->
        <div class="document-alert">
            <h4 class="document-alert-title">تنبيه هام:</h4>
            <ul class="document-alert-list">
                @if($isInventoryApprovalPdf ?? false)
                    <li>هذه الوثيقة رسمية ويجب الحفاظ عليها.</li>
                    <li>تعتبر هذه الوثيقة كإثبات توريد المنتجات إلى المخزن.</li>
                    <li>نظام CARLED لا يتحمل أي مسؤولية ناتجة عن هذه الورقة أو أي استخدام خارجي لها.</li>
                    <li>البيانات الموضحة فيها قابلة للمراجعة.</li>
                    <li>أي كشط أو تعديل على الوثيقة يجعلها لاغية.</li>
                @elseif($isReceiptPdf ?? false)
                    <li>هذه نسخة من بيانات الاستلام المحفوظة في النظام.</li>
                    <li>راجع الكميات والتكاليف مع الطلبية وفاتورة المورد قبل الاعتماد المخزني.</li>
                    <li>هذه الوثيقة لا تعني أن المخزون تم تحديثه؛ التحديث يحدث عند الاعتماد المخزني النهائي فقط.</li>
                @else
                    <li>هذه الوثيقة رسمية ويجب الحفاظ عليها وتسليمها بعد تعبئة البيانات للمالك.</li>
                    <li>يجب الانتباه والتدقيق بعناية عند الاستلام من الموزع أو المورد.</li>
                    @if($isReceiptWorksheet ?? false)<li>المطلوب من المحاسب: تسجيل الكمية المستلمة، تحديد الوحدة الفعلية (حبة/طقم أو متر/رول)، وكتابة تكلفة المورد عند اختلافها.</li>@endif
                    <li>يراجع المالك الكميات والوحدات والأسعار بعد تأكيد المحاسب للاستلام.</li>
                @endif
            </ul>
        </div>

        @unless(($isInventoryApprovalPdf ?? false) || $hidePrices)
            <!-- الإطار الثاني (الأخضر) : توضيح للوثيقة -->
            <div class="document-help">
                <h4 class="document-help-title">تنبيهات وتوضيح للجدول:</h4>
                <ul class="document-help-list">
                    <li><strong>العمود الأول:</strong> يعرض اسم المنتج المطلوب من المورد.</li>
                    <li><strong>العمود الثاني:</strong> يعرض الكمية التي تم طلبها مع تحديد نوع الوحدة (حبة، طقم، رول).</li>
                    <li><strong>العمود الثالث:</strong> يعرض إجمالي سعر التكلفة للكمية المطلوبة والمعتمد مسبقاً لدى المتجر.</li>
                    <li><strong>العمودان الرابع والخامس:</strong> يدوّن فيهما مسؤول المتجر الكمية التي استلمها فعلياً وإجمالي تكلفة الشراء من المورد.</li>
                    <li><strong>العمود السادس:</strong> يحتوي على الملاحظات (كالقياسات، أو الألوان، أو أي ملاحظات تمت إضافتها وقت تسجيل طلب التوريد).</li>
                </ul>
            </div>
        @endunless

    </div>
    @endif
    <div class="footer">
        تم إصدار هذه الوثيقه بواسطة نظام CARLED 
    </div>
</body>
</html>
