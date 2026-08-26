const confirmationFormSelector = 'form[data-ui-confirm]';
const confirmationNavigationSelector = 'a[data-ui-confirm-navigation]';

const requestConfirmation = async (confirmationTitle, confirmationText) => {
    if (!window.Swal) return window.confirm(confirmationText || confirmationTitle);

    const result = await window.Swal.fire({
        title: confirmationTitle,
        text: confirmationText,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'نعم، متابعة',
        cancelButtonText: 'إلغاء',
        reverseButtons: true,
        focusCancel: true,
        background: '',
        color: '',
        confirmButtonColor: '',
        cancelButtonColor: '',
    });
    return result.isConfirmed;
};

document.addEventListener('submit', async (event) => {
    const confirmationForm = event.target.closest(confirmationFormSelector);
    if (!confirmationForm || confirmationForm.dataset.uiConfirmationApproved === 'true') return;

    event.preventDefault();
    const isConfirmed = await requestConfirmation(
        confirmationForm.dataset.uiConfirmTitle || 'تأكيد العملية',
        confirmationForm.dataset.uiConfirm,
    );
    if (!isConfirmed) return;

    const submitter = event.submitter || undefined;
    confirmationForm.dataset.uiConfirmationApproved = 'true';
    confirmationForm.requestSubmit(submitter);
    if (submitter && confirmationForm.hasAttribute('data-ui-confirm-busy')) {
        submitter.disabled = true;
        submitter.textContent = confirmationForm.dataset.uiConfirmBusy || 'انتظر...';
    }
    delete confirmationForm.dataset.uiConfirmationApproved;
});

// إصلاح مطبق: الروابط الحساسة تستخدم الحوار المركزي نفسه دون JavaScript مضمّن في Blade.
document.addEventListener('click', async (event) => {
    const confirmationLink = event.target.closest(confirmationNavigationSelector);
    if (!confirmationLink) return;

    event.preventDefault();
    const isConfirmed = await requestConfirmation(
        confirmationLink.dataset.uiConfirmTitle || 'تأكيد الانتقال',
        confirmationLink.dataset.uiConfirmNavigation,
    );
    if (isConfirmed) window.location.assign(confirmationLink.href);
});
