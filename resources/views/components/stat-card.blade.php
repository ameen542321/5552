{{-- إصلاح مطبق: بطاقة الإحصاء تحافظ على attributes وتطبع معرّف القيمة للتحديث الحي. --}}
@props([
    'title' => '',
    'value' => '',
    'color' => 'success',
    'valueId' => null,
])

@php
    $statuses = [
        'emerald' => 'ui-status-success',
        'success' => 'ui-status-success',
        'red' => 'ui-status-danger',
        'danger' => 'ui-status-danger',
        'yellow' => 'ui-status-warning',
        'warning' => 'ui-status-warning',
        'indigo' => 'ui-status-info',
        'blue' => 'ui-status-info',
        'info' => 'ui-status-info',
        'gray' => 'ui-text-soft',
    ];

    $colorClass = $statuses[$color] ?? 'ui-title';
@endphp

<div {{ $attributes->class(['ui-card rounded-2xl p-5']) }}>
    <p class="ui-text-caption ui-text-soft">{{ $title }}</p>
    <p @if($valueId) id="{{ $valueId }}" @endif class="text-2xl font-bold {{ $colorClass }} mt-2">
        {{ $value }}
    </p>
</div>
