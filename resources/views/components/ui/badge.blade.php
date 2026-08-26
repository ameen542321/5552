@props(['variant' => 'info'])

@php
    $variantClass = match ($variant) {
        'success' => 'ui-badge-success',
        'warning' => 'ui-badge-warning',
        'danger' => 'ui-badge-danger',
        default => 'ui-badge-info',
    };
@endphp

<span {{ $attributes->merge(['class' => trim('ui-badge '.$variantClass)]) }}>
    {{ $slot }}
</span>
