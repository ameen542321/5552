const monitor = document.querySelector('[data-security-session-monitor]');

if (monitor) {
    const endpoint = monitor.dataset.securityCheckUrl;
    const interval = Math.max(900000, Number(monitor.dataset.securityCheckInterval) || 900000);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let lastActivityAt = Date.now();
    let running = false;

    const markActivity = () => {
        lastActivityAt = Date.now();
    };

    ['pointerdown', 'keydown', 'touchstart'].forEach((eventName) => {
        document.addEventListener(eventName, markActivity, { passive: true });
    });

    const runCheck = async () => {
        const recentlyActive = Date.now() - lastActivityAt <= interval;
        if (!endpoint || !csrf || running || document.visibilityState !== 'visible' || !recentlyActive) return;

        running = true;
        try {
            await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ automatic: true }),
            });
        } catch {
            // الفحص المساعد لا يعطل جلسة الأدمن أو واجهة اللوحة عند تعذر الشبكة.
        } finally {
            running = false;
        }
    };

    window.setInterval(runCheck, interval);
}
