const openModal = (modal) => modal?.classList.remove('hidden');
const closeModal = (modal) => modal?.classList.add('hidden');

document.addEventListener('click', (event) => {
    const createModal = document.querySelector('[data-owner-purchase-create-modal]');
    const editModal = document.querySelector('[data-owner-purchase-edit-modal]');

    if (event.target.closest('[data-owner-purchase-create-open]')) {
        openModal(createModal);
        return;
    }

    const editButton = event.target.closest('[data-owner-purchase-edit-open]');
    if (editButton && editModal) {
        const form = editModal.querySelector('[data-owner-purchase-edit-form]');
        form.action = editButton.dataset.updateUrl;
        editModal.querySelector('[data-owner-purchase-edit-type]').value = editButton.dataset.type || '';
        editModal.querySelector('[data-owner-purchase-edit-amount]').value = editButton.dataset.amount || '';
        editModal.querySelector('[data-owner-purchase-edit-description]').value = editButton.dataset.description || '';
        editModal.querySelector('[data-owner-purchase-edit-date]').value = editButton.dataset.businessDate || '';
        openModal(editModal);
        return;
    }

    if (event.target.closest('[data-owner-purchase-modal-close]')) {
        closeModal(event.target.closest('.ui-modal-backdrop'));
        return;
    }

    if (event.target === createModal) closeModal(createModal);
    if (event.target === editModal) closeModal(editModal);
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    closeModal(document.querySelector('[data-owner-purchase-create-modal]'));
    closeModal(document.querySelector('[data-owner-purchase-edit-modal]'));
});
