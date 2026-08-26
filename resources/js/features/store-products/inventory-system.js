const inventoryRootSelector = '[data-inventory-system]';

const disableInventoryFormButtons = (form) => {
    form.querySelectorAll('button[type="submit"]').forEach((button) => {
        button.disabled = true;
        button.classList.add('opacity-70', 'cursor-not-allowed');
        button.textContent = 'جاري التنفيذ...';
    });
};

const showInventoryMessage = (messageTitle, messageText, messageIcon = 'info') => {
    if (!window.Swal) {
        window.alert(`${messageTitle}\n${messageText}`);
        return;
    }

    window.Swal.fire({
        title: messageTitle,
        text: messageText,
        icon: messageIcon,
        confirmButtonText: 'حسنًا',
    });
};

const confirmInventoryForm = async (form) => {
    const confirmationTitle = form.dataset.confirmTitle || 'تأكيد العملية';
    const confirmationText = form.dataset.confirmText || 'هل تريد المتابعة؟';

    if (!window.Swal) return window.confirm(`${confirmationTitle}\n${confirmationText}`);
    const result = await window.Swal.fire({
        title: confirmationTitle,
        text: confirmationText,
        icon: form.dataset.confirmIcon || 'warning',
        showCancelButton: true,
        confirmButtonText: 'نعم، متابعة',
        cancelButtonText: 'إلغاء',
        reverseButtons: true,
    });
    return result.isConfirmed;
};

const normalizedRequestedQuantity = (form) => {
    const quantity = Number(form.querySelector('input[name="quantity"]')?.value || 0);
    const unitType = form.querySelector('select[name="unit_type"]')?.value || 'unit';
    const productType = form.dataset.productType || 'standard';
    const itemsPerUnit = Number(form.dataset.itemsPerUnit || 1);
    const rollLength = Number(form.dataset.rollLength || 0);

    if (productType === 'fractional' && unitType === 'roll' && rollLength > 0) {
        return quantity * rollLength;
    }
    if (form.dataset.isSplittable === '1' && unitType === 'piece' && itemsPerUnit > 1) {
        return quantity / itemsPerUnit;
    }

    return quantity;
};

document.addEventListener('DOMContentLoaded', () => {
    const inventoryRoot = document.querySelector(inventoryRootSelector);
    if (!inventoryRoot) return;

    inventoryRoot.querySelectorAll('input[type="number"]').forEach((input) => {
        input.addEventListener('wheel', (event) => event.preventDefault(), { passive: false });
    });

    inventoryRoot.querySelectorAll('form[data-disable-on-submit]').forEach((form) => {
        form.addEventListener('submit', () => disableInventoryFormButtons(form));
    });

    inventoryRoot.querySelectorAll('form[data-confirm-submit]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            if (form.dataset.confirmed === 'true') {
                disableInventoryFormButtons(form);
                return;
            }

            event.preventDefault();
            const currentStock = Number(form.dataset.currentStock || 0);
            if (form.hasAttribute('data-validate-available-stock') && currentStock <= 0) {
                showInventoryMessage(
                    form.dataset.emptyTitle || 'لا يمكن تنفيذ العملية',
                    form.dataset.emptyText || 'لا توجد كمية متاحة.',
                    'error',
                );
                return;
            }
            if (form.hasAttribute('data-validate-available-stock')
                && normalizedRequestedQuantity(form) > currentStock + 0.000001) {
                showInventoryMessage(
                    form.dataset.insufficientTitle || 'الكمية غير متوفرة',
                    form.dataset.insufficientText || 'الكمية المطلوبة أكبر من الرصيد المتاح.',
                    'error',
                );
                return;
            }

            if (!await confirmInventoryForm(form)) return;
            form.dataset.confirmed = 'true';
            disableInventoryFormButtons(form);
            form.submit();
        });
    });
});
