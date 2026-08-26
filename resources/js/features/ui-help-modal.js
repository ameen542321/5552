const modal = () => document.querySelector('[data-ui-help-modal]');
let lastTrigger = null;
let originalParent = null;
let originalNextSibling = null;
let bodyWasLocked = false;

function restoreHelpModalPosition(helpModal) {
    if (!helpModal || !originalParent) return;
    if (originalNextSibling?.parentNode === originalParent) {
        originalParent.insertBefore(helpModal, originalNextSibling);
    } else {
        originalParent.appendChild(helpModal);
    }
    originalParent = null;
    originalNextSibling = null;
}

function closeHelpModal() {
    const helpModal = modal();
    helpModal?.classList.add('hidden');
    if (!bodyWasLocked) document.body.classList.remove('ui-scroll-lock');
    lastTrigger?.focus();
    lastTrigger = null;
    bodyWasLocked = false;
    // إبقاء التولتيب داخل المودال الأب حتى تنتهي نقرة الإغلاق يمنع click.away من إغلاق المودال الأب.
    window.setTimeout(() => restoreHelpModalPosition(helpModal), 0);
}

function openHelpModal(trigger) {
    const helpModal = modal();
    if (!helpModal) return;

    lastTrigger = trigger;
    bodyWasLocked = document.body.classList.contains('ui-scroll-lock');
    const parentModalPanel = trigger.closest('.ui-modal-panel');
    if (parentModalPanel && helpModal.parentElement !== parentModalPanel) {
        originalParent = helpModal.parentElement;
        originalNextSibling = helpModal.nextSibling;
        parentModalPanel.appendChild(helpModal);
    }

    helpModal.querySelector('[data-ui-help-modal-title]').textContent = trigger.dataset.uiHelpTitle || 'توضيح';
    helpModal.querySelector('[data-ui-help-modal-body]').innerHTML = trigger.dataset.uiHelpBody || '';
    helpModal.classList.remove('hidden');
    document.body.classList.add('ui-scroll-lock');
    helpModal.querySelector('[data-ui-help-close]')?.focus();
}

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-ui-help-title]');
    if (trigger) {
        event.preventDefault();
        openHelpModal(trigger);
        return;
    }

    if (event.target.closest('[data-ui-help-close]') || event.target === modal()) closeHelpModal();
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal()?.classList.contains('hidden')) closeHelpModal();
});
