// Dynamically imported from app.js only once [data-home-motion-root] is
// confirmed present (see docs/homepage-performance-fixes-plan.md, Stage
// 5), so the DOM is already ready - runs immediately instead of waiting
// for DOMContentLoaded, which may have already fired by the time an
// async import resolves.
(() => {
    const root = document.querySelector('[data-home-motion-root]');

    if (!(root instanceof HTMLElement)) {
        return;
    }

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const revealNodes = Array.from(document.querySelectorAll('[data-home-reveal]'));
    const parallaxNodes = Array.from(document.querySelectorAll('[data-home-parallax]'));
    const heroNodes = Array.from(document.querySelectorAll('[data-home-hero-reveal]'));

    if (prefersReducedMotion) {
        revealNodes.forEach((node) => node.classList.add('is-visible'));
        heroNodes.forEach((node) => node.classList.add('is-visible'));
        return;
    }

    document.documentElement.classList.add('home-motion-ready');

    heroNodes.forEach((node, index) => {
        const delay = Number(node.getAttribute('data-home-delay') ?? index * 120);

        window.setTimeout(() => {
            node.classList.add('is-visible');
        }, delay);
    });

    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            const element = entry.target;
            const delay = Number(element.getAttribute('data-home-delay') ?? 0);

            window.setTimeout(() => {
                element.classList.add('is-visible');
            }, delay);

            revealObserver.unobserve(element);
        });
    }, {
        threshold: 0.14,
        rootMargin: '0px 0px -8% 0px',
    });

    revealNodes.forEach((node) => {
        if (node.hasAttribute('data-home-hero-reveal')) {
            return;
        }

        revealObserver.observe(node);
    });

    if (parallaxNodes.length === 0) {
        return;
    }

    let ticking = false;

    // Read every node's layout first, then write style changes in a
    // separate pass - reading getBoundingClientRect() and writing a style
    // property per node in one interleaved loop forces a synchronous
    // layout recalculation on each iteration (each write invalidates the
    // layout the next node's read needs). Splitting read/write into two
    // passes lets the browser batch layout once for the whole set instead.
    const updateParallax = () => {
        const viewportHeight = window.innerHeight || 1;

        const updates = parallaxNodes.map((node) => ({
            node,
            speed: Number(node.getAttribute('data-home-parallax-speed') ?? 0.08),
            rect: node.getBoundingClientRect(),
        }));

        updates.forEach(({ node, speed, rect }) => {
            if (rect.bottom < 0 || rect.top > viewportHeight) {
                node.style.removeProperty('--home-parallax-y');
                return;
            }

            const distanceFromCenter = rect.top + (rect.height / 2) - (viewportHeight / 2);
            const translateY = distanceFromCenter * speed * -1;

            node.style.setProperty('--home-parallax-y', `${translateY.toFixed(2)}px`);
        });

        ticking = false;
    };

    const requestParallaxUpdate = () => {
        if (ticking) {
            return;
        }

        ticking = true;
        window.requestAnimationFrame(updateParallax);
    };

    updateParallax();

    window.addEventListener('scroll', requestParallaxUpdate, { passive: true });
    window.addEventListener('resize', requestParallaxUpdate);
})();
