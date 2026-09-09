const paymentMethodLabels = {
    cash: 'كاش',
    card: 'شبكة',
    mixed: 'ميكس',
};

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-owner-credit-preview]');
    if (!trigger) return;

    const form = document.getElementById(trigger.dataset.formId || '');
    const modal = document.getElementById(trigger.dataset.modalId || '');
    if (!form || !modal) return;

    const amount = Number(form.elements.amount?.value || 0);
    const date = form.elements.collection_date?.value || '—';
    const method = form.elements.payment_method?.value || 'cash';
    const cashAmount = Number(form.elements.cash_amount?.value || 0);
    const cardAmount = Number(form.elements.card_amount?.value || 0);

    const amountTarget = modal.querySelector('[data-owner-credit-preview-value="amount"]');
    const dateTarget = modal.querySelector('[data-owner-credit-preview-value="date"]');
    const methodTarget = modal.querySelector('[data-owner-credit-preview-value="method"]');
    const mixedTarget = modal.querySelector('[data-owner-credit-preview-value="mixed"]');
    const mixedRow = modal.querySelector('[data-owner-credit-preview-mixed]');

    if (amountTarget) amountTarget.textContent = `${amount.toFixed(2)} ريال`;
    if (dateTarget) dateTarget.textContent = date;
    if (methodTarget) methodTarget.textContent = paymentMethodLabels[method] || 'غير محدد';
    if (mixedTarget) mixedTarget.textContent = `كاش ${cashAmount.toFixed(2)} ريال / شبكة ${cardAmount.toFixed(2)} ريال`;
    mixedRow?.classList.toggle('hidden', method !== 'mixed');

    modal.classList.remove('hidden');
});
