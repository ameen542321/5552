@extends('dashboard.app')
@php
    $activeAccountant = $employee->activeAccountant;
    $latestAccountant = $employee->accountant;
    $label = $personLabel ?? ($activeAccountant ? 'المحاسب' : 'الموظف');
@endphp

@section('title', $label . ' - ' . $employee->name)

@section('content')

<div class="px-4 sm:px-6 py-6 sm:py-8 max-w-5xl mx-auto">

    @php
        $activeAccountant = $employee->activeAccountant;
        $latestAccountant = $employee->accountant;
        $label = $personLabel ?? ($activeAccountant ? 'المحاسب' : 'الموظف');
    @endphp

   {{-- يتحول الرأس إلى أعمدة على الجوال حتى لا يضغط زر الرجوع اسم الموظف أو صفته. --}}
   <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 sm:mb-10">

    {{-- زر الرجوع (يمين) --}}
    <a href="{{ $returnTo ?? request('return_to', route('user.employees.index')) }}"
       class="flex items-center gap-2 ui-btn ui-btn-secondary ui-title px-4 py-2 rounded-lg transition">
        <i class="fa-solid fa-arrow-right text-lg"></i>
        <span>رجوع</span>
    </a>

    {{-- العنوان (وسط) --}}
    <div class="text-center flex-1 min-w-0">
        <h1 class="text-2xl sm:text-3xl font-bold ui-title break-words">
            {{ $label }} — {{ $employee->name }}
        </h1>
        {{-- الجهة الوظيفية أقرب إلى هوية الموظف، لذلك تظهر كشارة مختصرة تحت الاسم بدل بطاقة مستقلة. --}}
        <div class="mt-2 flex flex-wrap items-center justify-center gap-2">
            <span class="ui-badge {{ $activeAccountant ? 'ui-badge-success' : ($latestAccountant ? 'ui-badge-warning' : 'ui-badge-info') }}">
                <i class="fa-solid {{ $activeAccountant ? 'fa-user-tie' : ($latestAccountant ? 'fa-user-clock' : 'fa-user') }}"></i>
                {{ $activeAccountant ? 'محاسب فعال' : ($latestAccountant ? 'محاسب موقوف' : 'موظف') }}
            </span>
            <span class="ui-text-muted text-sm">عرض وإدارة بيانات {{ $label }}</span>
        </div>
    </div>

    {{-- يسار فارغ للتوازن --}}
    <div class="hidden sm:block w-24" aria-hidden="true"></div>

</div>

    <form method="GET" action="{{ url()->current() }}" class="mb-6 rounded-2xl ui-card p-4 flex flex-col sm:flex-row gap-3 sm:items-end">
        <input type="hidden" name="return_to" value="{{ $returnTo ?? request('return_to') }}">
        <div>
            <label class="block ui-text-caption ui-text-muted mb-2">فلترة الشهر</label>
            <input type="month" name="month" value="{{ $selectedMonth }}"
                   class="ui-card ui-title rounded-lg px-3 py-2">
        </div>
        <button class="ui-btn ui-btn-primary ui-title px-5 py-2 rounded-lg">تطبيق</button>
    </form>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-8">
        @foreach($operationSummaryCards as $summaryCard)
            <button type="button"
                    @if(!empty($summaryCard['modal'])) data-ui-show="{{ $summaryCard['modal'] }}" data-ui-reset-details @endif
                    class="text-right rounded-2xl ui-card p-4 shadow-lg ui-hover-info-bg transition">
                <p class="ui-text-caption ui-text-muted mb-2">{{ $summaryCard['label'] }}</p>
                <div class="flex items-end gap-1">
                    <span class="text-2xl font-black ui-status-info">{{ $summaryCard['value'] }}</span>
                    <span class="ui-text-caption ui-text-muted pb-1">{{ $summaryCard['suffix'] }}</span>
                </div>
                <p class="ui-text-caption ui-text-muted mt-2">{{ $summaryCard['hint'] }}</p>
            </button>
        @endforeach
    </div>


    <!-- العمليات -->
    <div class="ui-card p-4 sm:p-6 md:p-7 shadow-xl backdrop-blur-sm">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-xl font-bold ui-title">عمليات {{ $label }}</h2>

        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach($actionCards as $operationCard)
                @if(($operationCard['type'] ?? 'modal') === 'link')
                    <a href="{{ $operationCard['url'] }}"
                       class="group text-right ui-surface-muted-bg ui-hover-info-bg ui-title rounded-xl border ui-border p-5 transition-all hover:-translate-y-0.5 w-full">
                @elseif(($operationCard['type'] ?? 'modal') === 'status')
                    <form action="{{ $operationCard['url'] }}" method="POST" class="js-confirm-status" data-confirm-title="{{ $operationCard['confirm_title'] ?? 'تأكيد العملية' }}" data-confirm-text="{{ $operationCard['confirm'] }}">
                        @csrf
                        @method($operationCard['method'])
                        {{-- حفظ رابط صفحة الموظف/المحاسب كاملًا حتى يعود المستخدم لنفس الصفحة بعد الإيقاف/التفعيل. --}}
                        <input type="hidden" name="return_to" value="{{ url()->full() }}">
                        <button type="submit"
                                class="group text-right ui-surface-muted-bg ui-hover-info-bg ui-title rounded-xl border ui-border p-5 transition-all hover:-translate-y-0.5 w-full">
                @else
                    <button type="button"
                            data-ui-show="{{ $operationCard['modal'] }}" data-ui-reset-details
                            class="group text-right ui-surface-muted-bg ui-hover-info-bg ui-title rounded-xl border ui-border p-5 transition-all hover:-translate-y-0.5 w-full">
                @endif
                    <div class="flex items-center gap-3">
                        <span class="w-11 h-11 rounded-xl ui-status-info-bg ui-status-info flex items-center justify-center ui-border">
                            <i class="fa-solid {{ $operationCard['icon'] }} text-lg"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="font-semibold break-words">{{ $operationCard['title'] }}</p>
                            <p class="ui-text-caption ui-text-muted mt-1 break-words">{{ $operationCard['hint'] }}</p>
                        </div>
                    </div>
                @if(($operationCard['type'] ?? 'modal') === 'link')
                    </a>
                @elseif(($operationCard['type'] ?? 'modal') === 'status')
                        </button>
                    </form>
                @else
                    </button>
                @endif
            @endforeach

            {{-- جعل الترقية/الإرجاع بنفس شكل بطاقات العمليات حتى تبقى تجربة المستخدم موحدة. --}}
            @if($activeAccountant)
                <form action="{{ route('user.employees.demote', $employee->id) }}" method="POST">
                    @csrf
                    <button class="group text-right ui-surface-muted-bg ui-hover-info-bg ui-title rounded-xl border ui-border p-5 transition-all hover:-translate-y-0.5 w-full">
                        <div class="flex items-center gap-3">
                            <span class="w-11 h-11 rounded-xl ui-status-warning-bg ui-status-warning flex items-center justify-center ui-border">
                                <i class="fa-solid fa-arrow-rotate-left text-lg"></i>
                            </span>
                            <div>
                                <p class="font-semibold">سحب الترقية</p>
                                <p class="ui-text-caption ui-text-muted mt-1">سحب صلاحية المحاسب</p>
                            </div>
                        </div>
                    </button>
                </form>
            @else
                <button type="button"
                        data-ui-show="promoteModal"
                        class="group text-right ui-surface-muted-bg ui-hover-info-bg ui-title rounded-xl border ui-border p-5 transition-all hover:-translate-y-0.5 w-full">
                    <div class="flex items-center gap-3">
                        <span class="w-11 h-11 rounded-xl ui-status-info-bg ui-status-info flex items-center justify-center ui-border">
                            <i class="fa-solid fa-arrow-up text-lg"></i>
                        </span>
                        <div>
                            <p class="font-semibold">{{ $latestAccountant ? 'إعادة تفعيل المحاسب' : 'ترقية إلى محاسب' }}</p>
                            <p class="ui-text-caption ui-text-muted mt-1">{{ $latestAccountant ? 'الحساب موقوف وسيعاد تفعيله' : 'إنشاء حساب محاسب مرتبط بالموظف' }}</p>
                        </div>
                    </div>
                </button>
            @endif
        </div>
    </div>

    <!-- سجل العمليات -->
  <div class="ui-card p-4 sm:p-8 shadow-xl mt-8 sm:mt-12">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6 sm:mb-10">
        <div>
            <h2 class="text-2xl sm:text-3xl font-bold ui-title">سجل العمليات</h2>
            <p class="ui-text-muted text-sm mt-1">آخر الأنشطة المسجلة على هذا {{ $label }}</p>
        </div>

    </div>


    {{-- على الجوال يظهر السجل كبطاقات، وعلى الشاشات الأكبر يظهر جدول مختصر دون عمود مكرر للعملية. --}}
    <div class="space-y-3 sm:hidden">
        @forelse ($recentLogs as $operationLog)
            @php
                $operationActionName = $operationLog->action_name ?? $operationLog->action ?? 'operation';
                $operationAction = $logActionMap[$operationActionName] ?? ['color' => 'ui-text-muted', 'icon' => 'fa-circle-dot'];
                $operationLogMeta = is_array($operationLog->meta) ? $operationLog->meta : [];
                $actorName = $operationLogMeta['actor_name'] ?? $operationLogMeta['added_by_name'] ?? 'غير محدد';
                $operationLogDate = $operationLogMeta['operation_date'] ?? optional($operationLog->created_at)->format('Y-m-d');
            @endphp
            <article class="ui-card-muted p-4">
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 shrink-0 rounded-xl ui-surface-strong-bg flex items-center justify-center"><i class="fa-solid {{ $operationAction['icon'] }} {{ $operationAction['color'] }}"></i></span>
                    <div class="min-w-0"><p class="font-semibold ui-title break-words">{{ $operationLog->description }}</p><p class="ui-text-caption ui-text-muted mt-1">{{ $operationLogDate }}</p><p class="ui-text-caption ui-text-soft mt-1">نفذها: {{ $actorName }}</p></div>
                </div>
            </article>
        @empty
            <p class="py-8 text-center ui-text-muted">لا توجد عمليات حتى الآن</p>
        @endforelse
    </div>
    <div class="hidden overflow-x-auto sm:block">
        <table class="w-full min-w-[34rem] text-sm text-right">
            <thead class="ui-text-muted border-b ui-border">
                <tr>
                    <th class="py-3 px-2">الوصف</th>
                    <th class="py-3 px-2">من قام بها</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ui-border">
    @forelse ($recentLogs as $operationLog)

        @php
            // الوصف يحتوي نوع العملية، لذلك يظهر التاريخ تحته ولا نكرر نوع العملية في عمود مستقل.
            $operationActionName = $operationLog->action_name ?? $operationLog->action ?? 'operation';
            $operationAction = $logActionMap[$operationActionName] ?? [
                'label' => $operationActionName,
                'color' => 'ui-text-muted',
                'icon'  => 'fa-circle-dot'
            ];
            $operationLogMeta = is_array($operationLog->meta) ? $operationLog->meta : [];
            $actorName = $operationLogMeta['actor_name'] ?? $operationLogMeta['added_by_name'] ?? 'غير محدد';
            $operationLogDate = $operationLogMeta['operation_date'] ?? optional($operationLog->created_at)->format('Y-m-d');
        @endphp

        <tr class="ui-text-soft ui-hover-info-bg">
            <td class="py-4 px-2">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl ui-surface-muted-bg flex items-center justify-center">
                        <i class="fa-solid {{ $operationAction['icon'] }} {{ $operationAction['color'] }}"></i>
                    </span>
                    <div>
                        <p class="font-semibold ui-title">{{ $operationLog->description }}</p>
                        <p class="ui-text-caption ui-text-muted mt-1">{{ $operationLogDate }}</p>
                    </div>
                </div>
            </td>
            <td class="py-4 px-2 ui-text-soft">{{ $actorName }}</td>
        </tr>

    @empty

        <tr>
            <td colspan="2" class="py-12 text-center ui-text-muted">
                لا يوجد عمليات حتى الآن
            </td>
        </tr>

    @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $recentLogs->links() }}
    </div>

</div>




</div>


{{-- إصلاح مطبق: فتح مودالات ملخص العمليات وإعادة ضبط details ينفذهما ui-actions. --}}

@include('components.employee.operation-details-modal', [
    'modalId' => 'withdrawalsDetailsModal',
    'title' => 'تفاصيل السحوبات للشهر المحدد',
    'rows' => $operationDetails['withdrawals'],
    'columns' => ['amount' => 'المبلغ', 'accounting_date' => 'التاريخ', 'added_by' => 'من نفذ العملية', 'description' => 'الملاحظات'],
])
@include('components.employee.operation-details-modal', [
    'modalId' => 'debtsDetailsModal',
    'title' => 'سجل المديونيات والتحصيلات',
    'rows' => $operationDetails['debts'],
    'columns' => ['_serial' => '#', 'signed_amount' => 'المبلغ', 'date' => 'التاريخ', 'added_by' => 'من نفذ العملية', 'description' => 'الملاحظات'],
])
@include('components.employee.operation-details-modal', [
    'modalId' => 'creditSalesLogModal',
    'title' => 'سجل عمليات الأجل',
    'rows' => $operationDetails['credit_sales'],
    'columns' => ['_serial' => '#', 'operation_name' => 'اسم العملية', 'amount' => 'مبلغ العملية', 'added_by' => 'من نفذها', 'credit_partial_status' => 'هل تم سداد جزئي؟', 'collection_payments' => 'نوع التحصيل ومن نفذه'],
])
@include('components.employee.operation-details-modal', [
    'modalId' => 'creditSalesDetailsModal',
    'title' => 'إدارة عمليات الأجل',
    'rows' => $operationDetails['credit_sales'],
    'columns' => ['amount' => 'القيمة', 'remaining_amount' => 'المتبقي', 'date' => 'التاريخ', 'added_by' => 'من نفذ العملية', 'operation_name' => 'اسم العملية', 'description' => 'مرجع العملية', 'collection_payments' => 'التحصيلات'],
])
@include('components.employee.operation-details-modal', [
    'modalId' => 'absencesDetailsModal',
    'title' => 'تفاصيل الغياب للشهر المحدد',
    'rows' => $operationDetails['absences'],
    'columns' => ['date' => 'التاريخ', 'added_by' => 'من نفذ العملية', 'description' => 'الملاحظات'],
])

<!-- المودالات -->
@include('components.employee.withdrawal-form', ['person' => $employee])
@include('components.employee.absence-form', ['person' => $employee])
@include('components.employee.debt-form', ['person' => $employee])
{{-- المالك يعاين الأجل من تفاصيل البيع الآجل فقط؛ الإضافة والتحصيل تتم من واجهات المحاسب/نقطة البيع حتى لا تختلط الصلاحيات. --}}


<!-- مودال الترقية -->
<div id="promoteModal" class="ui-modal-backdrop hidden">
    <div class="ui-modal-panel">

        <h2 class="text-xl font-bold ui-title mb-6">{{ $latestAccountant ? 'إعادة تفعيل المحاسب' : 'ترقية الموظف إلى محاسب' }}</h2>

        <form action="{{ route('user.employees.promote', $employee->id) }}" method="POST">
            @csrf

            @if($latestAccountant)
                <div class="mb-4 p-3 ui-status-warning-bg ui-status-warning rounded-lg">
                    هذا الموظف لديه بريد محاسب سابق. يمكنك فقط إعادة تعيين كلمة المرور أو تركها كما هي.
                </div>

                <label class="block ui-text-soft mb-2">البريد الإلكتروني (غير قابل للتعديل)</label>
                <input type="text"
                       value="{{ $latestAccountant->email }}"
                       readonly
                       class="w-full ui-card ui-text-muted rounded-lg p-3 mb-4 cursor-not-allowed select-all">

            @else
                <label class="block ui-text-soft mb-2">البريد الإلكتروني</label>
                <input type="email" name="email" id="emailInput"
                       pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}"
                       class="w-full ui-card ui-text-soft rounded-lg p-3 mb-2"
                       placeholder="example@mail.com" required>

                <div id="emailExistsWarning"
                     class="hidden mb-4 p-3 ui-status-danger-bg ui-status-danger ui-title rounded-lg text-sm">
                    هذا البريد مستخدم مسبقًا. الرجاء إدخال بريد آخر.
                </div>
            @endif

            <label class="block ui-text-soft mb-2">
                كلمة المرور @if($latestAccountant) <span class="ui-text-muted">(اختياري)</span> @endif
            </label>

            <input type="password" name="password"
                   class="w-full ui-card ui-text-soft rounded-lg p-3 mb-4"
                   placeholder="********">

            <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 mt-6">
                <button type="button"
                        data-ui-hide="promoteModal"
                        class="px-4 py-2 ui-btn ui-btn-secondary ui-text-soft rounded-lg ui-hover-info-bg">
                    إلغاء
                </button>

                <button id="promoteSubmit"
                        type="submit"
                        class="px-4 py-2 ui-btn ui-btn-primary rounded-lg">
                    {{ $latestAccountant ? 'تأكيد إعادة التفعيل' : 'تأكيد الترقية' }}
                </button>
            </div>
        </form>

    </div>
</div>




<!-- فحص الإيميل عبر AJAX -->


{{-- عقد إعداد فحص البريد فقط؛ مسارات عمليات الموظف ونماذجها لم تتغير. --}}
<div class="hidden" data-employee-actions-config="{{ json_encode(['checkEmailUrl' => route('user.employees.checkEmail'), 'csrfToken' => csrf_token()], JSON_HEX_APOS | JSON_HEX_QUOT) }}" aria-hidden="true"></div>
@endsection
