// استخراج واجهة فقط؛ تبقى النماذج والمسارات وشروط العمليات كما هي.
const root = document.querySelector('[data-employee-actions-config]');
if (root) {
    const config = JSON.parse(root.dataset.employeeActionsConfig || '{}');
    document.addEventListener('DOMContentLoaded', function () {
        // تأكيد إيقاف/تفعيل الموظف من الصفحة الشخصية مع الحفاظ على رابط العودة الحالي.
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
                    confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--ui-danger-text').trim(),
                    cancelButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--ui-text-soft').trim(),
                    reverseButtons: true
                });

                if (confirmationResult.isConfirmed) {
                    confirmationForm.dataset.confirmed = '1';
                    confirmationForm.submit();
                }
            });
        });

        document.querySelectorAll('form[data-confirm-delete]').forEach(function (deleteForm) {
            deleteForm.addEventListener('submit', async function (deleteSubmitEvent) {
                if (deleteForm.dataset.confirmed === '1') {
                    return;
                }

                deleteSubmitEvent.preventDefault();
                const message = deleteForm.dataset.confirmDelete || 'هل تريد حذف هذه العملية؟';

                if (typeof Swal === 'undefined') {
                    if (window.confirm(message)) {
                        deleteForm.dataset.confirmed = '1';
                        deleteForm.submit();
                    }
                    return;
                }

                const result = await Swal.fire({
                    title: 'تأكيد الحذف',
                    text: message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، حذف',
                    cancelButtonText: 'إلغاء',
                    confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--ui-danger-text').trim(),
                    cancelButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--ui-text-soft').trim(),
                    reverseButtons: true
                });

                if (result.isConfirmed) {
                    deleteForm.dataset.confirmed = '1';
                    deleteForm.submit();
                }
            });
        });

    });

    document.addEventListener('DOMContentLoaded', function () {

        const emailInput   = document.getElementById('emailInput');
        const emailExistsWarningBox = document.getElementById('emailExistsWarning');
        const promoteSubmitButton = document.getElementById('promoteSubmit');
        const promoteAccountantForm = document.querySelector('#promoteModal form');

        // منع إرسال النموذج إذا كان الزر معطلاً
        if (promoteAccountantForm) {
            promoteAccountantForm.addEventListener('submit', function (promoteSubmitEvent) {
                if (promoteSubmitButton.disabled) {
                    promoteSubmitEvent.preventDefault();
                }
            });
        }

        // فحص البريد عبر AJAX
        if (emailInput) {
            emailInput.addEventListener('input', function () {

                // منع الرموز غير المسموح بها
                const invalidPattern = /[^a-zA-Z0-9@._\-+]/;
                if (invalidPattern.test(emailInput.value)) {
                    emailExistsWarningBox.textContent = "البريد يحتوي على حروف أو رموز غير مسموح بها.";
                    emailExistsWarningBox.classList.remove('hidden');
                    promoteSubmitButton.disabled = true;
                    promoteSubmitButton.classList.add('opacity-50', 'cursor-not-allowed');
                    return;
                }

                // فحص البريد يبقى داخل مودال الترقية حتى لا يتم إرسال نموذج ببيانات غير صالحة.
                fetch(config.checkEmailUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": config.csrfToken
                    },
                    body: JSON.stringify({ email: emailInput.value })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.exists) {
                        emailExistsWarningBox.textContent = "هذا البريد مستخدم مسبقًا. الرجاء إدخال بريد آخر.";
                        emailExistsWarningBox.classList.remove('hidden');
                        promoteSubmitButton.disabled = true;
                        promoteSubmitButton.classList.add('opacity-50', 'cursor-not-allowed');
                    } else {
                        emailExistsWarningBox.classList.add('hidden');
                        promoteSubmitButton.disabled = false;
                        promoteSubmitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                    }
                })
                .catch(() => {
                    emailExistsWarningBox.classList.add('hidden');
                    promoteSubmitButton.disabled = false;
                    promoteSubmitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                });

            });
        }

    });
}
