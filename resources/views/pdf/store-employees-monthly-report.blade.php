
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Cairo, 'DejaVu Sans', Amiri, sans-serif; direction: rtl; font-size: 12px; color: #172033; margin: 0; padding: 10px; }
        
        .header { border: 1px solid #d7deea; border-radius: 8px; padding: 10px; background: #f8fafc; margin-bottom: 10px }
        .brand { font-size: 20px; font-weight: 800; color: #0f766e; }
        .title { font-size: 16px; font-weight: 800; margin: 5px 0; }
        
        .meta { width: 100%; border-collapse: collapse; margin-top: 5px }
        .meta td { padding: 5px; border: 1px solid #e5e7eb }
        
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th { background: #0f766e; color: #fff; padding: 8px; border: 1px solid #0f766e; font-size: 11px; text-align: center; }
        table.items td { padding: 8px; border: 1px solid #d8dee9; text-align: center; font-size: 11px; }
        
        .write-space { color: #ccc; }
        .footer { margin-top: 20px; text-align: center; color: #64748b; font-size: 11px; }
        
        .signatures { margin-top: 20px; width: 100%; }
        .signatures td { width: 50%; padding: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">CARLED</div>
        <div class="title">سجل استلام طلبية رقم {{ e($order->id ?? 'N/A') }}</div>
        <table class="meta">
            <tr>
                <td>المتجر: {{ e($store->name ?? 'غير محدد') }}</td>
                <td>المورد: {{ e($order->supplier_name ?? 'غير محدد') }}</td>
                <td>التاريخ: {{ optional($order->created_at)->format('Y-m-d') ?? '---' }}</td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>المنتج</th>
                <th>الكمية</th>
                <th>التكلفة</th>
                <th>المستلمة</th>
                <th>السعر</th>
                <th>ملاحظات</th>
                <th>الجرد</th>
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
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td style="text-align: right;">{{ e($item->productName()) }}</td>
                <td>{{ (float)$item->quantity_requested > 0 ? number_format($item->quantity_requested, 2) : '---' }}
                    <span class="unit-text">{{ $unitLabel }}</span>
                </td>
                <td>{{ number_format((float)$item->cost_price_at_order, 2) }}</td>
                @if(in_array($order->status, ['received', 'approved'], true))
                    <td>{{ $item->quantity_received !== null ? number_format((float) $item->quantity_received, 2) : '---' }}</td>
                    <td>{{ $item->cost_price_at_receipt !== null ? number_format((float) $item->cost_price_at_receipt, 2) : '---' }}</td>
                @else
                    <td class="write-space">____</td>
                    <td class="write-space">____</td>
                @endif
                <td class="">
                    <!-- {{ $item->receipt_notes ?: '____' }} -->
                    
                 <span dir="rtl">{{ $customNotes[$item->id] ?? '' }}</span>
                </td> 
                <td class="write-space">____</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align: left; font-weight: bold; background: #f8fafc;">إجمالي التكلفة التقديرية</td>
                <td colspan="5" style="text-align: right; font-weight: bold; color: #0f766e;">
                    {{ number_format($totalCost, 2) }}
                </td>
            </tr>
        </tfoot>
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
    <div style="margin-top: 25px; width: 100%;">
        
        <!-- الإطار الأول (الأحمر) : تنبيه هام -->
        <div style="margin-bottom: 15px; padding: 12px 15px; border: 1px solid #ef4444; border-radius: 8px; background-color: #fef2f2;">
            <h4 style="margin: 0 0 8px 0; color: #b91c1c; font-size: 13px; font-weight: bold;">تنبيه هام:</h4>
            <ul style="margin: 0; padding-right: 20px; font-size: 11px; color: #7f1d1d; line-height: 1.8;">
                <li>هذه الوثيقة رسمية ويجب الحفاظ عليها وتسليمها بعد تعبئة البيانات للمالك.</li>
                <li>يجب الانتباة والتدقيق بعناية عند الاستلام من الموزع أو المورد.</li>
                <li>كل ما يُكتب في حقول هذه الوثيقة سيكون بمثابة أرقام نهائية وجاهزة للاعتماد المخزني.</li>
            </ul>
        </div>

        <!-- الإطار الثاني (الأخضر) : توضيح للوثيقة -->
        <div style="padding: 12px 15px; border: 1px solid #22c55e; border-radius: 8px; background-color: #f0fdf4;">
            <h4 style="margin: 0 0 8px 0; color: #15803d; font-size: 13px; font-weight: bold;">تنبيهات وتوضيح للجدول:</h4>
            <ul style="margin: 0; padding-right: 20px; font-size: 11px; color: #166534; line-height: 1.8;">
                <li><strong>العمود الأول:</strong> يعرض اسم المنتج المطلوب من المورد.</li>
                <li><strong>العمود الثاني:</strong> يعرض الكمية التي تم طلبها مع تحديد نوع الوحدة (حبة، طقم، رول).</li>
                <li><strong>العمود الثالث:</strong> يعرض إجمالي سعر التكلفة للكمية المطلوبة والمعتمد مسبقاً لدى المتجر.</li>
                <li><strong>العمودان الرابع والخامس:</strong> يدوّن فيهما مسؤول المتجر الكمية التي استلمها فعلياً وإجمالي تكلفة الشراء من المورد.</li>
                <li><strong>العمود السادس:</strong> يحتوي على الملاحظات (كالقياسات، أو الألوان، أو أي ملاحظات تمت إضافتها وقت تسجيل طلب التوريد).</li>
                <li><strong>العمود السابع:</strong> يقوم مسؤول المتجر باحتساب وتسجيل الكمية المتوفرة في المتجر (الجرد) قبل استلام المنتج من المورد.</li>
            </ul>
        </div>

    </div>
    @endif
    <div class="footer">
        تم إصدار هذه الوثيقه بواسطة نظام CARLED 
    </div>
</body>
</html>
