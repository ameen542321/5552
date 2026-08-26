@extends('dashboard.app')

@section('title', 'تعديل بيانات المستخدم')

@section('content')
<div class="mx-auto max-w-4xl p-4 text-right sm:p-6" dir="rtl">

    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3 sm:items-center sm:gap-4">
            <a href="{{ route('admin.users.index') }}"
               class="ui-btn ui-btn-secondary flex h-10 w-10 shrink-0 items-center justify-center p-0"
               aria-label="العودة إلى قائمة المستخدمين">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold ui-title">تعديل الملف الشخصي</h1>
                <p class="ui-text-soft mt-1">تحديث صلاحيات وبيانات: <span class="ui-brand-text font-medium">{{ $user->name }}</span></p>
            </div>
        </div>
        <div class="hidden md:block">
            <span class="px-3 py-1 ui-status-info-bg border ui-border ui-brand-text rounded-lg ui-text-caption font-mono uppercase">
                User ID: #{{ $user->id }}
            </span>
        </div>
    </div>

    @if($errors->any())
    <div class="ui-alert ui-alert-danger-plain mb-6" role="alert">
        <ul class="text-sm ui-status-danger space-y-1">
            @foreach($errors->all() as $error)
                <li class="flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation ui-text-caption"></i>
                    {{ $error }}
                </li>
            @endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="lg:col-span-2 space-y-6">
                <div class="ui-card p-5 sm:p-6">
                    <h3 class="ui-text-soft font-semibold mb-6 flex items-center gap-2 text-sm uppercase tracking-wider">
                        <i class="fa-solid fa-id-card ui-status-info"></i> المعلومات الشخصية
                    </h3>

                    <div class="space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block ui-text-caption ui-text-muted mb-2 mr-1">الاسم الكامل</label>
                                <input type="text" name="name" value="{{ old('name', $user->name) }}"
                                       class="w-full px-4 py-3 ui-input transition outline-none">
                            </div>
                            <div>
                                <label class="block ui-text-caption ui-text-muted mb-2 mr-1">رقم الهاتف</label>
                                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                                       class="w-full px-4 py-3 ui-input transition outline-none text-right">
                            </div>
                        </div>

                        <div>
                            <label class="block ui-text-caption ui-text-muted mb-2 mr-1">البريد الإلكتروني</label>
                            <input type="email" name="email" value="{{ old('email', $user->email) }}"
                                   class="w-full px-4 py-3 ui-input transition outline-none">
                        </div>
                    </div>
                </div>

                <div class="ui-card p-5 sm:p-6">
                    <h3 class="ui-text-soft font-semibold mb-6 flex items-center gap-2 text-sm uppercase tracking-wider">
                        <i class="fa-solid fa-box-open ui-status-info"></i> باقة الاشتراك
                    </h3>
                    <select name="plan_id" class="w-full px-4 py-3 ui-input outline-none appearance-none color-scheme-dark">
                        <option value="">بدون باقة (حساب يدوي)</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" {{ $user->plan_id == $plan->id ? 'selected' : '' }}>
                                {{ $plan->name }} ({{ $plan->allowed_stores }} متجر / {{ $plan->allowed_accountants }} محاسب)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="ui-card p-5 sm:p-6">
                    <h3 class="ui-text-soft font-semibold mb-6 flex items-center gap-2 text-sm uppercase tracking-wider">
                        <i class="fa-solid fa-sliders ui-status-info"></i> حدود النظام (Limits)
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block ui-text-caption ui-text-muted mb-2 mr-1">المتاجر المسموحة</label>
                            <input type="number" name="allowed_stores" value="{{ old('allowed_stores', $user->allowed_stores) }}"
                                   class="w-full px-4 py-3 ui-input outline-none">
                        </div>
                        <div>
                            <label class="block ui-text-caption ui-text-muted mb-2 mr-1">المحاسبين المسموح بهم</label>
                            <input type="number" name="allowed_accountants" value="{{ old('allowed_accountants', $user->allowed_accountants) }}"
                                   class="w-full px-4 py-3 ui-input outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="ui-card relative overflow-hidden p-5 sm:p-6">
                    <div class="absolute top-0 right-0 w-1 h-full {{ $user->status == 'active' ? 'ui-status-success-bg' : 'ui-status-danger-bg' }}"></div>
                    <h3 class="ui-text-soft font-semibold mb-6 text-sm uppercase tracking-wider">الحالة والتواريخ</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block ui-text-caption ui-text-muted mb-2 mr-1">حالة الحساب</label>
                            <select name="status" class="w-full px-4 py-3 ui-input outline-none appearance-none">
                                <option value="active" {{ $user->status == 'active' ? 'selected' : '' }}>نشط (Active)</option>
                                <option value="suspended" {{ $user->status == 'suspended' ? 'selected' : '' }}>موقف (Suspended)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block ui-text-caption ui-text-muted mb-2 mr-1">انتهاء الاشتراك</label>
                            <input type="date" name="subscription_end_at"
                                   value="{{ $user->subscription_end_at ? \Carbon\Carbon::parse($user->subscription_end_at)->format('Y-m-d') : '' }}"
                                   class="w-full px-4 py-3 ui-input outline-none color-scheme-dark">
                        </div>

                        <div>
                            <label class="block ui-text-caption ui-text-muted mb-2 mr-1">تاريخ إغلاق الحساب (Expiry)</label>
                            <input type="date" name="expires_at"
                                   value="{{ $user->expires_at ? \Carbon\Carbon::parse($user->expires_at)->format('Y-m-d') : '' }}"
                                   class="w-full px-4 py-3 ui-input outline-none color-scheme-dark">
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <button type="submit"
                            class="ui-btn ui-btn-primary w-full py-4">
                        <i class="fa-solid fa-check-circle"></i>
                        حفظ التغييرات
                    </button>
                    <a href="{{ route('admin.users.index') }}"
                       class="ui-btn ui-btn-secondary w-full py-4">
                        إلغاء
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
