(() => {
    if (window.__forumFormSubmitLockInit) {
        return;
    }
    window.__forumFormSubmitLockInit = true;

    const overlay = document.querySelector('[data-form-submit-lock-overlay]');
    if (!(overlay instanceof HTMLElement)) {
        return;
    }

    const main = document.getElementById('main-content');

    const setSubmitButtonsDisabled = (disabled) => {
        document.querySelectorAll('form[data-lock-on-submit] button[type="submit"]').forEach((button) => {
            if (button instanceof HTMLButtonElement) {
                button.disabled = disabled;
            }
        });
    };

    const lock = () => {
        overlay.hidden = false;
        document.body.style.overflow = 'hidden';
        if (main instanceof HTMLElement) {
            main.setAttribute('aria-hidden', 'true');
        }
    };

    const unlock = () => {
        overlay.hidden = true;
        document.body.style.overflow = '';
        if (main instanceof HTMLElement) {
            main.removeAttribute('aria-hidden');
        }
        setSubmitButtonsDisabled(false);
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-lock-on-submit')) {
            return;
        }

        setSubmitButtonsDisabled(true);
        lock();
    });

    // A browser can restore this exact page — including our disabled
    // button/overlay state — from bfcache when the user navigates back,
    // instead of doing a fresh load. Reset the lock so the page isn't stuck
    // looking mid-submit.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            unlock();
        }
    });
})();
