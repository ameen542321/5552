// استخراج واجهة فقط؛ تبقى النماذج والمسارات وشروط العمليات كما هي.
if (document.querySelector('[data-employee-index-interface]')) {
    document.addEventListener('DOMContentLoaded', function () {
        // تأكيد إيقاف/تفعيل الموظف عبر SweetAlert بدل confirm التقليدية.
        document.querySelectorAll('.js-confirm-status').forEach(function (confirmationForm) {
            confirmationForm.addEventListener('submit', async function (confirmationSubmitEvent) {
                if (confirmationForm.dataset.confirmed === '1') {
                    return;
                }

                confirmationSubmitEvent.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (window.confirm((confirmationForm.dataset.confirmText || '') + ' هل أنت متأكد؟')) {
                        confirmationForm.dataset.confirmed = '1';
                        confirmationForm.submit();
                    }
                    return;
                }

                const confirmationResult = await Swal.fire({
                    title: confirmationForm.dataset.confirmTitle || 'تأكيد العملية',
                    text: confirmationForm.dataset.confirmText || 'هل تريد المتابعة؟',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، متابعة',
                    cancelButtonText: 'إلغاء',
                    confirmButtonColor: '',
                    cancelButtonColor: '',
                    reverseButtons: true
                });

                if (confirmationResult.isConfirmed) {
                    confirmationForm.dataset.confirmed = '1';
                    confirmationForm.submit();
                }
            });
        });
    });
}
