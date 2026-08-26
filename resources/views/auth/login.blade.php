@extends('layouts.auth')

@section('content')
<div class="auth-card">
    <div class="auth-brand">
        <div class="auth-logo">
            <span class="auth-logo-accent">CAR</span><span>LED</span>
        </div>
        <span class="auth-brand-line"></span>
        <p class="auth-tagline">Smart Management System</p>
    </div>

    <div class="auth-shell auth-panel">
        <h1 class="text-xl font-black text-center mb-8 text-[var(--ui-text)]">الدخول إلى النظام</h1>

        @if ($errors->any() || session('auth') || session('status'))
            <div class="mb-6 space-y-3">
                @if ($errors->any())
                    <div class="ui-alert ui-badge-danger text-sm">
                        @foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach
                    </div>
                @endif
                @if (session('auth'))
                    <div class="ui-alert ui-alert-warning text-sm">{{ session('auth') }}</div>
                @endif
                @if (session('status'))
                    <div class="ui-alert ui-badge-success text-sm">{{ session('status') }}</div>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}">
            @csrf

            <div class="space-y-5">
                <div>
                    <label for="login-email" class="auth-label">البريد الإلكتروني</label>
                    <input id="login-email" type="email" name="email" value="{{ old('email') }}"
                           class="ui-input"
                           placeholder="name@company.com" required autofocus>
                </div>

                <div>
                    <label for="login-password" class="auth-label">كلمة المرور</label>
                    <div class="relative" x-data="{ passwordVisible: false }">
                        <input id="login-password" :type="passwordVisible ? 'text' : 'password'" name="password"
                               class="ui-input pl-12"
                               required>
                        <button type="button"
                                class="auth-password-toggle absolute left-1 top-1/2 -translate-y-1/2"
                                @click="passwordVisible = !passwordVisible"
                                :aria-label="passwordVisible ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
                                :title="passwordVisible ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'">
                            <i class="fa-solid" :class="passwordVisible ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="mt-2 flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <label class="flex items-center cursor-pointer group">
                        <input id="login-remember" type="checkbox" name="remember" class="auth-check-input peer">
                        <span class="auth-check">
                            <svg class="w-3 h-3 opacity-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        </span>
                        <span class="mr-2 ui-text-soft transition-colors">تذكرني</span>
                    </label>
                    <a href="{{ route('password.request') }}" class="auth-link">نسيت كلمة المرور؟</a>
                </div>

                <button type="submit" class="ui-btn ui-btn-primary w-full mt-4 py-3.5">
                    تسجيل الدخول
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
