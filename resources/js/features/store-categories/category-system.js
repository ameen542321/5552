const categorySystemRootSelector = '[data-category-system]';

const initializeCategoryPresets = (categoryRoot) => {
    const nameInput = categoryRoot.querySelector('#category_name');
    const presetInput = categoryRoot.querySelector('#category_name_preset');
    const notice = categoryRoot.querySelector('#category_preset_notice');
    const presetButtons = categoryRoot.querySelectorAll('[data-category-preset]');
    if (!nameInput || !presetInput || !notice || presetButtons.length === 0) return;

    const refreshPresetState = () => {
        if (presetInput.value) {
            notice.textContent = `سيُحفظ اسم القسم بالصيغة المعتمدة: ${nameInput.value}`;
            notice.classList.remove('hidden');
        } else {
            notice.classList.add('hidden');
        }
    };

    presetButtons.forEach((presetButton) => presetButton.addEventListener('click', () => {
        nameInput.value = presetButton.dataset.categoryName;
        presetInput.value = presetButton.dataset.categoryPreset;
        refreshPresetState();
        nameInput.focus();
    }));
    nameInput.addEventListener('input', () => {
        presetInput.value = '';
        refreshPresetState();
    });
    refreshPresetState();
};

const confirmCategoryTransfer = async (form, confirmationText) => {
    if (!window.Swal) return window.confirm(confirmationText);
    const result = await window.Swal.fire({
        title: `تأكيد نقل ${form.dataset.itemLabel || 'القسم'}`,
        text: confirmationText,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'نعم، نقل وحفظ',
        cancelButtonText: 'إلغاء',
        reverseButtons: true,
        focusCancel: true,
    });
    return result.isConfirmed;
};

const initializeCategoryTransfer = (categoryRoot) => {
    const form = categoryRoot.querySelector('#category_edit_form');
    const targetStoreSelect = categoryRoot.querySelector('#target_store_id');
    if (!form || !targetStoreSelect) return;

    form.addEventListener('submit', async (event) => {
        if (form.dataset.transferConfirmed === 'true' || !targetStoreSelect.value) return;
        event.preventDefault();
        const targetStoreName = targetStoreSelect.selectedOptions[0]?.text.trim();
        if (!targetStoreName) return;

        const itemLabel = form.dataset.itemLabel || 'القسم';
        const currentStore = form.dataset.currentStore || 'المتجر الحالي';
        const confirmationText = `سيتم نقل ${itemLabel} من ${currentStore} إلى ${targetStoreName}. هل أنت متأكد؟`;
        if (!await confirmCategoryTransfer(form, confirmationText)) return;

        form.dataset.transferConfirmed = 'true';
        form.submit();
    });
};

document.addEventListener('DOMContentLoaded', () => {
    const categoryRoot = document.querySelector(categorySystemRootSelector);
    if (!categoryRoot) return;
    initializeCategoryPresets(categoryRoot);
    initializeCategoryTransfer(categoryRoot);
});
