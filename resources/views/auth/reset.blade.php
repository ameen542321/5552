@extends('layouts.auth')

@section('content')

<div class="auth-shell auth-panel">
    <h1 class="mb-6 text-center text-2xl font-black ui-title">إعادة تعيين كلمة المرور</h1>

    @if ($errors->any())
        <div class="ui-alert ui-badge-danger mb-5 text-sm">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <input type="hidden" name="email" value="{{ $email }}">

        <div class="mb-4">
            <label for="reset-password" class="auth-label">كلمة المرور الجديدة</label>
            <input id="reset-password"
                   type="password"
                   name="password"
                   class="ui-input"
                   required>
        </div>

        <div class="mb-4">
            <label for="reset-password-confirmation" class="auth-label">تأكيد كلمة المرور</label>
            <input id="reset-password-confirmation"
                   type="password"
                   name="password_confirmation"
                   class="ui-input"
                   required>
        </div>

        <button class="ui-btn ui-btn-primary w-full mb-4">
            تحديث كلمة المرور
        </button>

        <div class="text-center">
            <a href="{{ route('login') }}" class="auth-link">
                العودة لتسجيل الدخول
            </a>
        </div>

    </form>

</div>

@endsection
