const welcomeProgressRoot = document.querySelector('[data-welcome-progress]');

if (welcomeProgressRoot) {
    const progressBar = welcomeProgressRoot.querySelector('#welcomeProgress');
    const continueForm = welcomeProgressRoot.querySelector('#welcomeContinueForm');
    const skipButton = welcomeProgressRoot.querySelector('#skipBtn');

    if (progressBar && continueForm && skipButton) {
        let currentProgress = 0;
        const progressInterval = window.setInterval(() => {
            currentProgress += 5;
            progressBar.value = currentProgress;

            if (currentProgress < 100) return;

            window.clearInterval(progressInterval);
            skipButton.classList.remove('hidden');
            continueForm.submit();
        }, 100);
    }
}
