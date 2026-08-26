@extends('dashboard.app')

@section('title', 'التنبيهات الإدارية')

@section('content')
@php
    // يستخدم العدد المعايير نفسها المستخدمة في لوحة المالك حتى لا يختلف الرقم بين الصفحتين.
    $firstStore = $stores->first();
    $alertsHelp = 'هذه الصفحة تجمع الحالات التي تحتاج قرارًا من المالك: شفتات ناقصة، نقل مخزني معلق، موظفون موقوفون، مصروفات أعلى من المبيعات، مديونيات متأخرة، وتنبيهات الرواتب. افتح كل بطاقة لمعالجة الحالة من صفحتها المختصة.';
    $firstPendingTransfer = $firstPendingTransfer ?? null;
    $employeeSalaryThresholdAlerts = $employeeSalaryThresholdAlerts ?? collect();
    $employeesWithoutSalary = $employeesWithoutSalary ?? collect();
    $alertsCount = $missingShiftAlerts->sum('missing_count')
        + $suspendedEmployeeAlerts->count()
        + $pendingStoreTransfersCount
        + ($expensesMonth > $salesMonth ? 1 : 0)
        + (int) $creditLate
        + $employeeSalaryThresholdAlerts->count()
        + $employeesWithoutSalary->count();
@endphp
<div class="ui-page px-4 py-6 sm:px-6">
    <div class="mx-auto max-w-6xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="ui-title text-2xl sm:text-3xl flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation ui-status-warning"></i>
                    التنبيهات الإدارية
                </h1>
                <div class="mt-2 flex items-center gap-2"><span class="ui-text-soft">مركز مراجعة تنبيهات المالك</span><x-ui.help title="التنبيهات الإدارية" :body="$alertsHelp" /></div>
            </div>
            <a href="{{ route('user.dashboard') }}" class="ui-btn ui-btn-secondary"><i class="fa-solid fa-arrow-right"></i> لوحة المالك</a>
        </header>

        <div class="ui-card p-5 flex items-center justify-between gap-4">
            <div><p class="ui-text-soft">التنبيهات التي تحتاج مراجعة</p><p class="ui-title text-2xl mt-1">{{ $alertsCount }}</p></div>
            <span class="ui-warning-help-btn" aria-hidden="true"><i class="fa-solid fa-bell"></i></span>
        </div>

        @if($alertsCount === 0)
            <div class="ui-card p-8 text-center">
                <i class="fa-solid fa-circle-check ui-status-success text-3xl"></i>
                <h2 class="ui-title text-xl mt-3">لا توجد تنبيهات إدارية حاليًا</h2>
                <p class="ui-text-soft mt-2">ستظهر هنا الحالات الجديدة التي تحتاج إلى مراجعتك.</p>
            </div>
        @else
            <div class="space-y-4">
                {{-- تفاصيل الشفت تبقى في صفحتها المتخصصة؛ هنا نعرض الملخص ورابط الإجراء فقط. --}}
                @foreach($missingShiftAlerts as $alert)
                    <section class="ui-card p-5">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div><h2 class="ui-title"><i class="fa-solid fa-calendar-xmark ui-status-warning ml-2"></i>شفتات تحتاج مراجعة — {{ $alert['store']->name }}</h2><p class="ui-text-soft mt-1">{{ $alert['missing_count'] }} شفت/يوم يحتاج إلى مراجعة.</p></div>
                            <a href="{{ route('user.stores.shift-gaps', $alert['store']->id) }}" class="ui-btn ui-btn-info">فتح صفحة المراجعة</a>
                        </div>
                    </section>
                @endforeach

                {{-- إجراءات الموظف الموقوف نُقلت من مودال لوحة المالك إلى مركز التنبيهات. --}}
                @if($suspendedEmployeeAlerts->isNotEmpty())
                    <section class="ui-card p-5">
                        <h2 class="ui-title"><i class="fa-solid fa-user-clock ui-status-danger ml-2"></i>موظفون موقوفون يحتاجون إجراء</h2>
                        <div class="mt-3 space-y-3">
                            @foreach($suspendedEmployeeAlerts as $employee)
                                <div class="ui-card-muted p-4">
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div><p class="ui-title">{{ $employee['name'] }}</p><p class="ui-text-soft mt-1">المتجر: {{ $employee['store_name'] }} — تاريخ الإيقاف: {{ $employee['suspended_at'] }}</p></div>
                                        <div class="ui-inline-frame"><span>بيع آجل: {{ number_format($employee['credit_total'], 2) }} ريال</span><span>مديونيات: {{ number_format($employee['debts_total'], 2) }} ريال</span></div>
                                    </div>
                                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                        {{-- إصلاح مطبق: تحذير إنهاء الموظف محفوظ ويُعرض عبر الحوار المركزي بدل التأكيد المضمّن. --}}
                                        <form action="{{ route('user.dashboard.suspended-employees.terminate', $employee['id']) }}" method="POST"
                                              data-ui-confirm="سيتم حذف الموظف وحساب المحاسب المرتبط إن وجد. تأكد من الالتزامات قبل التنفيذ. هل تريد المتابعة؟"
                                              data-ui-confirm-title="تأكيد إنهاء الموظف">@csrf @method('DELETE')<button type="submit" class="ui-btn ui-btn-danger w-full">نعم، تم فصله</button></form>
                                        <form action="{{ route('user.dashboard.suspended-employees.traveler', $employee['id']) }}" method="POST">@csrf<button type="submit" class="ui-btn ui-btn-secondary w-full">مسافر / إجازة بدون راتب</button></form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if($pendingStoreTransfersCount > 0 && ($firstPendingTransfer || $firstStore))
                    @php($transferStoreId = $firstPendingTransfer?->receiver_store_id ?? $firstPendingTransfer?->sender_store_id ?? $firstStore?->id)
                    <a href="{{ route('user.stores.transfers.index', ['store' => $transferStoreId, 'status' => 'pending']) }}" class="ui-alert ui-alert-warning block"><span class="ui-alert-title">{{ $pendingStoreTransfersCount }} طلب نقل مخزني معلق</span><span class="ui-alert-body block mt-1">اضغط للانتقال إلى متجر الطلبات المعلقة ومراجعة النقل المخزني.</span></a>
                @endif
                @if($expensesMonth > $salesMonth)<div class="ui-alert ui-alert-danger-plain"><span class="ui-alert-title">مصروفات هذا الشهر أعلى من المبيعات</span><span class="ui-alert-body block mt-1">النسبة: {{ number_format(($expensesMonth / max($salesMonth, 1)) * 100, 1) }}%</span></div>@endif
                @if($creditLate > 0)<div class="ui-alert ui-alert-warning"><span class="ui-alert-title">لديك {{ $creditLate }} مديونيات متأخرة لأكثر من 30 يومًا</span></div>@endif

                @if($employeeSalaryThresholdAlerts->isNotEmpty())
                    <section class="ui-card p-5"><h2 class="ui-title">تنبيهات حدود رواتب الموظفين</h2><div class="mt-3 space-y-3">@foreach($employeeSalaryThresholdAlerts as $alert)<div class="ui-alert {{ ($alert['level'] ?? '') === 'danger' ? 'ui-alert-danger-plain' : 'ui-alert-warning' }}"><p class="ui-alert-title">{{ $alert['message'] }}</p><p class="ui-alert-body mt-1">المتجر: {{ $alert['store_name'] ?? 'غير محدد' }} — المبلغ: {{ number_format((float) ($alert['amount'] ?? 0), 2) }} ريال — الراتب: {{ number_format((float) ($alert['salary'] ?? 0), 2) }} ريال — النسبة: {{ number_format((float) ($alert['ratio'] ?? 0), 1) }}%</p></div>@endforeach</div></section>
                @endif

                @if($employeesWithoutSalary->isNotEmpty())
                    <section class="ui-card p-5"><h2 class="ui-title">موظفون لم يُسجّل لهم راتب</h2><div class="mt-3 grid gap-2 sm:grid-cols-2">@foreach($employeesWithoutSalary as $employee)<div class="ui-inline-frame"><span class="ui-title">{{ $employee->name }}</span><span class="ui-text-soft">{{ $employee->store->name ?? 'متجر غير محدد' }}</span></div>@endforeach</div><a href="{{ route('user.employees.index') }}" class="ui-btn ui-btn-info mt-4">مراجعة الموظفين</a></section>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
