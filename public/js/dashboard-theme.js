(() => {
    const savedTheme = localStorage.getItem('theme');
    const useLightTheme = savedTheme === 'light';

    document.documentElement.classList.toggle('light', useLightTheme);
    document.documentElement.classList.toggle('dark', !useLightTheme);
})();
