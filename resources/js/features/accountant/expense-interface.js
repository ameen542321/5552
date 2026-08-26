// استخراج واجهة فقط؛ تبقى النماذج والمسارات وشروط العمليات كما هي.
const root = document.querySelector('[data-expense-interface-config]');
if (root) {
    const config = JSON.parse(root.dataset.expenseInterfaceConfig || '{}');
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        document.getElementById('expenseModal')?.classList.add('hidden');
        document.getElementById('editExpenseModal')?.classList.add('hidden');
    });

    const expenseFlashMessage = config.flashMessage;
    const expenseFlashType = config.flashType;
    if (expenseFlashMessage) {
        if (window.Swal) {
            Swal.fire({
                icon: expenseFlashType || 'info',
                title: expenseFlashType === 'success' ? 'تمت العملية' : 'تنبيه',
                text: expenseFlashMessage,
                confirmButtonText: 'حسناً',
                confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--ui-brand').trim(),
                background: getComputedStyle(document.documentElement).getPropertyValue('--ui-surface').trim(),
                color: getComputedStyle(document.documentElement).getPropertyValue('--ui-text').trim(),
            });
        } else {
            window.alert(expenseFlashMessage);
        }
    }
}
