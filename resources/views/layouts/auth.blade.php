<!DOCTYPE html>
<html lang="ar" dir="rtl" class="dark ui-font-loading">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preload" href="{{ asset('fonts/cairo/cairo-arabic-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/Cairo-Regular.ttf') }}" as="font" type="font/ttf" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <title>@yield('title', 'CARLED - تسجيل الدخول')</title>
</head>

<body class="auth-page auth-page-center">
    <x-ui.page-loader />
    <main class="w-full max-w-md">
        @yield('content')
    </main>
</body>
</html>
