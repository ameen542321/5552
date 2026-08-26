@props([
    'title' => 'توضيح',
    'body',
    'variant' => 'info',
])

<span class="inline-flex">
    <button type="button"
            class="{{ $variant === 'warning' ? 'ui-warning-help-btn' : 'ui-help-btn' }}"
            data-ui-help-title="{{ $title }}"
            data-ui-help-body="{{ $body }}"
            aria-label="{{ $title }}">
        <i class="fa-solid {{ $variant === 'warning' ? 'fa-triangle-exclamation' : 'fa-lightbulb' }}" aria-hidden="true"></i>
    </button>
</span>
