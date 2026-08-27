<details class="ui-card ui-disclosure" x-data="{ statusModalOpen: false }">
    <summary class="ui-disclosure-summary p-5">
        <span>
            <strong class="ui-title font-black">أدوات الدعم التقني</strong>
            <span class="ui-text-soft block mt-1">تظهر فقط خلال جلسة دعم بصفة المالك.</span>
        </span>
        <i class="fa-solid fa-chevron-down ui-text-soft ui-disclosure-chevron" aria-hidden="true"></i>
    </summary>
    <div class="border-t ui-border p-5 space-y-4">
        <div class="flex flex-wrap gap-2">
            <button type="button" class="ui-btn ui-btn-warning" @click="statusModalOpen = true">مراجعة الحالة</button>
            @if($order->workflow_status === 'approved_and_supplied')
            <form method="POST" action="{{ route('user.stores.purchase-orders.support-reverse', [$store->id, $order->id]) }}" class="flex flex-wrap items-end gap-2" data-ui-confirm="ستنشأ حركات مقابلة تعكس المخزون ومشتريات المالك مع إبقاء الطلبية وسجلها." data-ui-confirm-title="عكس اعتماد الطلبية؟">
                @csrf
                <input type="hidden" name="business_date" value="{{ $currentBusinessDate }}">
                <label class="ui-label">اكتب رمز الطلبية للعكس
                    <input name="confirmation" required class="ui-input" placeholder="{{ $order->referenceCode() }}">
                </label>
                <label class="ui-label">سبب العكس الإداري
                    <input name="support_note" required minlength="10" maxlength="500" class="ui-input" placeholder="سبب واضح لا يقل عن 10 أحرف">
                </label>
                <button class="ui-btn ui-btn-danger">عكس الاعتماد</button>
            </form>
            @elseif(!in_array($order->status, ['received', 'approved'], true))
            <form method="POST" action="{{ route('user.stores.purchase-orders.support-purge', [$store->id, $order->id]) }}" class="flex flex-wrap items-end gap-2" data-ui-confirm="ستحذف الطلبية وبنودها وأحداثها ومحاولات الجرد نهائيًا." data-ui-confirm-title="حذف الطلبية نهائيًا؟">
                @csrf @method('DELETE')
                <label class="ui-label">اكتب رمز الطلبية للحذف
                    <input name="confirmation" required class="ui-input" placeholder="{{ $order->referenceCode() }}">
                </label>
                <label class="ui-label">سبب الحذف
                    <input name="support_note" required minlength="10" maxlength="500" class="ui-input" placeholder="سبب واضح لا يقل عن 10 أحرف">
                </label>
                <button class="ui-btn ui-btn-danger">حذف نهائي</button>
            </form>
            @endif
        </div>
    </div>

    <div x-show="statusModalOpen" x-cloak class="ui-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="supportStatusTitle">
        <div class="ui-modal-panel" @click.outside="statusModalOpen = false">
            <div class="ui-modal-header">
                <h2 id="supportStatusTitle" class="ui-title font-black">حالة الطلبية</h2>
                <button type="button" class="ui-modal-close-text-danger" @click="statusModalOpen = false">إغلاق</button>
            </div>
            <form method="POST" action="{{ route('user.stores.purchase-orders.support-status', [$store->id, $order->id]) }}" class="p-6 space-y-4">
                @csrf @method('PATCH')
                <p class="ui-text-soft">الحالة الحالية: <strong class="ui-title">{{ $workflowLabels[$order->workflow_status] ?? \App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow::UNKNOWN_LABEL }}</strong></p>
                <label class="ui-label">الحالة الصحيحة
                    <select name="workflow_status" required class="ui-input">
                        @foreach($supportWorkflowLabels as $supportStatus => $supportStatusLabel)
                            <option value="{{ $supportStatus }}" @selected($order->workflow_status === $supportStatus)>{{ $supportStatusLabel }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ui-label">سبب تصحيح الحالة
                    <textarea name="support_note" required minlength="10" maxlength="500" rows="3" class="ui-input" placeholder="سبب واضح لا يقل عن 10 أحرف؛ يضاف إليه رقم التذكرة تلقائيًا"></textarea>
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="ui-btn ui-btn-secondary" @click="statusModalOpen = false">إلغاء</button>
                    <button class="ui-btn ui-btn-warning">حفظ الحالة</button>
                </div>
            </form>
        </div>
    </div>
</details>
