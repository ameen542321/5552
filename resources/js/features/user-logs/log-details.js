const createTextElement = (tagName, text, className = '') => {
    const element = document.createElement(tagName);
    element.textContent = String(text ?? '—');
    if (className) element.className = className;
    return element;
};

const parseDetails = (serializedDetails) => {
    if (!serializedDetails) return null;

    try {
        const parsedDetails = JSON.parse(serializedDetails);
        if (typeof parsedDetails !== 'string') return parsedDetails;
        return JSON.parse(parsedDetails);
    } catch {
        return null;
    }
};

const displayValue = (value) => {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
};

const createDetailsRow = (label, value) => {
    const row = document.createElement('div');
    row.className = 'grid grid-cols-1 gap-1 border-b ui-border py-3 text-right sm:grid-cols-[10rem_1fr] sm:gap-3';
    row.append(
        createTextElement('strong', label, 'ui-title'),
        createTextElement('span', displayValue(value), 'break-words ui-text-soft'),
    );
    return row;
};

const resolveStoreValue = (value, storeId, storeName) => (
    String(value ?? '') === String(storeId ?? '') && storeName ? storeName : value
);

const buildDetailsContent = (trigger) => {
    const content = document.createElement('div');
    content.className = 'max-h-[65vh] overflow-y-auto px-1 text-right';
    content.append(
        createDetailsRow('المستخدم', trigger.dataset.logUserName || '—'),
        createDetailsRow('المتجر', trigger.dataset.logStoreName || '—'),
    );

    const details = parseDetails(trigger.dataset.logDetails);
    if (!details || typeof details !== 'object') {
        content.append(createTextElement('p', 'لا توجد تفاصيل إضافية لهذه العملية.', 'py-4 text-center ui-text-muted'));
        return content;
    }

    const oldValues = details.old_values ?? {};
    const newValues = details.new_values ?? {};
    const changedKeys = Object.keys(newValues).filter((key) => oldValues[key] != newValues[key]);

    if (changedKeys.length) {
        content.append(createTextElement('h3', 'الحقول التي تم تعديلها', 'py-3 font-bold ui-title'));
        changedKeys.forEach((key) => {
            const label = key === 'store_id' ? 'نقل إلى متجر' : key;
            const oldValue = key === 'store_id'
                ? resolveStoreValue(oldValues[key], trigger.dataset.logStoreId, trigger.dataset.logStoreName)
                : oldValues[key];
            const newValue = key === 'store_id'
                ? resolveStoreValue(newValues[key], trigger.dataset.logStoreId, trigger.dataset.logStoreName)
                : newValues[key];
            content.append(createDetailsRow(label, `القديم: ${displayValue(oldValue)} — الجديد: ${displayValue(newValue)}`));
        });
    }

    Object.entries(details).forEach(([key, value]) => {
        if (key === 'old_values' || key === 'new_values') return;
        const label = key === 'store_id' ? 'المتجر الحالي' : (key === 'employee_id' ? 'الموظف' : key);
        const displayedValue = key === 'store_id'
            ? resolveStoreValue(value, trigger.dataset.logStoreId, trigger.dataset.logStoreName)
            : value;
        content.append(createDetailsRow(label, displayedValue));
    });

    return content;
};

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-log-details-trigger]');
    if (!trigger) return;

    const content = buildDetailsContent(trigger);
    if (window.Swal?.fire) {
        window.Swal.fire({
            title: 'تفاصيل العملية',
            html: content,
            confirmButtonText: 'إغلاق',
            width: 650,
        });
        return;
    }

    window.alert(content.textContent.trim() || 'لا توجد تفاصيل إضافية لهذه العملية.');
});
