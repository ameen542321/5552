@extends('dashboard.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6" dir="rtl">
    <header class="ui-card p-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="ui-title text-2xl font-black">متابعة طلبيات التوريد</h1>
                <x-ui.help title="لوحة المتابعة" body="لوحة قراءة للأدمن تعرض جميع المتاجر، عمر المرحلة، التأخير، سلامة الحالة، وزمن الدورة وفروقات التكلفة. التدخل في الطلبية يبقى عبر جلسة الدعم." />
            </div>
            <p class="ui-text-soft mt-1">متابعة تشغيلية وتقارير ضمن الفترة المحددة دون تعديل الطلبيات.</p>
        </div>
        <a href="{{ route('admin.health.purchase-orders') }}" class="ui-btn ui-btn-info">فتح فحص السلامة</a>
    </header>

    <form method="GET" class="ui-card p-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        <label class="ui-label">المتجر
            <select name="store_id" class="ui-input"><option value="">كل المتاجر</option>@foreach($stores as $store)<option value="{{ $store->id }}" @selected((string)($filters['store_id'] ?? '') === (string)$store->id)>{{ $store->name }}</option>@endforeach</select>
        </label>
        <label class="ui-label">الحالة العامة
            <select name="status" class="ui-input"><option value="">كل الحالات</option>@foreach(['draft'=>'مسودة','sent'=>'مرسلة','received'=>'مستلمة','approved'=>'معتمدة','cancelled'=>'ملغاة'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        </label>
        <label class="ui-label">المرحلة
            <select name="workflow_status" class="ui-input"><option value="">كل المراحل</option>@foreach($workflowLabels as $value=>$label)<option value="{{ $value }}" @selected(($filters['workflow_status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        </label>
        <label class="ui-label">بحث
            <input name="search" value="{{ $filters['search'] ?? '' }}" class="ui-input" placeholder="رقم الطلبية أو اسم المورد">
        </label>
        <label class="ui-label">من تاريخ<input type="date" name="date_from" value="{{ $dateFromValue }}" class="ui-input"></label>
        <label class="ui-label">إلى تاريخ<input type="date" name="date_to" value="{{ $dateToValue }}" class="ui-input"></label>
        <div class="flex items-end gap-2"><button class="ui-btn ui-btn-primary">تطبيق</button><a href="{{ route('admin.purchase-orders.index') }}" class="ui-btn ui-btn-secondary">مسح</a></div>
    </form>

    <section class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4" aria-label="تقارير أداء الطلبيات">
        <article class="ui-card p-4"><span class="ui-text-soft">عدد الطلبيات</span><strong class="ui-title block text-2xl">{{ number_format($reports['orders_count']) }}</strong></article>
        <article class="ui-card p-4"><span class="ui-text-soft">من الإنشاء إلى الإرسال</span><strong class="ui-title block text-2xl">{{ $reports['average_creation_to_send_hours'] === null ? '—' : number_format($reports['average_creation_to_send_hours'], 1).' ساعة' }}</strong></article>
        <article class="ui-card p-4"><span class="ui-text-soft">من الإرسال إلى الاستلام</span><strong class="ui-title block text-2xl">{{ $reports['average_send_to_receive_hours'] === null ? '—' : number_format($reports['average_send_to_receive_hours'], 1).' ساعة' }}</strong></article>
        <article class="ui-card p-4"><span class="ui-text-soft">من الاستلام إلى الاعتماد</span><strong class="ui-title block text-2xl">{{ $reports['average_receive_to_approve_hours'] === null ? '—' : number_format($reports['average_receive_to_approve_hours'], 1).' ساعة' }}</strong></article>
        <article class="ui-card p-4"><span class="ui-text-soft">بنود بفروقات تكلفة</span><strong class="ui-status-warning block text-2xl">{{ number_format($reports['variance_items_count']) }}</strong></article>
        <article class="ui-card p-4"><span class="ui-text-soft">إجمالي الزيادة</span><strong class="ui-status-danger block text-2xl">{{ number_format($reports['positive_variance_total'], 2) }} ر.س</strong></article>
        <article class="ui-card p-4"><span class="ui-text-soft">إجمالي النقص</span><strong class="ui-status-success block text-2xl">{{ number_format($reports['negative_variance_total'], 2) }} ر.س</strong></article>
    </section>

    <section class="ui-card p-5 space-y-4">
        <div class="flex items-center gap-2"><h2 class="ui-title text-xl font-black">الطلبيات</h2><x-ui.help title="العمر والتأخير" body="يحسب عمر المرحلة من آخر حدث مسجل. تظهر شارة التأخير عند تجاوز حد المرحلة، دون إرسال إشعارات أو تشغيل جدولة تلقائية." /></div>
        <div class="ui-table-wrap"><table class="ui-table min-w-[1100px]">
            <thead><tr><th>الطلبية</th><th>المتجر</th><th>المورد</th><th>المحاسب</th><th>المرحلة</th><th>عمر المرحلة</th><th>البنود</th><th>السلامة</th></tr></thead>
            <tbody>@forelse($orders as $order)
                @php $isDelayed = $order->delay_threshold_hours > 0 && $order->stage_age_hours >= $order->delay_threshold_hours; @endphp
                <tr>
                    <td><strong class="ui-title">{{ $order->referenceCode() }}</strong><span class="ui-text-muted block">{{ $order->created_at?->format('Y-m-d') }}</span></td>
                    <td>{{ $order->store?->name ?: 'متجر محذوف' }}</td><td>{{ $order->supplier_name ?: 'غير محدد' }}</td><td>{{ $order->accountant?->name ?: 'غير مسند' }}</td>
                    <td><span class="ui-badge {{ \App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow::badgeClasses()[$order->workflow_status] ?? 'ui-badge-neutral' }}">{{ $workflowLabels[$order->workflow_status] ?? 'حالة غير معروفة' }}</span></td>
                    <td>{{ number_format($order->stage_age_hours) }} ساعة @if($isDelayed)<span class="ui-badge ui-badge-warning">متأخرة</span>@endif</td>
                    <td>{{ number_format($order->items_count) }}</td>
                    <td>@if($order->integrity_issue_count)<a class="ui-btn ui-btn-danger" href="{{ route('admin.health.purchase-orders', ['order_id'=>$order->id]) }}">{{ $order->integrity_issue_count }} مشكلة</a>@else<span class="ui-badge ui-badge-success">سليمة</span>@endif</td>
                </tr>
            @empty<tr><td colspan="8" class="py-8 text-center ui-text-soft">لا توجد طلبيات مطابقة.</td></tr>@endforelse</tbody>
        </table></div>
        {{ $orders->links() }}
    </section>

    <details class="ui-card ui-disclosure">
        <summary class="ui-disclosure-summary p-5"><span><strong class="ui-title font-black">إعدادات حدود الطلبيات</strong><span class="ui-text-soft block mt-1">الحد الافتراضي، حالات الاحتساب، وحدود المتاجر والاستثناءات المؤقتة.</span></span><i class="fa-solid fa-chevron-down ui-disclosure-chevron" aria-hidden="true"></i></summary>
        <div class="border-t ui-border p-5 space-y-5">
            <form method="POST" action="{{ route('admin.purchase-orders.limits.global') }}" class="ui-card-muted p-4 space-y-3">@csrf @method('PATCH')
                <div class="flex items-center gap-2"><h3 class="ui-title font-bold">الإعداد الافتراضي</h3><x-ui.help title="حالات الاحتساب" body="تدخل الطلبية في الحد الأسبوعي فقط عندما تكون حالتها العامة ضمن الحالات المحددة. يطبق الافتراضي على المتاجر التي لا تملك حدًا خاصًا." /></div>
                <label class="ui-label">الحد الأسبوعي الافتراضي<input type="number" min="1" max="100" name="weekly_limit" value="{{ $globalSetting->weekly_limit }}" class="ui-input"></label>
                <div class="flex flex-wrap gap-3">@foreach(['draft'=>'مسودة','sent'=>'مرسلة','received'=>'مستلمة','approved'=>'معتمدة','cancelled'=>'ملغاة'] as $value=>$label)<label class="ui-check"><input type="checkbox" name="counted_statuses[]" value="{{ $value }}" @checked(in_array($value, $globalSetting->effectiveCountedStatuses(), true))><span>{{ $label }}</span></label>@endforeach</div>
                <button class="ui-btn ui-btn-primary">حفظ الافتراضي</button>
            </form>

            <form method="POST" action="{{ route('admin.purchase-orders.limits.store') }}" class="ui-card-muted p-4 grid grid-cols-1 gap-3 md:grid-cols-2">@csrf @method('PATCH')
                <label class="ui-label">المتجر<select required name="store_id" class="ui-input"><option value="">اختر متجرًا</option>@foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->name }}</option>@endforeach</select></label>
                <label class="ui-label">الحد الأسبوعي الخاص<input required type="number" min="1" max="100" name="weekly_limit" value="{{ $globalSetting->weekly_limit }}" class="ui-input"></label>
                <div class="md:col-span-2"><span class="ui-label">الحالات المحتسبة</span><div class="flex flex-wrap gap-3">@foreach(['draft'=>'مسودة','sent'=>'مرسلة','received'=>'مستلمة','approved'=>'معتمدة','cancelled'=>'ملغاة'] as $value=>$label)<label class="ui-check"><input type="checkbox" name="counted_statuses[]" value="{{ $value }}" @checked(in_array($value, $globalSetting->effectiveCountedStatuses(), true))><span>{{ $label }}</span></label>@endforeach</div></div>
                <label class="ui-label">حد الاستثناء المؤقت<input type="number" min="1" max="100" name="exception_weekly_limit" class="ui-input" placeholder="اختياري"></label>
                <label class="ui-label">ينتهي الاستثناء في<input type="datetime-local" name="exception_expires_at" class="ui-input"></label>
                <label class="ui-label md:col-span-2">سبب الاستثناء<textarea name="exception_reason" minlength="10" maxlength="500" class="ui-input" rows="2" placeholder="إلزامي عند إضافة الاستثناء"></textarea></label>
                <button class="ui-btn ui-btn-warning">حفظ حد المتجر</button>
            </form>

            @if($storeSettings->isNotEmpty())<div class="ui-table-wrap"><table class="ui-table"><thead><tr><th>المتجر</th><th>الحد الخاص</th><th>الحد الفعال</th><th>الاستثناء</th><th>السبب</th></tr></thead><tbody>@foreach($storeSettings as $setting)<tr><td>{{ $setting->store?->name }}</td><td>{{ $setting->weekly_limit }}</td><td>{{ $setting->effectiveWeeklyLimit() }}</td><td>{{ $setting->exception_expires_at?->isFuture() ? $setting->exception_expires_at->format('Y-m-d H:i') : 'لا يوجد' }}</td><td>{{ $setting->exception_reason ?: '—' }}</td></tr>@endforeach</tbody></table></div>@endif
        </div>
    </details>
</div>
@endsection
