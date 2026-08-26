@extends('dashboard.app')
@section('title', ($selectedStore ?? null) ? 'موظفو متجر ' . $selectedStore->name : 'إدارة الموظفين')
@section('content')

@php
    $activeAccountantsCount = $employees->filter(fn ($person) => $person->activeAccountant)->count();
    $suspendedEmployeesCount = $employees->where('status', 'suspended')->count();
    $backUrl = ($selectedStore ?? null) ? route('user.stores.show', $selectedStore->id) : route('user.dashboard');
@endphp

<div class="max-w-7xl mx-auto px-4 py-6 space-y-5">

    <div class="ui-card   border ui-border rounded-2xl p-5">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ $backUrl }}"
                   class="inline-flex items-center gap-2 ui-surface-muted-bg ui-text-muted px-4 py-2 rounded-lg transition">
                    <i class="fa-solid fa-arrow-right"></i>
                    رجوع
                </a>

                <div class="flex items-center gap-2">
                    <h1 class="text-2xl md:text-3xl font-black ui-title">{{ ($selectedStore ?? null) ? 'موظفو متجر ' . $selectedStore->name : 'إدارة الموظفين' }}</h1>
                    <x-ui.help title="نطاق عرض الموظفين" :body="($selectedStore ?? null) ? 'عرض جميع موظفي ومحاسبي هذا المتجر.' : 'عرض جميع الموظفين والمحاسبين لدى المالك في كل المتاجر.'" />
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('user.employees.create', array_filter(['store' => ($selectedStore ?? null)?->id, 'return_to' => url()->full()])) }}"
                   class="inline-flex items-center justify-center gap-2 ui-status-info-bg ui-title px-5 py-3 rounded-xl font-bold shadow-lg  transition">
                    <i class="fa-solid fa-plus"></i>
                    إضافة موظف
                </a>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 ui-text-caption ui-text-muted">
        <span class="inline-flex items-center justify-center min-w-9 h-9 px-3 rounded-full ui-surface-muted-bg border ui-border ui-title" title="إجمالي الموظفين في الصفحة">
            <i class="fa-solid fa-users ml-1 ui-status-info"></i>{{ $employees->count() }}
        </span>
        @if($activeAccountantsCount > 0)
            <span class="inline-flex items-center justify-center min-w-9 h-9 px-3 rounded-full ui-status-success-bg border ui-border ui-status-success" title="المحاسبون الفعّالون">
                <i class="fa-solid fa-user-tie ml-1"></i>{{ $activeAccountantsCount }}
            </span>
        @endif
        @if($suspendedEmployeesCount > 0)
            <span class="inline-flex items-center justify-center min-w-9 h-9 px-3 rounded-full ui-status-danger-bg border ui-border ui-status-danger" title="الموظفون الموقوفون">
                <i class="fa-solid fa-user-slash ml-1"></i>{{ $suspendedEmployeesCount }}
            </span>
        @endif
    </div>

    <div class="ui-card min-w-0 overflow-hidden shadow-xl">
        <div class="hidden md:grid grid-cols-12 px-4 py-3 ui-text-caption font-bold ui-text-muted border-b ui-border ui-surface-muted-bg">
            <div class="col-span-1 text-center">#</div>
            <div class="col-span-3">الموظف</div>
            <div class="col-span-2">الجوال</div>
            <div class="col-span-2">المتجر</div>
            <div class="col-span-2">الحالة</div>
            <div class="col-span-2 text-center">الإجراءات</div>
        </div>

        <div class="divide-y divide-ui-border">
            @forelse ($employees as $person)
                <div class="p-4 ui-surface-muted-bg transition">
                    <div class="hidden min-w-0 md:grid grid-cols-12 items-center gap-2">
                        <div class="col-span-1 text-center ui-text-muted font-mono">{{ $loop->iteration }}</div>
                        <div class="col-span-3 flex items-center gap-3 min-w-0">
                            <div class="relative w-10 h-10 rounded-xl ui-surface-muted-bg ui-text-muted flex items-center justify-center shrink-0">
                                @if($person->activeAccountant)
                                    <i class="fa-solid fa-user-tie ui-status-success" title="محاسب فعال" aria-label="محاسب فعال"></i>
                                @else
                                    <i class="fa-solid fa-user"></i>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="ui-title font-bold truncate" dir="auto">{{ $person->name }}</p>
                                <p class="ui-text-caption ui-text-muted">ID: {{ $person->id }}</p>
                                @if(isset($person->salary_info) && ($person->salary_info['suspended_days'] ?? 0) > 0)
                                    <p class="ui-text-caption ui-status-warning mt-1">
                                        الراتب المستحق: {{ number_format($person->salary_info['payable_salary'], 2) }} ر.س
                                        <span class="ui-text-muted">({{ $person->salary_info['worked_days'] }} عمل / {{ $person->salary_info['suspended_days'] }} إيقاف)</span>
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="col-span-2 ui-text-muted text-sm">{{ $person->phone ?? '—' }}</div>
                        <div class="col-span-2 ui-text-muted text-sm truncate">{{ $person->store->name }}</div>

                        <div class="col-span-2 flex items-center justify-center">
                            @if($person->activeAccountant)
                                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 ui-text-caption font-bold ui-status-success-bg ui-status-success-border ui-status-success"><i class="fa-solid fa-user-tie"></i>محاسب فعال</span>
                            @elseif($person->accountant)
                                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 ui-text-caption font-bold ui-status-warning-bg ui-status-warning-border ui-status-warning"><i class="fa-solid fa-user-clock"></i>محاسب موقوف</span>
                            @else
                                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 ui-text-caption font-bold {{ $person->status === 'active' ? 'ui-status-info-bg ui-status-info-border ui-status-info' : 'ui-status-danger-bg ui-status-danger-border ui-status-danger' }}"><i class="fa-solid fa-user"></i>{{ $person->status === 'active' ? 'موظف' : 'موظف موقوف' }}</span>
                            @endif
                        </div>

                        <div class="col-span-2">
                            <div class="flex min-w-0 flex-wrap items-center justify-center gap-1">
                                <a href="{{ route('user.employees.actions', ['employee' => $person->id, 'return_to' => url()->full()]) }}" class="ui-btn ui-btn-info p-2" title="عرض" aria-label="عرض">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <a href="{{ route('user.employees.edit', ['employee' => $person->id, 'return_to' => url()->full()]) }}" class="ui-btn ui-btn-secondary p-2" title="تعديل" aria-label="تعديل">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>

                                @if($person->status === 'active')
                                    <form action="{{ route('user.employees.suspend', $person->id) }}" method="POST" class="js-confirm-status" data-confirm-title="إيقاف الموظف؟" data-confirm-text="سيتم إيقاف الموظف ماليًا ووظيفيًا، وسيتم إيقاف حساب المحاسب المرتبط إن وجد. لن يتم احتساب راتبه عن أيام الإيقاف.">
                                        @csrf
                                        @method('PATCH')
                                        {{-- حفظ رابط القائمة كاملًا حتى يعود المستخدم لنفس صفحة الموظفين بعد الإيقاف/التفعيل. --}}
                                        <input type="hidden" name="return_to" value="{{ url()->full() }}">
                                        <button type="submit" class="ui-btn ui-btn-danger p-2" title="إيقاف الموظف" aria-label="إيقاف الموظف"><i class="fa-solid fa-pause"></i></button>
                                    </form>
                                @else
                                    <form action="{{ route('user.employees.activate', $person->id) }}" method="POST" class="js-confirm-status" data-confirm-title="تفعيل الموظف؟" data-confirm-text="سيتم تفعيل الموظف فقط واستئناف احتساب راتبه من تاريخ التفعيل، دون تفعيل حساب المحاسب.">
                                        @csrf
                                        @method('PATCH')
                                        {{-- حفظ رابط القائمة كاملًا حتى يعود المستخدم لنفس صفحة الموظفين بعد الإيقاف/التفعيل. --}}
                                        <input type="hidden" name="return_to" value="{{ url()->full() }}">
                                        <button type="submit" class="ui-btn ui-btn-primary p-2" title="تفعيل الموظف" aria-label="تفعيل الموظف"><i class="fa-solid fa-play"></i></button>
                                    </form>
                                @endif

                                <form action="{{ route('user.employees.destroy', $person->id) }}?return_to={{ urlencode(url()->full()) }}" method="POST" class="js-confirm-status" data-confirm-title="حذف الموظف؟" data-confirm-text="سيتم نقل الموظف إلى سلة المحذوفات.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ui-btn ui-btn-danger p-2" title="حذف" aria-label="حذف">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="md:hidden space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 min-w-0"><span class="w-8 h-8 rounded-lg ui-surface-strong-bg flex items-center justify-center shrink-0"><i class="fa-solid {{ $person->activeAccountant ? 'fa-user-tie ui-status-success' : 'fa-user ui-text-muted' }}"></i></span><p class="ui-title font-bold truncate" dir="auto">{{ $person->name }}</p></div>
                            <span class="ui-text-caption ui-text-muted">{{ $person->store->name }}</span>
                        </div>
                        <p class="text-sm ui-text-muted">#{{ $loop->iteration }} · ID: {{ $person->id }} · {{ $person->phone ?? '—' }}</p>
                        @if(isset($person->salary_info) && ($person->salary_info['suspended_days'] ?? 0) > 0)
                            <div class="rounded-xl border ui-border ui-status-warning-bg p-3 ui-text-caption ui-status-warning">
                                <p class="font-bold">الراتب المستحق: {{ number_format($person->salary_info['payable_salary'], 2) }} ر.س</p>
                                <p class="ui-status-warning mt-1">أيام العمل: {{ $person->salary_info['worked_days'] }} / أيام الإيقاف: {{ $person->salary_info['suspended_days'] }}</p>
                            </div>
                        @endif
                        <div class="flex items-center gap-2">
                            @if($person->activeAccountant)
                                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 ui-text-caption font-bold ui-status-success-bg ui-status-success-border ui-status-success"><i class="fa-solid fa-user-tie"></i>محاسب فعال</span>
                            @elseif($person->accountant)
                                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 ui-text-caption font-bold ui-status-warning-bg ui-status-warning-border ui-status-warning"><i class="fa-solid fa-user-clock"></i>محاسب موقوف</span>
                            @else
                                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 ui-text-caption font-bold {{ $person->status === 'active' ? 'ui-status-info-bg ui-status-info-border ui-status-info' : 'ui-status-danger-bg ui-status-danger-border ui-status-danger' }}"><i class="fa-solid fa-user"></i>{{ $person->status === 'active' ? 'موظف' : 'موظف موقوف' }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 pt-1">
                            <a href="{{ route('user.employees.actions', ['employee' => $person->id, 'return_to' => url()->full()]) }}" class="ui-btn ui-btn-info mobile-action-icon" title="عرض" aria-label="عرض"><i class="fa-solid fa-eye"></i></a>
                            <a href="{{ route('user.employees.edit', ['employee' => $person->id, 'return_to' => url()->full()]) }}" class="ui-btn ui-btn-secondary mobile-action-icon" title="تعديل" aria-label="تعديل"><i class="fa-solid fa-pen-to-square"></i></a>
                            @if($person->status === 'active')
                                <form action="{{ route('user.employees.suspend', $person->id) }}" method="POST" class="js-confirm-status" data-confirm-title="إيقاف الموظف؟" data-confirm-text="سيتم إيقاف الموظف ماليًا ووظيفيًا، وسيتم إيقاف حساب المحاسب المرتبط إن وجد. لن يتم احتساب راتبه عن أيام الإيقاف.">
                                    @csrf
                                    @method('PATCH')
                                    {{-- حفظ رابط القائمة كاملًا حتى يعود المستخدم لنفس صفحة الموظفين بعد الإيقاف/التفعيل. --}}
                                    <input type="hidden" name="return_to" value="{{ url()->full() }}">
                                    <button type="submit" class="ui-btn ui-btn-danger mobile-action-icon" title="إيقاف الموظف" aria-label="إيقاف الموظف"><i class="fa-solid fa-pause"></i></button>
                                </form>
                            @else
                                <form action="{{ route('user.employees.activate', $person->id) }}" method="POST" class="js-confirm-status" data-confirm-title="تفعيل الموظف؟" data-confirm-text="سيتم تفعيل الموظف فقط واستئناف احتساب راتبه من تاريخ التفعيل، دون تفعيل حساب المحاسب.">
                                    @csrf
                                    @method('PATCH')
                                    {{-- حفظ رابط القائمة كاملًا حتى يعود المستخدم لنفس صفحة الموظفين بعد الإيقاف/التفعيل. --}}
                                    <input type="hidden" name="return_to" value="{{ url()->full() }}">
                                    <button type="submit" class="ui-btn ui-btn-primary mobile-action-icon" title="تفعيل الموظف" aria-label="تفعيل الموظف"><i class="fa-solid fa-play"></i></button>
                                </form>
                            @endif
                            <form action="{{ route('user.employees.destroy', $person->id) }}?return_to={{ urlencode(url()->full()) }}" method="POST" class="js-confirm-status" data-confirm-title="حذف الموظف؟" data-confirm-text="سيتم نقل الموظف إلى سلة المحذوفات.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ui-btn ui-btn-danger mobile-action-icon" title="حذف" aria-label="حذف"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center ui-text-muted py-12">لا يوجد أشخاص حتى الآن</div>
            @endforelse
        </div>
    </div>

    <div class="pt-8 flex justify-center">
        <a href="{{ route('user.employees.trash', array_filter(['from' => ($selectedStore ?? null) ? 'store' : null, 'store_id' => ($selectedStore ?? null)?->id, 'return_to' => url()->full()])) }}"
           class="inline-flex items-center gap-2 ui-status-danger-bg border ui-border ui-status-danger px-5 py-3 rounded-xl font-bold transition">
            <i class="fa-solid fa-trash-can"></i>
            سلة الموظفين المحذوفين
        </a>
    </div>

</div>

<div class="hidden" data-employee-index-interface aria-hidden="true"></div>
@endsection
