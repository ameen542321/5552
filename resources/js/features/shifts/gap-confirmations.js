// استخراج واجهة فقط؛ تبقى النماذج والمسارات وشروط العمليات كما هي.
if (document.querySelector('[data-shift-gap-interface]')) {
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.js-shift-gap-confirm').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                if (form.dataset.confirmed === '1') {
                    return;
                }

                event.preventDefault();

                const title = form.dataset.confirmTitle || 'تأكيد الإجراء';
                const text = form.dataset.confirmText || 'هل تريد المتابعة؟';
                const icon = form.dataset.confirmIcon || 'question';

                if (typeof Swal === 'undefined') {
                    if (window.confirm(`${title}\n\n${text}`)) {
                        form.dataset.confirmed = '1';
                        form.submit();
                    }

                    return;
                }

                const result = await Swal.fire({
                    title,
                    text,
                    icon,
                    showCancelButton: true,
                    confirmButtonText: 'نعم، متابعة',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true,
                });

                if (result.isConfirmed) {
                    form.dataset.confirmed = '1';
                    form.submit();
                }
            });
        });
    });
}
