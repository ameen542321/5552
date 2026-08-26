document.querySelectorAll('[data-admin-notification-recipients]').forEach((root) => {
    // إصلاح مطبق: منتقي مستلمي الإشعار مشترك وآمن، مع إبقاء عقد target_ids الحالي دون تغيير.
    const selected = new Map();
    const items = [...root.querySelectorAll('[data-recipient-item]')];
    const selectedContainer = root.querySelector('[data-selected-recipients]');
    const selectedCount = root.querySelector('[data-selected-count]');
    const hiddenInput = root.querySelector('[data-target-ids]');
    const list = root.querySelector('[data-recipient-list]');
    const targetType = root.querySelector('[data-target-type]');

    if (!selectedContainer || !selectedCount || !hiddenInput || !list || !targetType) return;

    const renderSelected = () => {
        selectedContainer.replaceChildren();
        selected.forEach((recipient) => {
            const tag = document.createElement('div');
            tag.className = 'flex items-center gap-2 ui-status-info-bg ui-status-info px-3 py-1 rounded-full';

            const name = document.createElement('span');
            name.textContent = recipient.name;
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'ui-title ui-hover-info';
            removeButton.dataset.removeRecipient = recipient.id;
            removeButton.setAttribute('aria-label', `إزالة ${recipient.name}`);
            removeButton.textContent = '×';

            tag.append(name, removeButton);
            selectedContainer.appendChild(tag);
        });
        hiddenInput.value = JSON.stringify([...selected.keys()]);
        selectedCount.textContent = `${selected.size} مختار`;
    };

    const addItem = (item) => {
        const { id, name, type } = item.dataset;
        if (!selected.has(id)) selected.set(id, { id, name, type });
    };

    root.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-recipient]');
        if (removeButton) {
            selected.delete(removeButton.dataset.removeRecipient);
            renderSelected();
            return;
        }

        const item = event.target.closest('[data-recipient-item]');
        if (item) {
            addItem(item);
            renderSelected();
        }
    });

    root.querySelector('[data-select-all]')?.addEventListener('click', () => {
        items.filter((item) => !item.classList.contains('hidden')).forEach(addItem);
        renderSelected();
    });
    root.querySelector('[data-clear-all]')?.addEventListener('click', () => {
        selected.clear();
        renderSelected();
    });
    root.querySelector('[data-filter-type]')?.addEventListener('change', (event) => {
        const type = event.target.value;
        items.forEach((item) => item.classList.toggle('hidden', type !== 'all' && item.dataset.type !== type));
    });
    targetType.addEventListener('change', () => {
        const type = targetType.value;
        selected.clear();
        renderSelected();
        list.classList.toggle('hidden', type === 'all');
        if (type === 'all') return;
        items.forEach((item) => {
            const isVisible = (type === 'users' && item.dataset.type === 'user')
                || (type === 'accountants' && item.dataset.type === 'accountant');
            item.classList.toggle('hidden', !isVisible);
        });
    });
    root.querySelector('[data-recipient-search]')?.addEventListener('input', (event) => {
        const term = event.target.value.toLocaleLowerCase();
        items.forEach((item) => item.classList.toggle('hidden', !item.dataset.name.toLocaleLowerCase().includes(term)));
    });
});
