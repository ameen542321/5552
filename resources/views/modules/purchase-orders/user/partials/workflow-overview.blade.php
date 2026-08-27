{{-- ملخص تنقلي للعرض فقط؛ لا يغير حالات الطلبية أو صلاحيات الإجراءات. --}}
<section class="ui-card p-4 space-y-4" aria-labelledby="purchaseOrderProgressTitle">
    <div class="flex items-center gap-2">
        <h2 id="purchaseOrderProgressTitle" class="ui-title text-lg font-black">مراحل الطلبية</h2>
        <x-ui.help title="شريط المرحلة" body="يوضح موقع الطلبية في الدورة الأساسية. المرحلة الحالية مميزة، والمراحل السابقة مكتملة، أما الحالات التفصيلية مثل الإعادة للجرد أو التعديل فتظهر في شارة الحالة أعلى الصفحة." />
    </div>
    <ol class="ui-workflow-steps grid grid-cols-2 gap-2 sm:grid-cols-4" aria-label="تقدم الطلبية">
        @foreach($stageSteps as $stageIndex => $stage)
            <li>
                <a href="#{{ $stage['anchor'] }}"
                   class="ui-workflow-step {{ $stageIndex < $currentStageIndex ? 'ui-workflow-step-complete' : ($stageIndex === $currentStageIndex ? 'ui-workflow-step-current' : 'ui-workflow-step-upcoming') }}"
                   @if($stageIndex === $currentStageIndex) aria-current="step" @endif>
                    <span class="ui-badge {{ $stageIndex < $currentStageIndex ? 'ui-badge-success' : ($stageIndex === $currentStageIndex ? 'ui-badge-info' : 'ui-badge-neutral') }}">{{ $stageIndex + 1 }}</span>
                    <span>
                        <strong class="block {{ $stageIndex === $currentStageIndex ? 'ui-title' : 'ui-text-soft' }}">{{ $stage['label'] }}</strong>
                        <small class="ui-text-muted">{{ $stageIndex < $currentStageIndex ? 'مكتملة' : ($stageIndex === $currentStageIndex ? 'المرحلة الحالية' : 'قادمة') }}</small>
                    </span>
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
        <div class="flex flex-wrap items-center gap-2">
            <span class="ui-badge ui-badge-warning">مهمتك الآن</span>
            <span class="ui-badge ui-badge-neutral">{{ $isAccountantContext ? 'مهام المحاسب' : 'مهام المالك' }}</span>
        </div>
        <h2 id="purchaseOrderTaskTitle" class="ui-title text-lg font-black mt-2">{{ $taskTitle }}</h2>
        <p class="ui-text-soft mt-1">{{ $taskBody }}</p>
    </div>
    @if($hasCurrentTask)
        <a href="#{{ $taskAnchor }}" class="ui-btn ui-btn-primary">انتقل إلى المهمة</a>
    @endif
</section>
