<!DOCTYPE html>
<html lang="ar" dir="rtl" class="dark ui-font-loading">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preload" href="{{ asset('fonts/cairo/cairo-arabic-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/Cairo-Regular.ttf') }}" as="font" type="font/ttf" crossorigin>
    <title>مرحباً بك</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="auth-page auth-page-center">
    <x-ui.page-loader />
    <div class="auth-shell auth-shell-wide" data-welcome-progress>
        <h1 class="break-words text-2xl font-black ui-title sm:text-3xl">مرحباً {{ auth()->user()->name }} 👋</h1>
        <p class="mt-3 text-sm ui-text-soft">يتم تجهيز حسابك الآن…</p>

        <div class="auth-progress-wrap">
            <progress id="welcomeProgress" class="auth-progress" value="0" max="100" aria-label="تجهيز الحساب"></progress>
            <p class="mt-3 text-sm ui-text-soft">جاري تجهيز حسابك…</p>
        </div>

        <form id="welcomeContinueForm" method="POST" action="{{ route('welcome.continue') }}">
            @csrf
            <button id="skipBtn" class="ui-btn ui-btn-secondary mt-9 hidden w-full" type="submit">تخطي الآن</button>
        </form>
    </div>

</body>
</html>
