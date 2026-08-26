@extends('dashboard.app')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="ui-title text-2xl">تصحيح تاريخ حركات التوريد</h1>
            <p class="ui-text-soft mt-1">أداة مؤقتة تعرض الحركات المطابقة أولًا، ثم تعدّل تاريخ الإنشاء وآخر تحديث فقط.</p>
        </div>
        <a href="{{ route('user.dashboard') }}" class="ui-btn ui-btn-secondary">العودة للوحة التحكم</a>
    </header>

    <div class="ui-alert ui-alert-warning">
        <span class="ui-alert-title">تنبيه قبل الاستخدام</span>
        <span class="ui-alert-body block mt-1">لن تتغير الكمية أو الرصيد أو تاريخ العمل. يجب معاينة النتائج والتأكد من المتجر والملاحظة قبل التنفيذ.</span>
    </div>

    <section class="ui-card p-5 space-y-4">
        <div><h2 class="ui-title text-xl">تدقيق عمليات شهر كامل</h2><p class="ui-text-soft mt-1">عرض مؤقت للقراءة فقط لعمليات البيع، مع تمييز الآجل والمتبقي والتحصيلات المسجلة خلال الشهر.</p></div>
        <form method="GET" action="{{ route('user.tools.stock-movement-dates.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div><label class="ui-title block mb-2">المتجر</label><select name="store_id" class="ui-input" required><option value="">اختر المتجر</option>@foreach($stores as $store)<option value="{{ $store->id }}" @selected((string)($filters['store_id'] ?? '') === (string)$store->id)>{{ $store->name }}</option>@endforeach</select></div>
            <div><label class="ui-title block mb-2">الشهر</label><input type="month" name="audit_month" value="{{ $filters['audit_month'] ?? now()->format('Y-m') }}" class="ui-input" required></div>
            <div class="flex items-end"><button type="submit" class="ui-btn ui-btn-primary w-full">عرض تدقيق الشهر</button></div>
        </form>
    </section>

    @if($auditReady)
        <section class="ui-card overflow-hidden"><div class="p-5"><h2 class="ui-title text-xl">عمليات البيع خلال الشهر</h2><p class="ui-text-soft mt-1">{{ $auditSales->count() }} عملية معروضة. أي صف يحمل مبلغًا متبقيًا هو بيع آجل كلي أو جزئي.</p></div>
            @if($auditSales->isNotEmpty())<div class="ui-table-wrap"><table class="ui-table"><thead class="ui-table-head"><tr><th>رقم البيع</th><th>التاريخ</th><th>النوع</th><th>الإجمالي</th><th>المحصل</th><th>كاش</th><th>شبكة</th><th>المتبقي</th><th>المحاسب</th></tr></thead><tbody>@foreach($auditSales as $sale)@php($isCredit = (float)$sale->remaining_amount > 0 || $sale->sale_type === 'credit' || $sale->has_partial_credit)<tr><td>#{{ $sale->id }}</td><td>{{ optional($sale->business_date ?? $sale->created_at)->format('Y-m-d') }}</td><td><span class="ui-badge {{ $isCredit ? 'ui-badge-warning' : 'ui-badge-success' }}">{{ $isCredit ? 'آجل' : (['cash' => 'كاش', 'card' => 'شبكة', 'mixed' => 'مكس'][$sale->sale_type] ?? $sale->sale_type) }}</span></td><td>{{ number_format((float)($sale->final_total ?: $sale->total), 2) }}</td><td>{{ number_format((float)$sale->paid_amount, 2) }}</td><td>{{ number_format((float)$sale->cash_amount, 2) }}</td><td>{{ number_format((float)$sale->card_amount, 2) }}</td><td class="{{ $isCredit ? 'ui-status-warning font-bold' : '' }}">{{ number_format((float)$sale->remaining_amount, 2) }}</td><td>{{ $sale->accountant?->name ?? '—' }}</td></tr>@endforeach</tbody></table></div>@else<div class="ui-card-muted p-8 text-center ui-text-muted">لا توجد عمليات بيع في الشهر المحدد.</div>@endif
        </section>

        <section class="ui-card overflow-hidden">
            <div class="p-5">
                <h2 class="ui-title text-xl">مبيعات مرتبطة بمنتجات محذوفة</h2>
                <p class="ui-text-soft mt-1">عرض تشخيصي للقراءة فقط. يعرض بنود البيع التي لم يعد المنتج المرتبط بها متاحًا، دون تعديل عملية البيع أو المخزون.</p>
            </div>
            @if($salesWithMissingProducts->isNotEmpty())
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead class="ui-table-head"><tr><th>رقم البيع</th><th>رقم بند البيع</th><th>رقم المنتج المحذوف</th><th>الاسم المحفوظ</th><th>بيان البيع</th><th>التاريخ</th><th>الكمية</th><th>الوحدة</th><th>القيمة</th><th>المحاسب</th></tr></thead>
                        <tbody>
                            @foreach($salesWithMissingProducts as $item)
                                <tr>
                                    <td>#{{ $item->sale_id }}</td>
                                    <td>#{{ $item->id }}</td>
                                    <td>#{{ $item->product_id }}</td>
                                    <td>{{ $item->custom_name ?: 'لم يُحفظ اسم مستقل عند البيع' }}</td>
                                    <td>{{ $item->sale?->description ?: '—' }}</td>
                                    <td>{{ optional($item->sale?->business_date ?? $item->sale?->created_at ?? $item->created_at)->format('Y-m-d') }}</td>
                                    <td>{{ number_format((float)$item->quantity, 4) }}</td>
                                    <td>{{ ['piece' => 'حبة', 'unit' => 'وحدة', 'meter' => 'متر', 'roll' => 'رول'][$item->unit_type] ?? ($item->unit_type ?: '—') }}</td>
                                    <td>{{ number_format((float)($item->total ?: ((float)$item->price * (float)$item->quantity)), 2) }}</td>
                                    <td>{{ $item->sale?->accountant?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="ui-card-muted p-8 text-center ui-text-muted">لا توجد في الشهر المحدد عمليات بيع مرتبطة بمنتجات محذوفة.</div>
            @endif
        </section>

        @if($creditCollections->isNotEmpty())
            <section class="ui-card overflow-hidden"><div class="p-5"><h2 class="ui-title text-xl">تحصيلات الآجل خلال الشهر</h2></div><div class="ui-table-wrap"><table class="ui-table"><thead class="ui-table-head"><tr><th>رقم التحصيل</th><th>رقم الآجل</th><th>التاريخ</th><th>المبلغ</th><th>طريقة التحصيل</th></tr></thead><tbody>@foreach($creditCollections as $collection)<tr><td>#{{ $collection->id }}</td><td>#{{ $collection->credit_sale_id }}</td><td>{{ $collection->collection_date }}</td><td>{{ number_format((float)$collection->amount, 2) }}</td><td>{{ $collection->payment_method_label ?? $collection->payment_method ?? '—' }}</td></tr>@endforeach</tbody></table></div></section>
        @endif
    @endif

    <section class="ui-card p-5">
        <form method="GET" action="{{ route('user.tools.stock-movement-dates.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <label for="store_id" class="ui-title block mb-2">المتجر</label>
                <select id="store_id" name="store_id" class="ui-input" required>
                    <option value="">اختر المتجر</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" @selected((string)($filters['store_id'] ?? '') === (string)$store->id)>{{ $store->name }} — #{{ $store->id }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2 xl:col-span-2">
                <label for="note" class="ui-title block mb-2">الملاحظة المطابقة تمامًا</label>
                <input id="note" name="note" type="text" class="ui-input" value="{{ $filters['note'] ?? '' }}" placeholder="مثال: توريد طلبية محمد" required>
            </div>

            <div>
                <label for="from_date" class="ui-title block mb-2">التاريخ الحالي</label>
                <input id="from_date" name="from_date" type="date" class="ui-input" value="{{ $filters['from_date'] ?? '' }}" required>
            </div>

            <div>
                <label for="to_date" class="ui-title block mb-2">التاريخ الصحيح</label>
                <input id="to_date" name="to_date" type="date" class="ui-input" value="{{ $filters['to_date'] ?? '' }}" required>
            </div>

            <div>
                <label for="time_mode" class="ui-title block mb-2">الوقت</label>
                <select id="time_mode" name="time_mode" class="ui-input" required>
                    <option value="preserve" @selected(($filters['time_mode'] ?? 'preserve') === 'preserve')>الاحتفاظ بالوقت الحالي</option>
                    <option value="midnight" @selected(($filters['time_mode'] ?? '') === 'midnight')>تعيين الوقت 00:00:00</option>
                </select>
            </div>

            <div class="md:col-span-2 xl:col-span-3">
                <button type="submit" class="ui-btn ui-btn-secondary">معاينة الحركات المطابقة</button>
            </div>
        </form>
    </section>

    @if($readyToPreview)
        <section class="ui-card overflow-hidden">
            <div class="p-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="ui-title text-xl">نتيجة المعاينة</h2>
                    <p class="ui-text-soft mt-1">إجمالي الحركات المطابقة: {{ $matchingCount }}@if($matchingCount > $movements->count()) — تعرض أول {{ $movements->count() }} حركة للمراجعة@endif</p>
                </div>

                @if($movements->isNotEmpty())
                    <form method="POST" action="{{ route('user.tools.stock-movement-dates.update') }}" data-confirm-submit data-confirm-title="تأكيد تعديل تواريخ المخزون" data-confirm-text="سيتم تعديل created_at وupdated_at لجميع الحركات المطابقة للمعايير المحددة. هل تريد المتابعة؟" data-confirm-icon="warning">
                        @csrf
                        <input type="hidden" name="store_id" value="{{ $filters['store_id'] }}">
                        <input type="hidden" name="note" value="{{ $filters['note'] }}">
                        <input type="hidden" name="from_date" value="{{ $filters['from_date'] }}">
                        <input type="hidden" name="to_date" value="{{ $filters['to_date'] }}">
                        <input type="hidden" name="time_mode" value="{{ $filters['time_mode'] ?? 'preserve' }}">
                        <button type="submit" class="ui-btn ui-btn-warning">اعتماد تعديل التواريخ</button>
                    </form>
                @endif
            </div>

            @if($movements->isEmpty())
                <div class="ui-card-muted p-8 text-center ui-text-muted">لا توجد حركات تطابق المتجر والملاحظة والتاريخ المحدد.</div>
            @else
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead class="ui-table-head">
                            <tr>
                                <th>رقم الحركة</th>
                                <th>المنتج</th>
                                <th>النوع</th>
                                <th>الكمية</th>
                                <th>تاريخ الإنشاء الحالي</th>
                                <th>آخر تحديث حالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($movements as $movement)
                                <tr>
                                    <td>#{{ $movement->id }}</td>
                                    <td>{{ $movement->product_name_snapshot ?? $movement->product?->name ?? 'منتج غير متاح' }}</td>
                                    <td>{{ $movement->operation_label }}</td>
                                    <td>{{ number_format((float)$movement->quantity, 4) }}</td>
                                    <td>{{ optional($movement->created_at)->format('Y-m-d H:i:s') }}</td>
                                    <td>{{ optional($movement->updated_at)->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</div>
@endsection
