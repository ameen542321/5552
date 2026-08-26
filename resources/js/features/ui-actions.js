const findTarget = (id) => id ? document.getElementById(id) : null;

// إصلاح مطبق: إجراءات العرض والإخفاء والطباعة ومزامنة الحقول أصبحت عقودًا مشتركة بدل handlers داخل Blade.
document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-ui-show], [data-ui-hide], [data-ui-print], [data-ui-edit-form], [data-ui-toggle], [data-ui-set-value]');
    if (!trigger) return;

    if (trigger.hasAttribute('data-ui-print')) {
        window.print();
        return;
    }

    const editForm = findTarget(trigger.dataset.uiEditForm);
    if (editForm) {
        const actionTemplate = editForm.dataset.uiActionTemplate;
        if (actionTemplate) editForm.action = actionTemplate.replace('__ID__', trigger.dataset.id || '');
        editForm.querySelectorAll('[data-ui-fill]').forEach((field) => {
            field.value = trigger.dataset[field.dataset.uiFill] || '';
        });
    }

    const valueTarget = findTarget(trigger.dataset.uiSetValue);
    if (valueTarget) valueTarget.value = trigger.dataset.uiValue || '';

    const toggleTarget = findTarget(trigger.dataset.uiToggle);
    if (toggleTarget) {
        toggleTarget.classList.toggle('hidden');
        const classTarget = findTarget(trigger.dataset.uiToggleClassTarget);
        if (classTarget && trigger.dataset.uiToggleClass) {
            classTarget.classList.toggle(trigger.dataset.uiToggleClass);
        }
    }

    const showTarget = findTarget(trigger.dataset.uiShow);
    if (showTarget) {
        if (trigger.hasAttribute('data-ui-reset-details')) {
            showTarget.querySelectorAll('details[open]').forEach((detailsElement) => {
                detailsElement.removeAttribute('open');
            });
        }
        showTarget.querySelectorAll('[data-ui-fill-text]').forEach((element) => {
            const value = trigger.dataset[element.dataset.uiFillText] || '';
            element.textContent = `${element.dataset.uiPrefix || ''}${value}`;
        });
        showTarget.classList.remove('hidden');
        if (trigger.hasAttribute('data-ui-scroll-lock')) document.body.classList.add('ui-scroll-lock');
    }

    const hideTarget = findTarget(trigger.dataset.uiHide);
    if (hideTarget) {
        hideTarget.classList.add('hidden');
        if (trigger.hasAttribute('data-ui-scroll-unlock')) document.body.classList.remove('ui-scroll-lock');
        findTarget(trigger.dataset.uiResetForm)?.reset();
    }

    findTarget(trigger.dataset.uiSubmitForm)?.requestSubmit();
});

document.addEventListener('change', (event) => {
    const source = event.target.closest('[data-ui-sync-value]');
    const target = findTarget(source?.dataset.uiSyncValue);
    if (target) target.value = source.value;
    if (event.target.matches('[data-ui-submit-on-change]')) event.target.form?.requestSubmit();
});

document.addEventListener('input', (event) => {
    const input = event.target.closest('[data-ui-filter-input]');
    const container = findTarget(input?.dataset.uiFilterInput);
    if (!container) return;

    const term = input.value.toLocaleLowerCase();
    container.querySelectorAll('[data-ui-filter-value]').forEach((item) => {
        item.classList.toggle('hidden', !item.dataset.uiFilterValue.toLocaleLowerCase().includes(term));
    });
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-ui-single-submit]');
    if (!form) return;
    const button = event.submitter || form.querySelector('button[type="submit"]');
    if (!button) return;
    button.disabled = true;
    button.textContent = form.dataset.uiBusyText || 'جاري الحفظ...';
});
