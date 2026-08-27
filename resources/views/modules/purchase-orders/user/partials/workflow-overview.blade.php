{{-- ملخص تنقلي للعرض فقط؛ لا يغير حالات الطلبية أو صلاحيات الإجراءات. --}}
<section class="ui-card p-4 space-y-4" aria-labelledby="purchaseOrderProgressTitle">
    <div class="flex items-center gap-2">
        <h2 id="purchaseOrderProgressTitle" class="ui-title text-lg font-black">مراحل الطلبية</h2>
        <x-ui.help title="شريط المرحلة" body="يوضح موقع الطلبية في الدورة الأساسية. المرحلة الحالية مميزة، والمراحل السابقة مكتملة، أما الحالات التفصيلية مثل الإعادة للجرد أو التعديل فتظهر في شارة الحالة أعلى الصفحة." />
    </div>
    <ol class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        @foreach($stageSteps as $stageIndex => $stage)
            <li>
                <a href="#{{ $stage['anchor'] }}" class="ui-card-muted p-3 flex items-center gap-2">
                    <span class="ui-badge {{ $stageIndex < $currentStageIndex ? 'ui-badge-success' : ($stageIndex === $currentStageIndex ? 'ui-badge-info' : 'ui-badge-neutral') }}">{{ $stageIndex + 1 }}</span>
                    <span class="{{ $stageIndex === $currentStageIndex ? 'ui-title font-black' : 'ui-text-soft' }}">{{ $stage['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ol>
    <nav class="flex flex-wrap gap-2" aria-label="أقسام الطلبية">
        <a href="#order-overview" class="ui-btn ui-btn-secondary">البيانات</a>
        <a href="#order-actions" class="ui-btn ui-btn-secondary">الإجراءات</a>
        @if(in_array($order->status, ['approved', 'cancelled'], true))<a href="#order-items" class="ui-btn ui-btn-secondary">البنود</a>@endif
        @if($order->status === 'sent' || $isOwnerReceiptReview)<a href="#receipt-review" class="ui-btn ui-btn-secondary">الاستلام</a>@endif
        @if($isInventoryApproval)<a href="#inventory-approval" class="ui-btn ui-btn-secondary">الاعتماد</a>@endif
    </nav>
</section>

<section class="ui-card p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="purchaseOrderTaskTitle">
    <div>
        <span class="ui-badge ui-badge-warning">مهمتك الآن</span>
        <h2 id="purchaseOrderTaskTitle" class="ui-title text-lg font-black mt-2">{{ $taskTitle }}</h2>
        <p class="ui-text-soft mt-1">{{ $taskBody }}</p>
    </div>
    <a href="#{{ $taskAnchor }}" class="ui-btn ui-btn-primary">انتقل إلى المهمة</a>
</section>
