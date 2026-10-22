(function () {
    const timeoutMilliseconds = 10 * 60 * 1000;
    const logoutUrl = document.currentScript?.dataset.logoutUrl;

    if (!logoutUrl) {
        return;
    }

    let timeoutId;
    let isRedirecting = false;

    function startIdleTimer() {
        window.clearTimeout(timeoutId);
        timeoutId = window.setTimeout(() => {
            if (!isRedirecting) {
                isRedirecting = true;
                window.location.replace(`${logoutUrl}?reason=timeout`);
            }
        }, timeoutMilliseconds);
    }

    ['pointerdown', 'pointermove', 'keydown', 'scroll', 'touchstart'].forEach((eventName) => {
        window.addEventListener(eventName, startIdleTimer, { passive: true });
    });

    startIdleTimer();
})();