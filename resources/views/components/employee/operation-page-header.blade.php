@props([
    'title',
    'subtitle',
    'subtitleAsHelp' => false,
    'icon' => '👤',
    'accent' => 'info',
    'backRoute' => 'accountant.dashboard',
])

@php
    $accentClasses = [
        'info' => 'ui-op-accent-info',
        'success' => 'ui-op-accent-success',
        'danger' => 'ui-op-accent-danger',
        'warning' => 'ui-op-accent-warning',
    ];

    $iconClass = $accentClasses[$accent] ?? $accentClasses['info'];
@endphp

<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4 sm:mb-5">
    <div class="flex items-center gap-3 min-w-0">
        <div class="w-10 h-10 rounded-2xl {{ $iconClass }} flex items-center justify-center shadow-lg flex-shrink-0">
            <span class="ui-title text-lg font-bold">{{ $icon }}</span>
        </div>
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <h1 class="text-xl sm:text-2xl font-bold ui-title truncate">{{ $title }}</h1>
                @if($subtitleAsHelp)
                    <x-ui.help title="توضيح {{ $title }}" :body="$subtitle" />
                @endif
            </div>
            @unless($subtitleAsHelp)
                <p class="ui-text-caption sm:text-sm ui-text-soft mt-0.5">{{ $subtitle }}</p>
            @endunless
        </div>
    </div>

    <a href="{{ route($backRoute) }}"
       class="ui-btn ui-btn-secondary w-full px-3 py-2 text-sm sm:w-fit">
        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        الرجوع
    </a>
</div>
