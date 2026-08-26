@props(['padding' => 'normal'])

@php
    $paddingClass = match ($padding) {
        'none' => '',
        'sm' => 'p-4',
        'lg' => 'p-6',
        default => 'p-5',
    };
@endphp

<div {{ $attributes->merge(['class' => trim('ui-card '.$paddingClass)]) }}>
    {{ $slot }}
</div>
