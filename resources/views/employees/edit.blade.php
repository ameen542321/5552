@extends('dashboard.app')

@section('title', 'تعديل بيانات موظف')

@section('content')

<div class="px-4 sm:px-6 py-6 sm:py-8 max-w-3xl mx-auto">

    <!-- العنوان + زر الرجوع -->
    {{-- وضع زر الرجوع أعلى العنوان على الجوال يمنع تزاحم الرأس. --}}
    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl sm:text-3xl font-bold ui-title">تعديل موظف</h1>
                <x-ui.help title="تعديل موظف" body="قم بتحديث بيانات الموظف وتعديل معلوماته الأساسية." />
            </div>
        </div>

        <a href="{{ request('return_to', route('user.employees.index')) }}"
           class="inline-flex items-center gap-2 ui-text-soft ui-hover-info ui-card px-4 py-2 rounded-lg shadow ui-hover-info-bg transition">
            <i class="fa-solid fa-arrow-right"></i>
            رجوع
        </a>
    </div>

    <!-- بطاقة النموذج -->
    <div class="ui-card shadow-xl rounded-xl p-4 sm:p-8">

        {{-- إصلاح مطبق: حُفظ نص التحذير التشغيلي ونُقل تأكيد الحفظ إلى الحوار المركزي. --}}
        <form action="{{ route('user.employees.update', $employee->id) }}" method="POST" class="space-y-6"
              data-ui-confirm="إذا تم تغيير المتجر: سيتم نقل المديونيات فقط، وستبقى السحوبات والغيابات وتقارير الرواتب على المتجر الذي حدثت فيه حتى تظهر كاملة في تقرير الموظف والتقارير الشهرية. هل تريد المتابعة؟"
              data-ui-confirm-title="تأكيد تحديث الموظف">
            @csrf
            @method('PUT')

            <input type="hidden" name="return_to" value="{{ request('return_to') }}">

            <!-- الاسم -->
            <div>
                <label class="block ui-text-soft font-medium mb-1">اسم الموظف</label>
                <div class="relative">
                    <input type="text" name="name" value="{{ $employee->name }}" required
                           class="w-full ui-card ui-text-soft rounded-lg px-10 py-2
                                 ">
                    <i class="fa-solid fa-user ui-text-muted absolute left-3 top-1/2 -translate-y-1/2"></i>
                </div>
                @error('name')
                    <p class="ui-status-danger text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- الجوال -->
            <div>
                <label class="block ui-text-soft font-medium mb-1">رقم الجوال</label>
                <div class="relative">
                    <input type="text" name="phone" value="{{ $employee->phone }}"
                           class="w-full ui-card ui-text-soft rounded-lg px-10 py-2
                                 ">
                    <i class="fa-solid fa-phone ui-text-muted absolute left-3 top-1/2 -translate-y-1/2"></i>
                </div>
                @error('phone')
                    <p class="ui-status-danger text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            @if($employee->accountant)
                <div class="rounded-2xl border ui-border ui-surface-strong-bg p-4 space-y-4">
                    <div class="flex items-center gap-2">
                        <h2 class="ui-title font-black">بيانات دخول المحاسب</h2>
                        <x-ui.help title="بيانات دخول المحاسب" body="يمكن للمالك تغيير بريد دخول المحاسب أو تعيين كلمة مرور جديدة. اترك حقلي كلمة المرور فارغين للإبقاء على الكلمة الحالية." />
                    </div>

                    <div>
                        <label class="block ui-text-soft font-medium mb-1">البريد الإلكتروني للمحاسب</label>
                        <input type="email" name="accountant_email" value="{{ old('accountant_email', $employee->accountant->email) }}" required class="ui-input w-full">
                        @error('accountant_email')<p class="ui-status-danger text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block ui-text-soft font-medium mb-1">كلمة مرور جديدة</label>
                            <input type="password" name="accountant_password" autocomplete="new-password" class="ui-input w-full" placeholder="اتركها فارغة دون تغيير">
                            @error('accountant_password')<p class="ui-status-danger text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block ui-text-soft font-medium mb-1">تأكيد كلمة المرور</label>
                            <input type="password" name="accountant_password_confirmation" autocomplete="new-password" class="ui-input w-full" placeholder="أعد كتابة الكلمة الجديدة">
                        </div>
                    </div>
                </div>
            @endif

            <!-- الراتب -->
            <div>
                <label class="block ui-text-soft font-medium mb-1">الراتب الشهري</label>
                <div class="relative">
                    <input type="number" name="salary" value="{{ $employee->salary }}" required step="0.01"
                           class="w-full ui-card ui-text-soft rounded-lg px-10 py-2
                                 ">
                    <i class="fa-solid fa-money-bill ui-text-muted absolute left-3 top-1/2 -translate-y-1/2"></i>
                </div>
                @error('salary')
                    <p class="ui-status-danger text-sm mt-1">{{ $message }}</p>
                @enderror
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-xl border ui-border ui-surface-strong-bg p-3">
                    <label class="flex items-center gap-2 text-sm ui-text-soft">
                        <input type="radio" name="salary_effective_mode" value="today" class="ui-brand-text" checked>
                        تطبيق تغيير الراتب من تاريخ اليوم
                    </label>
                    <label class="flex items-center gap-2 text-sm ui-text-soft">
                        <input type="radio" name="salary_effective_mode" value="custom" class="ui-brand-text">
                        تحديد تاريخ من الشهر الحالي
                    </label>
                    <div class="sm:col-span-2">
                        <div class="mb-2 flex items-center gap-2">
                            <label class="ui-text-soft text-sm">تاريخ تطبيق الراتب</label>
                            <x-ui.help title="تاريخ تطبيق الراتب" body="يُستخدم هذا التاريخ في البطاقات والإحصائيات والتقارير عند احتساب الراتب داخل الشهر." />
                        </div>
                        <input type="date" name="salary_effective_date" min="{{ now()->startOfMonth()->toDateString() }}" max="{{ now()->endOfMonth()->toDateString() }}"
                               class="w-full ui-card ui-text-soft rounded-lg px-3 py-2">
                    </div>
                    @error('salary_effective_date')
                        <p class="sm:col-span-2 ui-status-danger text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- المتجر -->
            <div>
                <label class="block ui-text-soft font-medium mb-1">المتجر</label>
                <div class="relative">
                    <select name="store_id"
                            class="w-full ui-card ui-text-soft rounded-lg px-10 py-2
                                  ">
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" {{ $employee->store_id == $store->id ? 'selected' : '' }}>
                                {{ $store->name }}
                            </option>
                        @endforeach
                    </select>
                    <i class="fa-solid fa-store ui-text-muted absolute left-3 top-1/2 -translate-y-1/2"></i>
                </div>
                @error('store_id')
                    <p class="ui-status-danger text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <div class="mb-1 flex items-center gap-2">
                    <label class="ui-text-soft font-medium">تاريخ النقل عند تغيير المتجر</label>
                    <x-ui.help title="تاريخ النقل" body="يُطلب هذا التاريخ فقط إذا تغيّر المتجر، ويجب أن يكون من اليوم أو الأيام الماضية ضمن الشهر الحالي." />
                </div>
                <input type="date" name="transfer_effective_date" value="{{ old('transfer_effective_date', now()->toDateString()) }}" min="{{ now()->startOfMonth()->toDateString() }}" max="{{ now()->toDateString() }}"
                       class="w-full ui-card ui-text-soft rounded-lg px-3 py-2">
                @error('transfer_effective_date')
                    <p class="ui-status-danger text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="ui-inline-frame">
                <span class="ui-title font-bold">تنبيه عند تغيير المتجر</span>
                <x-ui.help variant="warning" title="تنبيه عند تغيير المتجر" body="سيتم نقل المديونيات فقط. السحوبات والغيابات وتقارير الرواتب ستبقى على المتجر الذي حدثت فيه، وتقرير الموظف سيعرض أيام الشهر وعملياته كاملة حتى لو تم نقله أثناء الشهر." />
            </div>

            <!-- زر التحديث -->
            <div class="pt-4 flex justify-between">



                <button
                    class="ui-btn ui-btn-primary ui-title px-6 py-2.5 rounded-lg shadow ui-hover-info-bg transition font-semibold">
                    تحديث البيانات
                </button>
            </div>

        </form>

    </div>

</div>

@endsection
