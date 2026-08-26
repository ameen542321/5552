@props([
    'pageTitle',
    'heading',
    'message',
    'statusCode' => null,
    'statusClass' => 'ui-status-info',
    'icon' => '!',
    'maxWidth' => 'max-w-2xl',
])

<!DOCTYPE html>
<html lang="ar" dir="rtl" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ui-page min-h-screen">
    <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:py-10">
        <section class="ui-card w-full {{ $maxWidth }} p-5 text-center shadow-2xl sm:p-8">
            <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl ui-surface-strong-bg {{ $statusClass }}">
                <span class="text-3xl font-black" aria-hidden="true">{{ $icon }}</span>
            </div>

            @if($statusCode)
                <p class="text-6xl font-black {{ $statusClass }} sm:text-7xl">{{ $statusCode }}</p>
            @endif

            <h1 class="{{ $statusCode ? 'mt-4 text-xl sm:text-2xl' : 'text-2xl sm:text-3xl' }} font-black ui-title">{{ $heading }}</h1>
            <p class="mt-3 break-words text-base leading-8 ui-text-soft sm:text-lg">{{ $message }}</p>

            <div class="mt-8 grid w-full grid-cols-1 gap-3 sm:flex sm:flex-wrap sm:justify-center">
                {{ $slot }}
            </div>
        </section>
    </main>
</body>
</html>
