// إصلاح مطبق: تنقل المتاجر وتبديل الثيم موحدان عبر data hooks مشتركة.
const initializeStoreNavigationSelects = () => {
    document.querySelectorAll('[data-store-navigation-select]').forEach((storeNavigationSelect) => {
        storeNavigationSelect.addEventListener('change', () => {
            const selectedStoreUrl = storeNavigationSelect.value;
            if (selectedStoreUrl) window.location.assign(selectedStoreUrl);
        });
    });
};

const applyTheme = (useDarkTheme) => {
    document.documentElement.classList.toggle('dark', useDarkTheme);
    document.documentElement.classList.toggle('light', !useDarkTheme);
    localStorage.setItem('theme', useDarkTheme ? 'dark' : 'light');

    document.querySelectorAll('[data-theme-toggle]').forEach((themeToggle) => {
        themeToggle.setAttribute('aria-pressed', String(useDarkTheme));
    });
};

const initializeThemeToggles = () => {
    const useDarkTheme = document.documentElement.classList.contains('dark');
    document.querySelectorAll('[data-theme-toggle]').forEach((themeToggle) => {
        themeToggle.setAttribute('aria-pressed', String(useDarkTheme));
        themeToggle.addEventListener('click', () => {
            applyTheme(!document.documentElement.classList.contains('dark'));
        });
    });
};

// قائمة أسماء المتاجر تبقى كاملة، لكن روابط متجر واحد فقط يسمح بفتحها في كل مرة.
const initializeStoreAccordions = () => {
    document.querySelectorAll('[data-store-accordion]').forEach((accordion) => {
        const storeItems = Array.from(accordion.querySelectorAll('[data-store-accordion-item]'));
        storeItems.forEach((storeItem) => {
            storeItem.addEventListener('toggle', () => {
                if (!storeItem.open) return;
                storeItems.forEach((otherItem) => {
                    if (otherItem !== storeItem) otherItem.open = false;
                });
            });
        });
    });
};

initializeStoreNavigationSelects();
initializeThemeToggles();
initializeStoreAccordions();

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-history-back]')) window.history.back();
});
