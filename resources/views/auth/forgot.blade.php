@extends('layouts.auth')

@section('content')

<div class="auth-shell auth-panel">
    <h1 class="mb-6 text-center text-2xl font-black ui-title">نسيت كلمة المرور</h1>

    <p class="ui-text-soft text-center mb-6 leading-7">
        أدخل بريدك الإلكتروني وسنرسل لك رابطًا لإعادة تعيين كلمة المرور.
    </p>

    @if ($errors->any())
        <div class="ui-alert ui-badge-danger mb-5 text-sm">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    @if (session('status'))
        <div class="ui-alert ui-badge-success mb-5 text-sm">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-4">
            <label for="forgot-email" class="auth-label">البريد الإلكتروني</label>
            <input id="forgot-email"
                   type="email"
                   name="email"
                   value="{{ old('email') }}"
                   class="ui-input"
                   placeholder="example@email.com"
                   required>
        </div>

        <button class="ui-btn ui-btn-primary w-full mb-4">
            إرسال رابط إعادة التعيين
        </button>

        <div class="text-center">
            <a href="{{ route('login') }}" class="auth-link">
                العودة لتسجيل الدخول
            </a>
        </div>

    </form>

</div>

@endsection
