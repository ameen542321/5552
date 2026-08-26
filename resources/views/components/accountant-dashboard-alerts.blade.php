@props([
    'incomingTransfers' => 0,
    'outgoingTransfers' => 0,
    'shiftRequests' => collect(),
    'activeReferenceDate' => null,
])

@php
    $shiftRequests = collect($shiftRequests);
    $transferCount = (int) $incomingTransfers + (int) $outgoingTransfers;
    $activeReferenceDayName = $activeReferenceDate
        ? \Carbon\Carbon::parse($activeReferenceDate)->locale('ar')->translatedFormat('l')
        : null;
@endphp

<div x-data="{ dashboardAlertOpen: null }" class="inline-flex flex-wrap items-center gap-2">
    @if($transferCount > 0)
        <button type="button" @click="dashboardAlertOpen = 'transfers'" class="ui-topbar-action group gap-2" aria-label="تنبيهات النقل المخزني">
            <i class="fa-solid fa-truck-ramp-box text-xl" aria-hidden="true"></i>
            <span class="ui-badge ui-badge-info">{{ $transferCount }}</span>
            <span class="ui-tooltip-popover">النقل المخزني</span>
        </button>
    @endif

    @if($activeReferenceDate)
        <button type="button" @click="dashboardAlertOpen = 'reference-day'" class="ui-topbar-action group gap-2" aria-label="تنبيه اليوم المرجع">
            <i class="fa-solid fa-calendar-day text-xl" aria-hidden="true"></i>
            <span class="ui-badge ui-badge-info">1</span>
            <span class="ui-tooltip-popover">اليوم المرجع</span>
        </button>
    @endif

    @if($shiftRequests->isNotEmpty())
        <button type="button" @click="dashboardAlertOpen = 'owner-requests'" class="ui-topbar-action group gap-2" aria-label="طلبات صاحب المتجر">
            <i class="fa-solid fa-inbox text-xl" aria-hidden="true"></i>
            <span class="ui-badge ui-badge-info">{{ $shiftRequests->count() }}</span>
            <span class="ui-tooltip-popover">طلبات صاحب المتجر</span>
        </button>
    @endif

    <div x-show="dashboardAlertOpen" x-cloak x-transition.opacity class="ui-modal-backdrop" role="dialog" aria-modal="true">
        <div class="ui-modal-panel w-full max-w-xl" @click.outside="dashboardAlertOpen = null">
            <div class="ui-modal-header">
                <h2 class="ui-title font-bold" x-text="dashboardAlertOpen === 'transfers' ? 'النقل المخزني' : (dashboardAlertOpen === 'reference-day' ? 'اليوم المرجع' : 'طلبات صاحب المتجر')"></h2>
                <button type="button" @click="dashboardAlertOpen = null" class="ui-modal-close-danger" aria-label="إغلاق">×</button>
            </div>

            <div class="p-4 space-y-3 max-h-[65vh] overflow-y-auto">
                <div x-show="dashboardAlertOpen === 'transfers'" class="space-y-3">
                    <div class="flex items-center gap-2"><strong class="ui-title">حالة النقل</strong><x-ui.help title="النقل المخزني" body="الوارد يحتاج منك تسجيل استلامه، والصادر يحتاج مراجعة حالته. افتح صفحة النقل لإكمال الإجراء." /></div>
                    @if((int) $incomingTransfers > 0)<p class="ui-card-muted p-3 ui-title">{{ $incomingTransfers }} بضاعة واردة بحاجة لمعالجة.</p>@endif
                    @if((int) $outgoingTransfers > 0)<p class="ui-card-muted p-3 ui-title">{{ $outgoingTransfers }} بضاعة صادرة بحاجة للمراجعة.</p>@endif
                    <a href="{{ route('accountant.transfers.index') }}" class="ui-btn ui-btn-primary w-full">فتح النقل المخزني</a>
                </div>

                <div x-show="dashboardAlertOpen === 'reference-day'" class="ui-alert ui-alert-warning">
                    <span class="flex items-center gap-2"><strong class="ui-alert-title">يوم مرجع مفعل — {{ $activeReferenceDayName }}</strong><x-ui.help title="اليوم المرجع" body="عند تفعيل يوم مرجع تعرض بطاقات الصفحة عمليات ذلك التاريخ بدل عمليات اليوم الحالي حتى تنتهي من معالجته." /></span>
                    <span class="ui-alert-body">تعرض الصفحة الآن عمليات يوم {{ $activeReferenceDayName }}، تاريخ {{ $activeReferenceDate }}، حتى إغلاق اليوم المرجع أو تأجيله.</span>
                </div>

                <div x-show="dashboardAlertOpen === 'owner-requests'" class="space-y-3">
                    <div class="flex items-center gap-2"><strong class="ui-title">طلبات المراجعة</strong><x-ui.help title="طلبات صاحب المتجر" body="افتح الطلب المطلوب لمعالجة اليوم المحدد. يظهر زر الاستئناف إذا سبق فتح الطلب ولم يكتمل." /></div>
                    @foreach($shiftRequests as $gapRequest)
                        @php
                            $gapDate = \Carbon\Carbon::parse(data_get($gapRequest->details, 'business_date'))->toDateString();
                            $gapStatus = data_get($gapRequest->details, 'status', 'pending');
                            $isActiveGap = $activeReferenceDate === $gapDate;
                        @endphp
                        <div class="ui-card-muted p-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <span class="ui-title font-bold">طلب مراجعة تاريخ {{ $gapDate }}</span>
                            @if($isActiveGap)
                                <span class="ui-badge ui-badge-success">مفعل الآن</span>
                            @else
                                <form method="POST" action="{{ route('accountant.shift-gaps.activate', $gapRequest->id) }}">
                                    @csrf
                                    <button type="submit" class="ui-btn ui-btn-warning">{{ $gapStatus === 'in_progress' ? 'استئناف الطلب' : 'فتح الطلب' }}</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
