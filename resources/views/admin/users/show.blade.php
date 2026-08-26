@extends('dashboard.app')

@section('title', 'تفاصيل التاجر | ' . $user->name)

@section('content')
<div class="mx-auto max-w-7xl space-y-6 p-4 text-right sm:p-6" dir="rtl"
     x-data="{ supportModal: false, supportTarget: '', supportAction: '' }">

    <div class="ui-card flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:text-right">
            <div class="ui-card-muted flex h-16 w-16 shrink-0 items-center justify-center text-2xl font-bold ui-title" aria-hidden="true">
                {{ mb_substr($user->name, 0, 1) }}
            </div>
            <div>
                <h1 class="text-2xl font-bold ui-title">{{ $user->name }}</h1>
                <div class="ui-text-soft mt-2 flex flex-col items-center gap-2 sm:flex-row sm:flex-wrap">
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-envelope ui-status-info"></i> {{ $user->email }}</span>
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-phone ui-status-info"></i> {{ $user->phone ?? 'لا يوجد هاتف' }}</span>
                </div>
            </div>
        </div>
        <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-2 lg:w-auto lg:grid-cols-4">
            <button type="button" class="ui-btn ui-btn-warning w-full"
                    @click="supportTarget = @js('المالك: ' . $user->name); supportAction = @js(route('admin.support.owner.start', $user)); supportModal = true">
                <i class="fa-solid fa-headset"></i> دخول كمالك
            </button>
            <form action="{{ route('admin.users.toggleStatus', $user->id) }}" method="POST"
                  data-ui-confirm="سيتم {{ $user->status == 'active' ? 'إيقاف' : 'تفعيل' }} حساب {{ $user->name }}."
                  data-ui-confirm-title="تأكيد تغيير حالة الحساب">
                @csrf
                @method('PATCH')
                <button type="submit" class="ui-btn {{ $user->status == 'active' ? 'ui-btn-warning' : 'ui-btn-primary' }} w-full">
                    <i class="fa-solid {{ $user->status == 'active' ? 'fa-pause' : 'fa-play' }}"></i>
                    {{ $user->status == 'active' ? 'إيقاف مؤقت' : 'تفعيل الحساب' }}
                </button>
            </form>
            <a href="{{ route('admin.users.edit', $user->id) }}" class="ui-btn ui-btn-primary w-full">
                <i class="fa-solid fa-pen-to-square"></i> تعديل
            </a>
            <a href="{{ route('admin.users.index') }}" class="ui-btn ui-btn-secondary w-full">
                رجوع <i class="fa-solid fa-arrow-left"></i>
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        {{-- الحالة --}}
        <div class="ui-card p-5">
            <p class="ui-text-caption ui-text-muted uppercase font-bold tracking-wider mb-1">حالة الحساب</p>
            <div class="text-xl font-bold {{ $user->status == 'active' ? 'ui-status-success' : 'ui-status-danger' }}">
                {{ $user->status == 'active' ? 'نشط' : 'موقوف' }}
            </div>
        </div>

        {{-- الخطة --}}
        <div class="ui-card p-5">
            <p class="ui-text-caption ui-text-muted uppercase font-bold tracking-wider mb-1">باقة الاشتراك</p>
            <div class="text-xl font-bold ui-brand-text">{{ $user->plan->name ?? 'بدون خطة' }}</div>
        </div>

        {{-- المتاجر --}}
        <div class="ui-card p-5">
            <p class="ui-text-caption ui-text-muted uppercase font-bold tracking-wider mb-1">المتاجر</p>
            <div class="flex items-end gap-2 text-xl font-bold ui-title">
                {{ $user->stores->count() }} <span class="text-sm ui-text-muted font-normal">من أصل {{ $user->allowed_stores }}</span>
            </div>
        </div>

        {{-- المحاسبين --}}
        <div class="ui-card p-5">
            <p class="ui-text-caption ui-text-muted uppercase font-bold tracking-wider mb-1">المحاسبين</p>
            <div class="flex items-end gap-2 text-xl font-bold ui-title">
                {{ $user->accountants->count() }} <span class="text-sm ui-text-muted font-normal">من أصل {{ $user->allowed_accountants }}</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="ui-card overflow-hidden p-0">
                <div class="p-6 border-b ui-border">
                    <h3 class="ui-title font-bold flex items-center gap-2">
                        <i class="fa-solid fa-store ui-status-info"></i> المتاجر التابعة
                    </h3>
                </div>
                <div class="ui-table-wrap rounded-none border-0">
                    <table class="ui-table min-w-[520px] text-right">
                        <thead class="ui-surface-muted-bg ui-text-muted">
                            <tr>
                                <th class="px-6 py-4">اسم المتجر</th>
                                <th class="px-6 py-4 text-center">الحالة</th>
                                <th class="px-6 py-4">تاريخ الإنشاء</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ui-border">
                            @forelse($user->stores as $store)
                            <tr class="ui-hover-info-bg">
                                <td class="px-6 py-4 ui-text-soft">{{ $store->name }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-2.5 py-1 rounded-lg ui-text-caption font-bold {{ $store->status == 'active' ? 'ui-status-success-bg ui-status-success' : 'ui-status-danger-bg ui-status-danger' }}">
                                        {{ $store->status == 'active' ? 'نشط' : 'موقف' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 ui-text-muted">{{ $store->created_at->format('Y-m-d') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="px-6 py-10 text-center ui-text-muted">لا توجد متاجر</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ui-card overflow-hidden p-0">
                <div class="border-b ui-border p-6">
                    <h3 class="ui-title flex items-center gap-2 font-bold"><i class="fa-solid fa-user-tie ui-status-info"></i> المحاسبون وجلسات الدعم</h3>
                </div>
                <div class="ui-table-wrap rounded-none border-0">
                    <table class="ui-table min-w-[560px] text-right">
                        <thead><tr><th>المحاسب</th><th>المتجر</th><th>الحالة</th><th>الدعم</th></tr></thead>
                        <tbody>
                            @forelse($accountants as $accountant)
                                <tr>
                                    <td>{{ $accountant->name }}</td>
                                    <td>{{ $accountant->store?->name ?? '—' }}</td>
                                    <td>{{ $accountant->status === 'active' ? 'نشط' : 'موقوف' }}</td>
                                    <td>
                                        <button type="button" class="ui-btn ui-btn-secondary"
                                                @click="supportTarget = @js('المحاسب: ' . $accountant->name); supportAction = @js(route('admin.support.accountant.start', $accountant)); supportModal = true">
                                            <i class="fa-solid fa-headset"></i> دخول كمحاسب
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-8 text-center ui-text-muted">لا يوجد محاسبون مرتبطون بهذا المالك.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="ui-card p-5 sm:p-6">
                <h3 class="ui-text-soft font-semibold mb-6 text-sm border-b ui-border pb-4">مواعيد هامة</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="ui-text-muted text-sm">تاريخ التسجيل</span>
                        <span class="ui-title text-sm">{{ $user->created_at->format('Y-m-d') }}</span>
                    </div>
                    @php
                        $subEnd = $user->subscription_end_at ? \Carbon\Carbon::parse($user->subscription_end_at) : null;
                        $expires = $user->expires_at ? \Carbon\Carbon::parse($user->expires_at) : null;
                    @endphp
                    <div class="flex items-center justify-between">
                        <span class="ui-text-muted text-sm">انتهاء الاشتراك</span>
                        <span class="px-3 py-1 rounded-lg ui-text-caption {{ $subEnd && $subEnd->isFuture() ? 'ui-status-success-bg' : 'ui-status-danger-bg' }}">
                            {{ $subEnd ? $subEnd->format('Y-m-d') : 'غير محدد' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="ui-text-muted text-sm">تاريخ الإغلاق (Expiry)</span>
                        <span class="ui-text-soft text-sm">{{ $expires ? $expires->format('Y-m-d') : 'غير محدد' }}</span>
                    </div>
                </div>
            </div>

            <div class="ui-card p-5 sm:p-6">
                <h3 class="ui-title mb-3 flex items-center gap-2 font-semibold">
                    <i class="fa-solid fa-trash-can ui-status-danger"></i> نقل الحساب إلى السلة
                </h3>
                <p class="ui-text-soft mb-5">
                    يمكن استعادة الحساب لاحقًا من سلة المحذوفات ما لم يُحذف نهائيًا منها.
                </p>
                <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST"
                      data-ui-confirm="سيُنقل حساب {{ $user->name }} إلى سلة المحذوفات."
                      data-ui-confirm-title="نقل المستخدم إلى السلة؟">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="ui-btn ui-btn-danger w-full py-3">
                        نقل الحساب إلى السلة
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div x-show="supportModal" x-cloak class="ui-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="support-session-title" @keydown.escape.window="supportModal = false">
        <div @click.away="supportModal = false" class="ui-modal-panel ui-modal-panel-transfer">
            <div class="ui-modal-header">
                <h2 id="support-session-title" class="ui-title text-xl font-bold">بدء جلسة دعم تقني</h2>
                <button type="button" @click="supportModal = false" class="ui-modal-close-danger" aria-label="إغلاق نافذة جلسة الدعم"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
            <div class="p-5 sm:p-6">
                <div class="flex items-center justify-center gap-2"><p class="ui-text-soft text-center" x-text="supportTarget"></p><button type="button" class="ui-help-btn" data-ui-help-title="جلسة الدعم التقني" data-ui-help-body="حدد سبب الدخول بدقة. إذا كان لديك رقم تذكرة فاكتبه؛ وإلا سيصدر النظام رقمًا تلقائيًا. بعد البدء ستظهر جلسة الدعم في أعلى جميع الصفحات ويمكن إنهاؤها من هناك." aria-label="شرح جلسة الدعم"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button></div>
                <div class="ui-alert ui-alert-warning mt-4">
                    ستكون الصلاحيات كاملة، وستظهر العمليات باسم الدعم التقني ورقم التذكرة.
                </div>
                <form method="POST" :action="supportAction" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="support-reason" class="ui-text-soft mb-1 block">سبب الدخول <span class="ui-status-danger">*</span></label>
                    <textarea id="support-reason" name="reason" required minlength="5" maxlength="1000" rows="4"
                              class="ui-input w-full" placeholder="اكتب المشكلة أو العمل المطلوب مراجعته..."></textarea>
                </div>
                <div>
                    <label for="support-ticket" class="ui-text-soft mb-1 block">رقم تذكرة الدعم</label>
                    <input id="support-ticket" name="ticket_reference" maxlength="100" class="ui-input w-full" placeholder="اتركه فارغًا ليولده النظام تلقائيًا">
                </div>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" @click="supportModal = false" class="ui-btn ui-btn-danger">إلغاء</button>
                    <button type="submit" class="ui-btn ui-btn-warning">بدء جلسة الدعم</button>
                </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
