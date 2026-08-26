// شاشة تحميل CARLED وربطها بجاهزية خط Cairo — تاريخ التعديل: 2026-08-09.
const root = document.documentElement;

if (root.classList.contains('ui-font-loading')) {
    const startedAt = performance.now();
    let revealed = false;
    const revealPage = () => {
        if (revealed) return;
        revealed = true;

        // مدة قصيرة وواضحة: تكفي لرؤية السيارة بدون أن يتحول التحميل إلى انتظار ممل.
        // مستخدمو تقليل الحركة لا يفرض عليهم التأخير الكامل.
        const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        const minimumDisplayTime = reducedMotion ? 120 : 1700;
        const remaining = Math.max(0, minimumDisplayTime - (performance.now() - startedAt));
        window.setTimeout(() => {
            root.classList.remove('ui-font-loading');
            const loader = document.querySelector('[data-ui-page-loader]');
            loader?.setAttribute('aria-hidden', 'true');
            window.setTimeout(() => {
                loader?.remove();
                document.dispatchEvent(new CustomEvent('ui:page-ready'));
            }, 350);
        }, remaining);
    };

    const fontsReady = document.fonts?.load
        ? Promise.all([
            document.fonts.load('400 1rem "Cairo"', 'الخط'),
            document.fonts.load('900 1rem "Cairo"', 'الخط'),
        ]).catch(() => undefined)
        : Promise.resolve();
    const pageLoaded = document.readyState === 'complete'
        ? Promise.resolve()
        : new Promise((resolve) => window.addEventListener('load', resolve, { once: true }));

    Promise.all([fontsReady, pageLoaded]).then(revealPage);

    window.setTimeout(revealPage, 3500);
}
