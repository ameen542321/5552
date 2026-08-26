@props([
    'alerts' => collect(),
    'context' => 'owner',
])

@php
    $alerts = collect($alerts);
    $statusLabels = [
        'pending_owner_review' => 'طلبية جديدة للمراجعة',
        'returned_after_edit' => 'معادة بعد التعديل',
        'returned_after_count' => 'معادة بعد الجرد',
        'pending_inventory_approval' => 'بانتظار الاعتماد المخزني',
        'pending_owner_receipt_review' => 'تأكيد الاستلام بحاجة للمراجعة',
        'returned_for_edit' => 'معادة للتعديل',
        'returned_for_count' => 'معادة للجرد',
        'pending_receipt_confirmation' => 'بانتظار تأكيد الاستلام',
    ];
    $statusBadgeClasses = [
        'pending_owner_review' => 'ui-badge-info',
        'returned_after_edit' => 'ui-badge-info',
        'returned_after_count' => 'ui-badge-info',
        'pending_inventory_approval' => 'ui-badge-warning',
        'pending_owner_receipt_review' => 'ui-badge-info',
        'returned_for_edit' => 'ui-badge-warning',
        'returned_for_count' => 'ui-badge-warning',
        'pending_receipt_confirmation' => 'ui-badge-success',
    ];
@endphp

@if($alerts->isNotEmpty())
    <div x-data="{ purchaseOrderAlertsOpen: false }" class="inline-flex">
        <button type="button"
                @click="purchaseOrderAlertsOpen = true"
                class="ui-topbar-action group gap-2"
                aria-label="فتح تنبيهات طلبيات التوريد">
            <i class="fa-solid fa-cart-flatbed text-xl" aria-hidden="true"></i>
            <span class="ui-badge ui-badge-info">{{ $alerts->count() }}</span>
            <span class="ui-tooltip-popover">تنبيهات طلبيات التوريد</span>
        </button>

        <div x-show="purchaseOrderAlertsOpen"
             x-cloak
             x-transition.opacity
             class="ui-modal-backdrop"
             role="dialog"
             aria-modal="true"
             aria-label="تنبيهات طلبيات التوريد">
            <div class="ui-modal-panel w-full max-w-xl" @click.outside="purchaseOrderAlertsOpen = false">
                <div class="ui-modal-header">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-cart-flatbed ui-status-info" aria-hidden="true"></i>
                        <h2 class="ui-title font-bold">تنبيهات طلبيات التوريد</h2>
                        <x-ui.help title="تنبيهات الطلبيات" body="تعرض هذه النافذة الطلبيات التي تنتظر منك إجراءً. اضغط على الطلبية لفتحها وإكمال الخطوة المطلوبة." />
                        <span class="ui-badge ui-badge-info">{{ $alerts->count() }}</span>
                    </div>
                    <button type="button" @click="purchaseOrderAlertsOpen = false" class="ui-modal-close-danger" aria-label="إغلاق">×</button>
                </div>

                <div class="max-h-[65vh] overflow-y-auto p-4 space-y-3">
                    @foreach($alerts as $pendingOrder)
                        @php
                            $returnNumber = max(1, (int) ($pendingOrder->edit_return_count ?? 0));
                            $countReturnNumber = max(1, (int) $pendingOrder->items->max('inventory_count_attempt'));
                            $orderUrl = $context === 'accountant'
                                ? match ($pendingOrder->workflow_status) {
                                    'returned_for_edit' => route('accountant.purchase-orders.edit', $pendingOrder->id),
                                    'returned_for_count' => route('accountant.purchase-orders.inventory-count', $pendingOrder->id),
                                    'pending_receipt_confirmation' => route('accountant.purchase-orders.show', $pendingOrder->id).'#receipt-confirmation',
                                    default => route('accountant.purchase-orders.show', $pendingOrder->id),
                                }
                                : route('user.stores.purchase-orders.show', [$pendingOrder->store_id, $pendingOrder->id])
                                    .($pendingOrder->workflow_status === 'pending_owner_receipt_review' ? '#receipt-review' : ($pendingOrder->workflow_status === 'pending_inventory_approval' ? '#inventory-approval' : ''));
                            $alertLabel = match ($pendingOrder->workflow_status) {
                                'returned_for_edit' => 'أعاد المالك الطلبية للتعديل — الإعادة '.$returnNumber,
                                'returned_for_count' => 'أعاد المالك منتجات للجرد — الجولة '.$countReturnNumber,
                                'pending_receipt_confirmation' => 'اعتمد المالك الطلبية؛ أكد استلام المورد',
                                'pending_owner_review' => 'طلبية جديدة بحاجة للمراجعة',
                                'returned_after_edit' => 'أعاد المحاسب الطلبية بعد تنفيذ التعديل — الإعادة '.$returnNumber,
                                'returned_after_count' => 'أعاد المحاسب الطلبية بعد تنفيذ الجرد — الجولة '.$countReturnNumber,
                                'pending_inventory_approval' => 'تم تأكيد الاستلام؛ جاهزة للاعتماد المخزني',
                                'pending_owner_receipt_review' => 'أكد المحاسب الاستلام؛ راجع البيانات',
                                default => $statusLabels[$pendingOrder->workflow_status] ?? 'تحتاج مراجعة',
                            };
                        @endphp
                        <a href="{{ $orderUrl }}" class="ui-card-muted p-3 flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <span class="min-w-0 flex-1">
                                <strong class="block ui-title break-words">{{ $pendingOrder->displayName() }}</strong>
                                @if($context === 'owner')
                                    <span class="block ui-text-soft text-sm break-words">{{ $pendingOrder->accountant?->name ?: $pendingOrder->store?->name }}</span>
                                @endif
                                @if($context === 'accountant' && $pendingOrder->workflow_status === 'pending_receipt_confirmation')
                                    <span class="block ui-text-soft text-sm mt-1">سجّل ما وصل من المورد: الكمية، وحدة الاستلام، والتكلفة عند اختلافها.</span>
                                @endif
                                @if($context === 'owner' && $pendingOrder->workflow_status === 'pending_owner_receipt_review')
                                    <span class="block ui-text-soft text-sm mt-1">افتح صفحة مراجعة تأكيد الاستلام.</span>
                                @endif
                            </span>
                            <span class="ui-badge self-start sm:shrink-0 {{ $statusBadgeClasses[$pendingOrder->workflow_status] ?? 'ui-badge-neutral' }}">{{ $alertLabel }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
