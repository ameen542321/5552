@php
    // تحديد الحالة برمجياً إذا لم يتم تمريرها
    $displayStatus = $status ?? ($store->trashed() ? 'deleted' : $store->status);

    $colors = [
        'active'    => 'ui-status-success-bg ui-status-success ui-border',
        'suspended' => 'ui-status-danger-bg ui-status-danger ui-border',
        'deleted'   => 'ui-surface-muted-bg ui-text-muted ui-border',
    ];

    $label = [
        'active'    => 'نشط',
        'suspended' => 'موقوف',
        'deleted'   => 'محذوف مؤقتاً',
    ];
@endphp

<span class="px-3 py-1 ui-text-caption font-medium rounded-full border {{ $colors[$displayStatus] ?? $colors['active'] }}">
    {{ $label[$displayStatus] ?? $label['active'] }}
</span>
