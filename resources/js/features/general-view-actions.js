// سلوكيات عرض عامة لا ترسل نماذج ولا تغير بيانات مالية أو مخزنية.
document.addEventListener('keydown', (event) => {
    const productSearch = document.querySelector('[data-accountant-product-search]');
    if (!productSearch || event.key !== 'Escape') return;

    document.querySelectorAll('[id^="details_"]').forEach((element) => element.classList.add('hidden'));
    document.querySelectorAll('[id^="arrow_"]').forEach((element) => element.classList.remove('rotate-180'));
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[data-accountant-product-search]')?.focus();

    document.querySelectorAll('[data-exclusive-details]').forEach((details) => {
        details.addEventListener('toggle', () => {
            if (!details.open) return;

            document.querySelectorAll('[data-exclusive-details][open]').forEach((opened) => {
                if (opened !== details) opened.removeAttribute('open');
            });
        });
    });
});
