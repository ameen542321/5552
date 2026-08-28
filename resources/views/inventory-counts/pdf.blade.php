<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><style>
body{font-family:Cairo,'DejaVu Sans',sans-serif;font-size:12px;color:#172033}h1{font-size:18px}table{width:100%;border-collapse:collapse;margin-top:14px}th,td{border:1px solid #cbd5e1;padding:8px;text-align:right}th{background:#0f766e;color:#fff}.meta{background:#f8fafc;padding:10px;border:1px solid #cbd5e1}.blank{height:26px}.footer{margin-top:18px;text-align:center;color:#475569}
</style></head><body>
<h1>ورقة جرد — {{ $session->referenceCode() }}</h1>
<div class="meta">المتجر: {{ $store->name }} | المحاسب: {{ $session->accountant?->name ?: 'غير محدد' }} | تاريخ ووقت إصدار الملف: {{ $issuedAt->format('Y-m-d H:i') }}</div>
<table><thead><tr><th>#</th><th>المنتج</th><th>الوصف</th><th>نوع الجرد</th><th>الوحدة</th><th>الكمية</th><th>الملاحظات</th></tr></thead><tbody>
@foreach($session->items as $item)<tr><td>{{ $loop->iteration }}</td><td>{{ $item->product_name_snapshot }}</td><td>{{ $item->product_description_snapshot ?: '—' }}</td><td>{{ $item->count_type === 'periodic' ? 'دوري' : $item->count_type }}</td><td>{{ ['piece'=>'حبة','kit'=>'طقم','meter'=>'متر','roll'=>'رول','unit'=>'وحدة'][$item->unit_type] ?? $item->unit_type }}</td><td class="blank"></td><td class="blank"></td></tr>@endforeach
</tbody></table><div class="footer">توقيع المحاسب: .......................... &nbsp;&nbsp;&nbsp; توقيع صاحب المتجر: ..........................</div></body></html>
