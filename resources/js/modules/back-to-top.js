(() => {
    const button = document.querySelector('[data-back-to-top]');
    if (!(button instanceof HTMLElement)) {
        return;
    }

    const SHOW_AFTER_PX = 600;
    let ticking = false;

    const update = () => {
        ticking = false;
        button.classList.toggle('is-visible', window.scrollY > SHOW_AFTER_PX);
    };

    window.addEventListener('scroll', () => {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(update);
        }
    }, { passive: true });

    button.addEventListener('click', () => {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });

        // Move focus to the top of the page so keyboard users do not stay
        // parked on a button that is about to hide.
        document.getElementById('main-content')?.focus({ preventScroll: true });
    });

    update();
})();
