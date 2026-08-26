const createNotificationText = (className, text) => {
    const element = document.createElement('div');
    element.className = className;
    element.textContent = text ?? '';

    return element;
};

const createNotificationItem = (notificationEvent) => {
    const notificationItem = document.createElement('div');
    notificationItem.className = 'ui-notification-item ui-notification-item-unread';

    const notificationContent = document.createElement('div');
    notificationContent.className = 'flex-1 min-w-0';
    notificationContent.append(
        createNotificationText('ui-notification-title', notificationEvent.title),
        createNotificationText('ui-notification-body', notificationEvent.message),
    );
    notificationItem.append(notificationContent);

    return notificationItem;
};

const incrementNotificationBadge = (navigationRoot) => {
    const notificationBadge = navigationRoot.querySelector('[data-notif-badge]');
    if (!notificationBadge) return;

    const currentNotificationCount = Number.parseInt(notificationBadge.textContent || '0', 10) || 0;
    notificationBadge.textContent = String(currentNotificationCount + 1);
    notificationBadge.classList.remove('hidden');
};

const prependNotification = (navigationRoot, notificationEvent) => {
    const notificationList = navigationRoot.querySelector('[data-notif-list]');
    if (!notificationList) return;

    notificationList.querySelector('[data-notification-empty-state]')?.remove();
    notificationList.prepend(createNotificationItem(notificationEvent));
};

const initializeRealtimeNotifications = () => {
    if (!window.Echo) return;

    document.querySelectorAll('[data-realtime-notifications]').forEach((navigationRoot) => {
        const notificationChannel = navigationRoot.dataset.notificationChannel;
        if (!notificationChannel) return;

        window.Echo.private(notificationChannel)
            .listen('.new-notification', (notificationEvent) => {
                incrementNotificationBadge(navigationRoot);
                prependNotification(navigationRoot, notificationEvent);
            });
    });
};

initializeRealtimeNotifications();
