@extends('dashboard.app')
@section('title', 'مراجعة الشفتات الناقصة - ' . $store->name)

@section('content')
@php
    $zeroCloseRows = $gapRows->filter(function ($row) {
        $hasOperations = ($row['sales_count'] + $row['expenses_count'] + $row['withdrawals_count']) > 0;
        $isRequested = in_array($row['request_status'] ?? null, ['pending', 'in_progress'], true);

        return ! $hasOperations && ! $isRequested;
    })->values();
@endphp
<div class="max-w-6xl mx-auto p-6 space-y-6 ui-title" x-data="{ selectedZeroDates: [] }">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-black ui-title">مراجعة الشفتات الناقصة</h1>
                <x-ui.help title="نطاق مراجعة الشفتات" body="تعرض الصفحة الأيام المكتملة فقط ضمن آخر 15 يومًا، ولا تشمل اليوم الجاري لأنه ما زال مفتوحًا وقابلًا لتسجيل العمليات." />
            </div>
            <p class="ui-text-muted text-sm mt-1">{{ $store->name }}</p>
        </div>
        <a href="{{ route('user.stores.show', $store->id) }}" class="px-4 py-2 rounded-xl ui-surface-muted-bg ui-hover-info-bg ui-title text-sm transition">رجوع للمتجر</a>
    </div>

    <div class="rounded-2xl border ui-border ui-surface-strong-bg p-5 text-sm leading-7 shadow-lg shadow-black">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="ui-title flex items-center gap-2">
                <p class="font-black ui-title">
                    كيف تتعامل مع الأيام الناقصة؟
                </p>
                <x-ui.help title="التعامل مع الأيام الناقصة" body="اليوم الذي يحتوي عمليات يُعاد للمحاسب ليُدخل أو يربط الشفت الصحيح. اليوم الذي لا يحتوي أي عملية يمكن إغلاقه صفريًا كإجازة مع حفظ سجل تدقيق." />
            </div>
            @if($zeroCloseRows->isNotEmpty())
                <form method="POST" action="{{ route('user.stores.shift-gaps.zero-close', $store->id) }}"
                      class="shrink-0 js-shift-gap-confirm"
                      data-confirm-title="إغلاق الأيام المحددة"
                      data-confirm-text="سيتم إنشاء إغلاق صفري للأيام التي حددتها فقط، ولن يغلق النظام أي يوم يحتوي عمليات."
                      data-confirm-icon="question">
                    @csrf
                    <template x-for="date in selectedZeroDates" :key="date">
                        <input type="hidden" name="business_dates[]" :value="date">
                    </template>
                    <button type="submit" class="ui-btn ui-btn-success px-4 py-2 ui-text-caption font-black" :disabled="selectedZeroDates.length === 0">
                        إغلاق الأيام المحددة <span x-text="selectedZeroDates.length"></span>
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- توضيح: أعيد بناء صفحة مراجعة الشفتات كبطاقات حتى تظهر قرارات المالك بوضوح بدل جدول مزدحم. --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @forelse($gapRows as $row)
            @php
                $hasOperations = ($row['sales_count'] + $row['expenses_count'] + $row['withdrawals_count']) > 0;
                $requestStatus = $row['request_status'] ?? null;
                $isRequested = in_array($requestStatus, ['pending', 'in_progress'], true);
                $canZeroClose = ! $hasOperations && ! $isRequested;
                // توضيح: رقم الشفت لا يظهر للمالك إلا للمتاجر متعددة الشفتات؛ متجر شفت واحد يعامل كتاريخ عادي.
                $shouldShowShiftLabel = (int) ($row['max_shifts'] ?? 1) > 1;
            @endphp
            <div class="rounded-3xl ui-card p-5 shadow-xl shadow-black space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                    <div>
                        <p class="ui-text-caption ui-text-muted font-bold">التاريخ الناقص</p>
                        <h2 class="text-2xl font-black ui-title font-mono mt-1">{{ $row['date'] }}</h2>
                        @if($shouldShowShiftLabel)
                            <p class="ui-status-warning text-sm font-black mt-2">{{ $row['shift_label'] }}</p>
                            @if(($row['closed_shifts_count'] ?? 0) > 0)
                                <p class="ui-text-caption ui-text-muted mt-1">تم إغلاق {{ $row['closed_shifts_count'] }} شفت، والمتبقي هو الشفت {{ $row['missing_shift_number'] }}</p>
                            @endif
                        @endif
                        @if($row['accountant_name'] ?? null)
                            <p class="ui-text-caption ui-text-soft mt-2">المحاسب: <span class="ui-title font-bold">{{ $row['accountant_name'] }}</span></p>
                        @endif
                    </div>
                    <div class="flex flex-col items-start gap-2 sm:items-end">
                        @if($canZeroClose)
                            <label class="ui-inline-frame ui-text-caption font-bold">
                                <input type="checkbox" class="ui-checkbox" value="{{ $row['date'] }}" x-model="selectedZeroDates">
                                تحديد للإغلاق الصفري
                            </label>
                        @endif
                        @if($requestStatus === 'in_progress')
                            <span class="rounded-full ui-status-info-bg ui-status-info px-3 py-1 ui-text-caption font-bold">قيد المعالجة لدى المحاسب</span>
                        @elseif($requestStatus === 'pending')
                            <span class="rounded-full ui-status-info-bg ui-status-info px-3 py-1 ui-text-caption font-bold">تم إرساله للمحاسب</span>
                        @elseif($hasOperations)
                            <span class="rounded-full ui-status-danger-bg ui-status-danger px-3 py-1 ui-text-caption font-bold">به عمليات — يحتاج مراجعة</span>
                        @else
                            <span class="rounded-full ui-status-success-bg ui-status-success px-3 py-1 ui-text-caption font-bold">لا توجد عمليات — مرشح للإغلاق الصفري</span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-2xl ui-surface-muted-bg border ui-border p-3">
                        <p class="ui-text-caption ui-text-muted">مبيعات</p>
                        <p class="text-lg font-black ui-title">{{ $row['sales_count'] }}</p>
                    </div>
                    <div class="rounded-2xl ui-surface-muted-bg border ui-border p-3">
                        <p class="ui-text-caption ui-text-muted">مصروفات</p>
                        <p class="text-lg font-black ui-title">{{ $row['expenses_count'] }}</p>
                    </div>
                    <div class="rounded-2xl ui-surface-muted-bg border ui-border p-3">
                        <p class="ui-text-caption ui-text-muted">سحوبات</p>
                        <p class="text-lg font-black ui-title">{{ $row['withdrawals_count'] }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 pt-2">
                    @if($isRequested)
                        <span class="px-1 py-2 ui-status-info ui-text-caption font-bold">بانتظار المحاسب</span>
                        <form method="POST" action="{{ route('user.stores.shift-gaps.request-accountant.cancel', $store->id) }}"
                              class="js-shift-gap-confirm"
                              data-confirm-title="إلغاء طلب المحاسب"
                              data-confirm-text="سيتم إلغاء الطلب الحالي لهذا الشفت، وبعدها يمكنك إرساله لمحاسب آخر."
                              data-confirm-icon="warning">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="business_date" value="{{ $row['date'] }}">
                            <input type="hidden" name="missing_shift_number" value="{{ $row['missing_shift_number'] }}">
                            <button type="submit" class="ui-btn ui-btn-danger px-3 py-2 ui-text-caption">إلغاء الطلب</button>
                        </form>
                        @if($activeAccountants->count() > 1)
                            <form method="POST" action="{{ route('user.stores.shift-gaps.request-accountant.reassign', $store->id) }}"
                                  class="flex flex-col sm:flex-row gap-2 sm:items-center js-shift-gap-confirm"
                                  data-confirm-title="إعادة تعيين الطلب"
                                  data-confirm-text="سيتم تحويل هذا الطلب إلى المحاسب المختار وإشعاره مباشرة."
                                  data-confirm-icon="question">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="business_date" value="{{ $row['date'] }}">
                                <input type="hidden" name="missing_shift_number" value="{{ $row['missing_shift_number'] }}">
                                <select name="accountant_id" required class="rounded-xl ui-surface-muted-bg border ui-border ui-title px-3 py-2 ui-text-caption">
                                    <option value="">اختر محاسبًا آخر</option>
                                    @foreach($activeAccountants as $accountantOption)
                                        <option value="{{ $accountantOption->id }}">{{ $accountantOption->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="ui-btn ui-btn-warning px-3 py-2 ui-text-caption font-bold">إعادة تعيين</button>
                            </form>
                        @endif
                    @else
                        <form method="POST" action="{{ route('user.stores.shift-gaps.request-accountant', $store->id) }}" class="flex flex-col sm:flex-row gap-2 sm:items-center">
                            @csrf
                            <input type="hidden" name="business_date" value="{{ $row['date'] }}">
                            <input type="hidden" name="missing_shift_number" value="{{ $row['missing_shift_number'] }}">
                            @if($activeAccountants->count() === 1)
                                @php
                                    $onlyAccountant = $activeAccountants->first();
                                @endphp
                                <input type="hidden" name="accountant_id" value="{{ $onlyAccountant->id }}">
                                {{-- توضيح: عند وجود محاسب واحد فقط لا نعرض قائمة اختيار؛ يظهر اسمه مباشرة منعًا لالتباس المالك. --}}
                                <span class="rounded-xl ui-surface-muted-bg border ui-border ui-title px-3 py-2 ui-text-caption">{{ $onlyAccountant->name }}</span>
                            @else
                                <select name="accountant_id" required class="rounded-xl ui-surface-muted-bg border ui-border ui-title px-3 py-2 ui-text-caption">
                                    <option value="">اختر محاسبًا فعالًا</option>
                                    @foreach($activeAccountants as $accountantOption)
                                        <option value="{{ $accountantOption->id }}">{{ $accountantOption->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <button type="submit" class="ui-btn ui-btn-info px-3 py-2 ui-text-caption font-bold disabled:opacity-50" @disabled($activeAccountants->isEmpty())>إعادة للمحاسب</button>
                            @if($activeAccountants->isEmpty())
                                <span class="ui-status-danger ui-text-caption">لا يوجد محاسب فعال في هذا المتجر.</span>
                            @endif
                        </form>
                        @if(! $hasOperations)
                            <form method="POST" action="{{ route('user.stores.shift-gaps.zero-close', $store->id) }}"
                                  class="js-shift-gap-confirm"
                                  data-confirm-title="إغلاق صفري / إجازة"
                                  data-confirm-text="سيتم إنشاء إغلاق صفري لهذا اليوم لأنه لا يحتوي عمليات."
                                  data-confirm-icon="question">
                                @csrf
                                <input type="hidden" name="business_date" value="{{ $row['date'] }}">
                                <button type="submit" class="rounded-xl ui-status-success-bg border ui-border px-3 py-2 ui-status-success ui-hover-info-bg ui-text-caption font-bold">إغلاق صفري</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('user.stores.shift-gaps.zero-close', $store->id) }}"
                                  class="js-shift-gap-confirm"
                                  data-confirm-title="إغلاق الشفت واعتماد عملياته"
                                  data-confirm-text="سيتم إغلاق هذا الشفت بواسطة المالك اعتمادًا على العمليات الموجودة، بدون إصدار PDF أو رسالة واتساب."
                                  data-confirm-icon="warning">
                                @csrf
                                <input type="hidden" name="business_date" value="{{ $row['date'] }}">
                                <input type="hidden" name="close_with_operations" value="1">
                                <button type="submit" class="rounded-xl ui-status-warning-bg border ui-border px-3 py-2 ui-status-warning ui-hover-info-bg ui-text-caption font-bold">إغلاق من المالك</button>
                            </form>
                        @endif
                    @endif
                    <a href="{{ route('user.stores.daily', ['store' => $store->id, 'date' => $row['date']]) }}" class="ui-btn ui-btn-secondary px-3 py-2 ui-text-caption">فتح المبيعات</a>
                </div>
            </div>
        @empty
            <div class="lg:col-span-2 rounded-3xl ui-card p-8 text-center ui-text-muted">
                لا توجد شفتات ناقصة ضمن آخر 15 يومًا مكتملًا.
            </div>
        @endforelse
    </div>
</div>

@endsection

@section('scripts')

{{-- علامة تفعيل لوحدة الواجهة المستخرجة دون تغيير العملية. --}}
<div class="hidden" data-shift-gap-interface aria-hidden="true"></div>
@endsection
