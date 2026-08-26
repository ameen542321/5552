<!DOCTYPE html>
<html lang="ar" dir="rtl" class="dark ui-font-loading">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preload" href="{{ asset('fonts/cairo/cairo-arabic-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/Cairo-Regular.ttf') }}" as="font" type="font/ttf" crossorigin>
    <title>تم إيقاف حسابك</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page auth-page-center">
    <x-ui.page-loader />
    <div class="auth-shell max-w-sm text-center">
        <div class="auth-icon-danger">⛔</div>

        <h1 class="text-2xl font-black ui-title">تم إيقاف حسابك</h1>

        <p class="mt-3 leading-8 ui-text-soft">
            تم إيقاف حسابك من قبل الإدارة.<br>
            إذا كنت تعتقد أن هذا خطأ، يرجى التواصل مع الدعم.
        </p>

        @if(auth('accountant')->check())
            <form action="{{ route('accountant.logout') }}" method="POST" class="mt-6">
                @csrf
                <button type="submit" class="ui-btn ui-btn-danger w-full">تسجيل الخروج</button>
            </form>
        @elseif(auth('web')->check())
            <form action="{{ route('logout') }}" method="POST" class="mt-6">
                @csrf
                <button type="submit" class="ui-btn ui-btn-danger w-full">تسجيل الخروج</button>
            </form>
        @else
            <a href="/" class="ui-btn ui-btn-danger mt-6 inline-flex w-full items-center justify-center">العودة للصفحة الرئيسية</a>
        @endif
    </div>
</body>
</html>
