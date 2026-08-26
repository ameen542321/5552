// يمنع النقر المزدوج على فلترة الطلبات ويغير حالة العرض فقط دون المساس بطلبات الشراء.
document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-purchase-order-filter]');
    if (!form) return;

    const button = form.querySelector('[data-filter-submit]');
    const text = form.querySelector('[data-filter-submit-text]');
    const loader = form.querySelector('[data-filter-submit-loader]');

    if (button) {
        button.disabled = true;
        button.classList.add('opacity-75', 'cursor-not-allowed');
    }
    if (text) text.textContent = 'جاري...';
    loader?.classList.remove('hidden');
});
