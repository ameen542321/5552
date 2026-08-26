const productFormRootSelector = '[data-store-product-form]';
let tintNameFieldsInitialized = false;

const productFormRootExists = () => document.querySelector(productFormRootSelector) !== null;

function parseTintProductName(value) {
    const tokens = String(value || '').trim().replace(/\s+/g, ' ').split(' ').filter(Boolean);
    if (tokens.length < 3) return { manufacturer: tokens[0] || '', size: '', grade: '', extra: '' };

    const knownSizeIndex = tokens.findIndex((token) => ['كبير', 'صغير'].includes(token));
    const knownGradeIndex = tokens.findIndex((token) => token === 'شفاف' || /^0?[1-3]$/.test(token));
    const sizeIndex = knownSizeIndex >= 0 && knownGradeIndex >= 0 ? knownSizeIndex : 1;
    const gradeIndex = knownSizeIndex >= 0 && knownGradeIndex >= 0 ? knownGradeIndex : 2;
    const remaining = tokens.filter((token, index) => index !== sizeIndex && index !== gradeIndex);

    return {
        manufacturer: remaining.shift() || '',
        size: tokens[sizeIndex] || '',
        grade: tokens[gradeIndex] || '',
        extra: remaining.join(' '),
    };
}

function initializeTintNameFields() {
    if (tintNameFieldsInitialized) return;
    const manufacturer = document.getElementById('tint_manufacturer');
    const size = document.getElementById('tint_size');
    const grade = document.getElementById('tint_grade');
    const extra = document.getElementById('tint_extra');
    if (!manufacturer || !size || !grade || !extra) return;

    if (!manufacturer.value && !size.value && !grade.value && !extra.value) {
        const parsedName = parseTintProductName(document.getElementById('product_name').value);
        manufacturer.value = parsedName.manufacturer;
        size.value = parsedName.size;
        grade.value = parsedName.grade;
        extra.value = parsedName.extra;
    }
    tintNameFieldsInitialized = true;
}

function updateTintNamePreview() {
    const input = document.getElementById('product_name');
    const preview = document.getElementById('tint_name_preview');
    const hint = document.getElementById('tint_name_readonly_hint');
    if (!input || !preview) return;

    const active = !preview.classList.contains('hidden');
    ['tint_manufacturer', 'tint_size', 'tint_grade'].forEach((fieldId) => {
        const field = document.getElementById(fieldId);
        if (field) field.required = active;
    });
    input.readOnly = active;
    input.classList.toggle('cursor-not-allowed', active);
    input.classList.toggle('ui-text-muted', active);
    hint?.classList.toggle('hidden', !active);
    if (!active) return;

    initializeTintNameFields();
    const nameParts = [
        document.getElementById('tint_manufacturer').value.trim(),
        document.getElementById('tint_size').value,
        document.getElementById('tint_grade').value,
        document.getElementById('tint_extra').value.trim(),
    ].filter(Boolean);
    const normalizedName = nameParts.join(' ').replace(/\s+/g, ' ').trim();
    input.value = normalizedName;
    document.getElementById('tint_normalized_name').textContent = normalizedName || 'أكمل بيانات الظهور';
}

function updateFractionalGuidance() {
    const productType = document.getElementById('product_type').value;
    const categorySelect = document.getElementById('category_id');
    const categoryName = categorySelect?.selectedOptions[0]?.dataset.categoryName?.trim() || '';
    const isFractional = productType === 'fractional';
    const isTint = categoryName === 'تضليل';
    const isUpholstery = categoryName === 'تنجيد وتلابيس';

    document.getElementById('tint_product_guidance').classList.toggle('hidden', !isFractional || !isTint);
    document.getElementById('upholstery_product_guidance').classList.toggle('hidden', !isFractional || !isUpholstery);
    document.getElementById('general_roll_guidance').classList.toggle('hidden', !isFractional || isTint || isUpholstery);

    const title = document.getElementById('fractional_guidance_title');
    if (title) {
        title.textContent = isTint
            ? 'دليل إدخال رول التضليل'
            : (isUpholstery ? 'دليل إدخال رول التنجيد والتلابيس' : 'دليل إدخال منتج رول/قص');
    }

    document.getElementById('tint_name_preview').classList.toggle('hidden', !isFractional || !isTint);
    updateTintNamePreview();
}

function disableNumberWheelInputs() {
    document.querySelectorAll(`${productFormRootSelector} input[type="number"]`).forEach((input) => {
        input.addEventListener('wheel', (event) => event.preventDefault(), { passive: false });
    });
}

function updateSplittableFieldsVisibility() {
    const splittableCheckbox = document.getElementById('is_splittable');
    const splittableFields = document.getElementById('splittable_fields');
    if (!splittableCheckbox || !splittableFields) return;

    const isStandardProduct = document.getElementById('product_type')?.value !== 'fractional';
    splittableFields.classList.toggle('hidden', !isStandardProduct || !splittableCheckbox.checked);
}

function showProductFormValidationErrors() {
    const errorsPayload = document.querySelector('[data-product-form-errors]');
    if (!errorsPayload) return;

    const validationMessages = JSON.parse(errorsPayload.dataset.productFormErrors || errorsPayload.textContent || '[]');
    if (!Array.isArray(validationMessages) || validationMessages.length === 0) return;

    if (!window.Swal) {
        window.alert(validationMessages.join('\n'));
        return;
    }

    const messagesContainer = document.createElement('div');
    messagesContainer.className = 'text-right leading-7';
    validationMessages.forEach((validationMessage) => {
        const messageRow = document.createElement('div');
        messageRow.textContent = `• ${validationMessage}`;
        messagesContainer.appendChild(messageRow);
    });

    window.Swal.fire({
        title: 'بيانات غير مكتملة',
        html: messagesContainer,
        icon: 'error',
        confirmButtonText: 'حسناً',
        background: '',
        color: '',
        confirmButtonColor: '',
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (!productFormRootExists()) return;

    const productFormRoot = document.querySelector(productFormRootSelector);
    productFormRoot.addEventListener('click', (event) => {
        const removeFractionButton = event.target.closest('[data-product-remove-fraction]');
        if (removeFractionButton) removeFractionButton.closest('.ui-product-fraction-row')?.remove();
    });

    document.getElementById('category_id')?.addEventListener('change', updateFractionalGuidance);
    document.getElementById('product_type')?.addEventListener('change', () => {
        updateFractionalGuidance();
        updateSplittableFieldsVisibility();
    });
    document.getElementById('is_splittable')?.addEventListener('change', updateSplittableFieldsVisibility);
    ['tint_manufacturer', 'tint_size', 'tint_grade', 'tint_extra'].forEach((fieldId) => {
        const field = document.getElementById(fieldId);
        field?.addEventListener('input', updateTintNamePreview);
        field?.addEventListener('change', updateTintNamePreview);
    });
    updateFractionalGuidance();
    updateSplittableFieldsVisibility();
    showProductFormValidationErrors();
    disableNumberWheelInputs();
});
