// هذه الخريطة تربط أسماء العقود فقط بالدوال الحالية؛ الحسابات المالية والمخزنية تبقى دون تغيير.
const actionHandlers = {
    'collection.open': (element) => window.openCollectionModal?.(element.dataset.employeeId, element.dataset.employeeName),
    'collection.close': () => window.closeCollectionModal?.(),
    'collection.preview': (element) => window.openPreviewModal?.(JSON.parse(element.dataset.sale || '{}')),
    'collection.preview-close': () => window.closePreviewModal?.(),
    'collection.partial-open': (element) => window.openPartialModal?.(element.dataset.saleId, Number(element.dataset.amount)),
    'collection.partial-close': () => window.closePartialModal?.(),
    'debt.open': (element) => window.openDebtModal?.(element.dataset.employeeId, element.dataset.employeeName, element.dataset.hasDebt === 'true'),
    'debt.close': () => window.closeDebtModal?.(),
    'debt.collect-open': () => window.openCollectModal?.(),
    'debt.collect-close': () => window.closeCollectModal?.(),
    'debt.full': (element) => window.collectFull?.(element.dataset.debtId, Number(element.dataset.amount), element),
    'debt.partial': (element) => window.collectPartial?.(element.dataset.debtId, Number(element.dataset.amount), element),
    'daily.financial-toggle': (element) => window.toggleFinancialDetails?.(element.dataset.rowId),
    'daily.sale-toggle': (element) => window.toggleDetails?.(element.dataset.saleId),
    'daily.financial-edit': (element) => window.openFinancialEditModal?.(JSON.parse(element.dataset.operation || '{}')),
    'daily.financial-close': () => window.closeFinancialEditModal?.(),
    'daily.sale-edit': (element) => window.openEditSaleModal?.(element.dataset.saleId),
    'daily.sale-close': () => window.closeEditSaleModal?.(),
    'daily.product-add': () => window.addEditSaleProductRow?.(),
    'daily.product-choose': (element) => window.chooseEditProduct?.(element),
    'daily.item-remove': (element) => window.removeEditSaleItemRow?.(element),
    'daily.quantity-adjust': (element) => window.adjustEditSaleQuantity?.(element, Number(element.dataset.direction)),
};

document.addEventListener('click', (event) => {
    const element = event.target.closest('[data-sensitive-action]');
    if (!element) return;

    // يمنع النقر داخل لوحة المودال من الوصول إلى عقد إغلاق الخلفية.
    const propagationBoundary = event.target.closest('[data-stop-propagation="true"]');
    if (propagationBoundary && propagationBoundary !== element && element.contains(propagationBoundary)) return;

    const action = element.dataset.sensitiveAction;
    const handler = actionHandlers[action];
    if (!handler) return;

    if (element.dataset.stopPropagation === 'true') event.stopPropagation();
    handler(element, event);
});

// تفصل عقود الإدخال بين إعادة حساب الحقول وبين مزامنة العرض حتى نحافظ على السلوك السابق لكل حقل.
const inputHandlers = {
    'daily.sale-fields-sync': () => window.updateEditSaleFields?.(),
    'daily.sale-fields': () => window.updateEditSaleFields?.(false),
    'daily.payment-touch': () => window.markEditSalePaymentTouched?.(),
    'daily.mixed-payment': () => window.syncMixedPaidAmountFromSplit?.(),
    'daily.labor-total': () => window.syncEditedOperationTotal?.(),
    'daily.quantity-limit': (element) => {
        window.enforceEditQuantityLimit?.(element);
        window.syncEditedOperationTotal?.();
    },
    'daily.product-search': (element) => window.renderEditProductSearchResults?.(element),
    'daily.operation-total': () => window.syncEditedOperationTotal?.(),
    'daily.unit-change': (element) => window.handleEditUnitTypeChange?.(element),
};

['input', 'change', 'focusin'].forEach((eventName) => {
    document.addEventListener(eventName, (event) => {
        const element = event.target.closest('[data-sensitive-input]');
        if (!element) return;
        const action = element.dataset.sensitiveInput;
        // البحث يستجيب للتركيز والإدخال، بينما القوائم والاختيارات تستجيب للتغيير فقط.
        const acceptsEvent = action === 'daily.product-search'
            ? event.type === 'input' || event.type === 'focusin'
            : action === 'daily.sale-fields-sync' || (action === 'daily.sale-fields' && element.type === 'checkbox') || action === 'daily.unit-change'
                ? event.type === 'change'
                : event.type === 'input';
        if (acceptsEvent) inputHandlers[action]?.(element, event);
    });
});
