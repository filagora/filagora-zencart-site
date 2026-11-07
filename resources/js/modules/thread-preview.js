(() => {
    const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    const links = Array.from(document.querySelectorAll('[data-thread-preview-url]'));
    if (!canHover || links.length === 0) {
        return;
    }

    const cache = new Map();
    const popover = document.createElement('div');
    popover.setAttribute('role', 'tooltip');
    popover.id = 'thread-preview-popover';
    popover.hidden = true;
    popover.className = 'fixed z-[1200] w-[22rem] max-w-[90vw] border border-[#d8dce2] bg-white p-3 text-xs leading-snug text-[#3a4048] shadow-lg';
    document.body.appendChild(popover);

    let showTimer = null;
    let hideTimer = null;
    let activeLink = null;

    const section = (label, post) => {
        const wrap = document.createElement('div');
        wrap.className = 'mt-2 first:mt-0';
        const head = document.createElement('div');
        head.className = 'font-semibold text-[#5c6570]';
        head.textContent = `${label}: ${post.author}, ${post.date}`;
        const body = document.createElement('div');
        body.className = 'mt-0.5';
        body.textContent = post.excerpt;
        wrap.append(head, body);

        return wrap;
    };

    const render = (data) => {
        popover.replaceChildren();
        if (data.first) {
            popover.appendChild(section('First post', data.first));
        }
        if (data.latest) {
            popover.appendChild(section('Latest post', data.latest));
        }

        return popover.childElementCount > 0;
    };

    const hide = () => {
        window.clearTimeout(showTimer);
        popover.hidden = true;
        activeLink?.removeAttribute('aria-describedby');
        activeLink = null;
    };

    const place = (link) => {
        const rect = link.getBoundingClientRect();
        const width = popover.offsetWidth;
        popover.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - width - 8))}px`;
        const below = rect.bottom + 6;
        popover.style.top = below + popover.offsetHeight > window.innerHeight
            ? `${Math.max(8, rect.top - popover.offsetHeight - 6)}px`
            : `${below}px`;
    };

    const show = async (link) => {
        const url = link.dataset.threadPreviewUrl;
        if (!cache.has(url)) {
            cache.set(url, fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then((response) => (response.ok ? response.json() : null))
                .catch(() => null));
        }
        const data = await cache.get(url);
        if (data === null) {
            cache.delete(url);
        }
        if (activeLink !== link || data === null || !render(data)) {
            return;
        }
        popover.hidden = false;
        link.setAttribute('aria-describedby', popover.id);
        place(link);
    };

    const schedule = (link) => {
        window.clearTimeout(hideTimer);
        window.clearTimeout(showTimer);
        activeLink = link;
        showTimer = window.setTimeout(() => show(link), 300);
    };

    links.forEach((link) => {
        link.addEventListener('mouseenter', () => schedule(link));
        link.addEventListener('focus', () => schedule(link));
        link.addEventListener('mouseleave', () => { hideTimer = window.setTimeout(hide, 100); });
        link.addEventListener('blur', hide);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hide();
        }
    });
    window.addEventListener('scroll', hide, { passive: true });
})();
