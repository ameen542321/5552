const flashMessagesRoot = document.querySelector('[data-ui-flash-messages]');

const showFlashMessages = () => {
    if (!flashMessagesRoot || !window.Swal?.fire) return;

    const flashMessages = [
        { icon: 'success', title: flashMessagesRoot.dataset.successMessage },
        { icon: 'error', title: flashMessagesRoot.dataset.errorMessage },
        { icon: 'warning', title: flashMessagesRoot.dataset.warningMessage },
        { icon: 'info', title: flashMessagesRoot.dataset.infoMessage },
    ];

    flashMessages.forEach((flashMessage) => {
        if (!flashMessage.title) return;

        // تبقى رسالة الحالة في مركز الشاشة على جميع المقاسات بدل الظهور في زاوية مختلفة.
        window.Swal.fire({
            icon: flashMessage.icon,
            title: flashMessage.title,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
        });
    });
};

if (document.documentElement.classList.contains('ui-font-loading')) {
    document.addEventListener('ui:page-ready', showFlashMessages, { once: true });
} else {
    showFlashMessages();
}
