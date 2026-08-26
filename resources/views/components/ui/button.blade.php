{{-- إصلاح مطبق: مكوّن الزر يدعم أدوار الهوية المركزية بما فيها التحذير. --}}
@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
])

@php
    $variantClass = match ($variant) {
        'secondary' => 'ui-btn-secondary',
        'warning' => 'ui-btn-warning',
        'danger' => 'ui-btn-danger',
        default => 'ui-btn-primary',
    };

    $classes = trim('ui-btn '.$variantClass);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
