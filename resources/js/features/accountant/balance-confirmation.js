function themeColor(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

function submitConfirmed(form) {
    // تمنع العلامة اعتراض الإرسال للمرة الثانية بعد موافقة المستخدم.
    form.dataset.confirmed = '1';
    form.submit();
}

function suspendBalanceDialog(form) {
    const dialog = form.closest('.ui-modal-backdrop');
    dialog?.classList.add('ui-modal-suspended');
    dialog?.setAttribute('aria-hidden', 'true');

    return () => {
        dialog?.classList.remove('ui-modal-suspended');
        dialog?.removeAttribute('aria-hidden');
    };
}

document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-accountant-balance-form]');
    if (!form || form.dataset.confirmed === '1') return;

    // وجود تاريخ مرجع له أولوية؛ وإلا لا يظهر التأكيد إلا عند اختيار تاريخ العمل التالي.
    const activeGapDate = form.dataset.activeShiftGapDate;
    const selected = form.querySelector('input[name="next_shift_decision"]:checked');
    if (!activeGapDate && (!selected || selected.value !== 'next_business_date')) return;

    event.preventDefault();
    if (typeof window.Swal === 'undefined') {
        submitConfirmed(form);
        return;
    }

    const dialog = activeGapDate
        ? {
            title: `إصدار موازنة ${activeGapDate}`,
            text: 'سيتم إصدار موازنة التاريخ المرجع فقط، ولن يتم إغلاق شفت تاريخ اليوم الحالي.',
            confirmButtonText: 'إصدار الموازنة',
        }
        : {
            title: 'الانتقال لتاريخ العمل التالي',
            text: 'سيتم إنهاء يوم العمل الحالي ولن يتم فتح شفت ثانٍ لهذا التاريخ إلا بتدخل المالك.',
            confirmButtonText: 'متابعة',
        };

    // نخفي نافذة تفاصيل الإقفال مؤقتًا حتى يكون تأكيد يوم المرجع هو النافذة الوحيدة النشطة بصريًا.
    const restoreBalanceDialog = suspendBalanceDialog(form);
    window.Swal.fire({
        icon: 'warning',
        ...dialog,
        showCancelButton: true,
        cancelButtonText: 'إلغاء',
        confirmButtonColor: themeColor('--ui-brand'),
        cancelButtonColor: themeColor('--ui-danger-text'),
        background: themeColor('--ui-surface'),
        color: themeColor('--ui-text'),
    }).then((result) => {
        if (result.isConfirmed) {
            submitConfirmed(form);
            return;
        }

        restoreBalanceDialog();
    });
});

window.addEventListener('load', () => {
    // يمرر Blade الرابط كبيانات آمنة فقط، وتبقى مسؤولية فتح الرسالة في هذه الوحدة.
    const notice = document.querySelector('[data-accountant-whatsapp-url]');
    if (!notice) return;
    const url = notice.dataset.accountantWhatsappUrl;

    if (typeof window.Swal === 'undefined') {
        if (window.confirm('تم الإقفال بنجاح، هل تريد فتح واتساب لإرسال التقرير؟')) window.open(url, '_blank');
        return;
    }

    window.Swal.fire({
        title: '✅ تم الإقفال بنجاح',
        html: '<div class="text-center"><i class="fa-solid fa-check-circle fa-4x ui-status-success mb-4"></i><p class="text-lg font-bold ui-title mb-2">تم إغلاق الشفت بنجاح</p><div class="ui-surface-strong-bg rounded-lg p-4 mb-4"><p class="ui-text-soft mb-1">يمكنك الآن إرسال التقرير للمالك عبر الواتساب</p></div><p class="ui-text-soft text-sm">سيتم فتح تطبيق واتساب في نافذة جديدة</p></div>',
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: '<i class="fab fa-whatsapp ml-1"></i> إرسال عبر واتساب',
        cancelButtonText: 'لاحقاً',
        confirmButtonColor: themeColor('--ui-brand'),
        cancelButtonColor: themeColor('--ui-danger-text'),
        background: themeColor('--ui-surface'),
        color: themeColor('--ui-text'),
        allowOutsideClick: false,
    }).then((result) => {
        if (result.isConfirmed) window.open(url, '_blank');
    });
});
