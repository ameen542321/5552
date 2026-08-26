// تحديد إشعارات المركز محليًا فقط؛ الإرسال والقراءة والحذف يبقيان عبر النماذج والمسارات الحالية.
document.addEventListener('change', (event) => {
    if (!event.target.matches('[data-notification-select-all]')) return;

    document.querySelectorAll('[data-notification-item-checkbox]')
        .forEach((checkbox) => { checkbox.checked = event.target.checked; });
});

// يسجل المصنع قبل Alpine.start حتى يبقى x-data في Blade عقد إعداد فقط.
window.notificationComposer = function notificationComposer(initialMode, initialIds) {
    return {
        mode: initialMode || 'accountants',
        selected: Array.isArray(initialIds) ? initialIds.map(Number) : [],
        setMode(next) {
            this.mode = next;
            if (next === 'admin') this.selected = [];
            if (next === 'accountant' && this.selected.length > 1) this.selected = [this.selected[0]];
        },
        toggle(id) {
            const index = this.selected.indexOf(id);

            if (this.mode === 'accountant') {
                this.selected = index === -1 ? [id] : [];
                return;
            }

            if (index === -1) this.selected.push(id);
            else this.selected.splice(index, 1);
        },
    };
};
