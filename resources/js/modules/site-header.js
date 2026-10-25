    (() => {
        if (window.__legacyHeaderDocsMenuInit) {
            return;
        }
        window.__legacyHeaderDocsMenuInit = true;

        const closeMenus = (except) => {
            document.querySelectorAll('details[data-docs-menu][open]').forEach((menu) => {
                if (except && menu === except) {
                    return;
                }
                menu.removeAttribute('open');
            });
        };

        const closeUserMenus = (except) => {
            document.querySelectorAll('details[data-user-menu][open]').forEach((menu) => {
                if (except && menu === except) {
                    return;
                }
                menu.removeAttribute('open');
            });
        };

        // The mobile nav intentionally isn't a native <details>/<summary>:
        // that element auto-toggles closed on any click inside it (not just
        // the summary), which on some WebKit-based browsers races against a
        // tapped link's own navigation — the browser can close/detach the
        // panel before the link finishes navigating, so the tap silently
        // does nothing. Managing open/closed state ourselves removes that
        // built-in auto-toggle entirely; closing only ever happens from the
        // explicit handlers below (trigger tap, outside click, Escape).
        const mobileNavWrapper = document.querySelector('[data-mobile-nav]');
        const mobileNavTrigger = mobileNavWrapper?.querySelector('[data-mobile-nav-trigger]');
        const mobileNavPanel = mobileNavWrapper?.querySelector('[data-mobile-nav-panel]');

        const isMobileNavOpen = () => mobileNavWrapper?.classList.contains('is-open') ?? false;

        const openMobileNav = () => {
            if (!(mobileNavWrapper instanceof HTMLElement) || !(mobileNavPanel instanceof HTMLElement) || !(mobileNavTrigger instanceof HTMLElement)) {
                return;
            }
            mobileNavWrapper.classList.add('is-open');
            mobileNavPanel.hidden = false;
            mobileNavTrigger.setAttribute('aria-expanded', 'true');
        };

        const closeMobileNav = () => {
            if (!(mobileNavWrapper instanceof HTMLElement) || !(mobileNavPanel instanceof HTMLElement) || !(mobileNavTrigger instanceof HTMLElement)) {
                return;
            }
            mobileNavWrapper.classList.remove('is-open');
            mobileNavPanel.hidden = true;
            mobileNavTrigger.setAttribute('aria-expanded', 'false');
        };

        mobileNavTrigger?.addEventListener('click', (event) => {
            event.preventDefault();
            if (isMobileNavOpen()) {
                closeMobileNav();
            } else {
                openMobileNav();
            }
        });

        // Same reasoning as the mobile nav above: no native <details>, since
        // the panel (the search strip, a separate row) isn't adjacent to the
        // trigger (an icon in the main row) the way <summary>/content need
        // to be, and the panel contains an <input> that clicking/typing in
        // would otherwise race against <details>' own auto-close. Elements
        // are looked up independently rather than via a shared wrapper -
        // both are simply absent unless the viewing user has turned on the
        // "Header Search Icon" preference (users.search_icon_enabled,
        // profile settings tab) or is previewing it for this session via
        // ?search_icon_toggle=1 (see ApplySearchIconToggleOverride).
        const searchToggleTrigger = document.querySelector('[data-search-toggle-trigger]');
        const searchTogglePanel = document.querySelector('[data-search-toggle-panel]');

        const isSearchToggleOpen = () => searchTogglePanel instanceof HTMLElement && !searchTogglePanel.hidden;

        const openSearchToggle = () => {
            if (!(searchTogglePanel instanceof HTMLElement) || !(searchToggleTrigger instanceof HTMLElement)) {
                return;
            }
            searchTogglePanel.hidden = false;
            searchToggleTrigger.setAttribute('aria-expanded', 'true');
            const searchInput = document.getElementById('desktop-global-search');
            if (searchInput instanceof HTMLElement) {
                searchInput.focus();
            }
        };

        const closeSearchToggle = () => {
            if (!(searchTogglePanel instanceof HTMLElement) || !(searchToggleTrigger instanceof HTMLElement)) {
                return;
            }
            searchTogglePanel.hidden = true;
            searchToggleTrigger.setAttribute('aria-expanded', 'false');
        };

        searchToggleTrigger?.addEventListener('click', (event) => {
            event.preventDefault();
            if (isSearchToggleOpen()) {
                closeSearchToggle();
            } else {
                openSearchToggle();
            }
        });

        // The avatar and bell menus (data-user-menu) are click-to-open only
        // (native <details>/<summary> toggle handles this for free) - no
        // hover-to-open. They have small click targets and forms inside them
        // (mark-all-read, logout, the staff preview toggle), so an immediate
        // mouseleave-closes-it behaviour risked dismissing the menu out from
        // under a click aimed at one of them. The navigation menus
        // (data-docs-menu) hold plain links only, so they additionally open
        // on hover - done the WCAG 1.4.13 way, see below. Dismissal (outside
        // click, Escape, focus-out) is unaffected, handled below regardless
        // of how the menu was opened.
        //
        // Hover used to also close sibling menus on open (mouseenter closed
        // "the others"); native <details> elements don't coordinate with
        // each other on their own, so without this, opening one by click no
        // longer closed a different one already open. The native `toggle`
        // event covers this instead - it fires for keyboard activation too
        // (Enter/Space on the <summary>), not just clicks.
        document.querySelectorAll('details[data-docs-menu], details[data-user-menu]').forEach((menu) => {
            if (!(menu instanceof HTMLDetailsElement)) {
                return;
            }

            menu.addEventListener('toggle', () => {
                if (!menu.open) {
                    return;
                }
                if (menu.hasAttribute('data-docs-menu')) {
                    closeMenus(menu);
                }
                if (menu.hasAttribute('data-user-menu')) {
                    closeUserMenus(menu);
                }
            });
        });

        // Hover-to-open for the navigation menus only (never data-user-menu).
        // Built to satisfy WCAG 1.4.13: the panel is a descendant of the
        // <details>, so the pointer can travel from the trigger onto it without
        // leaving (the panel's pt-3 bridges the visual gap); a short grace
        // period before closing forgives a slip; and Escape still dismisses
        // (handled below). A mouse-only gesture: touch and keyboard keep the
        // native click/Enter toggle, and a menu the visitor opened by click, a
        // key press, or by clicking the trigger while it was hover-open is
        // "pinned" and stays until dismissed. A menu holding keyboard focus
        // is never closed by the pointer leaving.
        const hoverCapable = window.matchMedia('(hover: hover) and (pointer: fine)');
        const HOVER_OPEN_DELAY_MS = 80;
        const HOVER_CLOSE_DELAY_MS = 250;

        document.querySelectorAll('details[data-docs-menu]').forEach((menu) => {
            if (!(menu instanceof HTMLDetailsElement)) {
                return;
            }

            let openTimer = null;
            let closeTimer = null;
            let openedByHover = false;

            const cancelTimers = () => {
                window.clearTimeout(openTimer);
                window.clearTimeout(closeTimer);
                openTimer = null;
                closeTimer = null;
            };

            menu.addEventListener('pointerenter', (event) => {
                if (event.pointerType !== 'mouse' || !hoverCapable.matches) {
                    return;
                }
                window.clearTimeout(closeTimer);
                closeTimer = null;
                if (menu.open) {
                    return;
                }
                openTimer = window.setTimeout(() => {
                    openTimer = null;
                    if (!menu.open) {
                        openedByHover = true;
                        menu.setAttribute('open', '');
                    }
                }, HOVER_OPEN_DELAY_MS);
            });

            menu.addEventListener('pointerleave', (event) => {
                if (event.pointerType !== 'mouse') {
                    return;
                }
                window.clearTimeout(openTimer);
                openTimer = null;
                if (!openedByHover) {
                    return;
                }
                closeTimer = window.setTimeout(() => {
                    closeTimer = null;
                    if (openedByHover && !menu.contains(document.activeElement)) {
                        menu.removeAttribute('open');
                    }
                    openedByHover = false;
                }, HOVER_CLOSE_DELAY_MS);
            });

            // Clicking the trigger of a hover-opened menu pins it open rather
            // than toggling it shut under the visitor's pointer.
            menu.querySelector('summary')?.addEventListener('click', (event) => {
                window.clearTimeout(openTimer);
                openTimer = null;
                if (openedByHover && menu.open) {
                    event.preventDefault();
                    openedByHover = false;
                }
            });

            menu.addEventListener('toggle', () => {
                if (!menu.open) {
                    openedByHover = false;
                    cancelTimers();
                }
            });
        });

        // Dismiss open header menus (navigation, bell and avatar) on a press
        // outside them. The click handlers below already do this, but iOS
        // Safari does not always deliver a click for a tap on a non-interactive
        // part of the page, so a menu could stay open there. Pointer events fire
        // for any press on any element, so this covers that case and is a no-op
        // everywhere the click already closed the menu. A press inside an open
        // menu (including on its own trigger) is ignored, so toggling is
        // unchanged.
        document.addEventListener('pointerdown', (event) => {
            const target = event.target instanceof Node ? event.target : null;

            document.querySelectorAll('details[data-docs-menu][open], details[data-user-menu][open]').forEach((menu) => {
                if (target && menu.contains(target)) {
                    return;
                }
                menu.removeAttribute('open');
            });
        });

        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            const openMenu = document.querySelector('details[data-docs-menu][open]');
            if (!(openMenu instanceof HTMLDetailsElement)) {
                return;
            }
            if (target && openMenu.contains(target)) {
                return;
            }
            closeMenus();
            closeUserMenus();
        });

        document.addEventListener('click', (event) => {
            if (!isMobileNavOpen()) {
                return;
            }
            const target = event.target instanceof Element ? event.target : null;
            if (target && mobileNavWrapper?.contains(target)) {
                return;
            }
            closeMobileNav();
        });

        document.addEventListener('click', (event) => {
            if (!isSearchToggleOpen()) {
                return;
            }
            const target = event.target instanceof Element ? event.target : null;
            if (target && (searchTogglePanel?.contains(target) || searchToggleTrigger?.contains(target))) {
                return;
            }
            closeSearchToggle();
        });

        document.addEventListener('focusin', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            const openMenu = document.querySelector('details[data-docs-menu][open]');
            if (!(openMenu instanceof HTMLDetailsElement)) {
                return;
            }
            if (target && openMenu.contains(target)) {
                return;
            }
            closeMenus();
            closeUserMenus();
        });

        document.addEventListener('focusin', (event) => {
            if (!isMobileNavOpen()) {
                return;
            }
            const target = event.target instanceof Element ? event.target : null;
            if (target && mobileNavWrapper?.contains(target)) {
                return;
            }
            closeMobileNav();
        });

        document.addEventListener('focusin', (event) => {
            if (!isSearchToggleOpen()) {
                return;
            }
            const target = event.target instanceof Element ? event.target : null;
            if (target && (searchTogglePanel?.contains(target) || searchToggleTrigger?.contains(target))) {
                return;
            }
            closeSearchToggle();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            closeMenus();
            closeUserMenus();
            closeMobileNav();
            closeSearchToggle();
        });
    })();

    (() => {
        if (window.__legacyAuthModalInit) {
            return;
        }
        window.__legacyAuthModalInit = true;

        const authModal = document.getElementById('home-auth-modal');
        const authDialog = document.getElementById('home-auth-modal-dialog');

        // Login and register are plain, server-rendered forms, each living in
        // their own always-present (hidden) panel - no iframe, no postMessage
        // bridge, no pending-overlay dance.
        const nativeAuthPanels = {
            login: document.getElementById('home-auth-modal-native-login'),
            register: document.getElementById('home-auth-modal-native-register'),
            'password/forgot': document.getElementById('home-auth-modal-native-password-request'),
        };

        const resolveNativeAuthPanelKey = (url) => {
            if (typeof url !== 'string') {
                return null;
            }

            for (const key of Object.keys(nativeAuthPanels)) {
                if (url === `/${key}` || url.startsWith(`/${key}?`) || url.startsWith(`/${key}#`)) {
                    return key;
                }
            }

            return null;
        };

        const isNativeAuthUrl = (url) => resolveNativeAuthPanelKey(url) !== null;
        let authLauncherElement = null;
        let savedMainAriaHidden = null;

        const getFocusable = (container) => {
            const nodes = container.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');

            return Array.from(nodes).filter((node) => node instanceof HTMLElement && !node.hasAttribute('hidden'));
        };

        const trapAuthFocus = (event) => {
            if (event.key !== 'Tab' || !(authModal instanceof HTMLElement) || authModal.hidden || !(authDialog instanceof HTMLElement)) {
                return;
            }

            const focusable = getFocusable(authDialog);
            if (focusable.length === 0) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            const active = document.activeElement;

            if (event.shiftKey && active === first) {
                event.preventDefault();
                last.focus();
                return;
            }

            if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        };

        const openAuthModal = (url, launcher = null) => {
            if (!(authModal instanceof HTMLElement) || !(authDialog instanceof HTMLElement)) {
                return;
            }

            authLauncherElement = launcher instanceof HTMLElement ? launcher : (document.activeElement instanceof HTMLElement ? document.activeElement : null);

            const nativeKey = resolveNativeAuthPanelKey(url);
            let activeNativePanel = null;

            Object.entries(nativeAuthPanels).forEach(([key, panel]) => {
                if (!(panel instanceof HTMLElement)) {
                    return;
                }

                panel.hidden = key !== nativeKey;
                if (key === nativeKey) {
                    activeNativePanel = panel;
                }
            });

            authModal.hidden = false;
            document.body.style.overflow = 'hidden';
            const main = document.querySelector('main');
            if (main instanceof HTMLElement) {
                savedMainAriaHidden = main.getAttribute('aria-hidden');
                main.setAttribute('aria-hidden', 'true');
            }

            requestAnimationFrame(() => {
                const nativeInput = activeNativePanel instanceof HTMLElement
                    ? activeNativePanel.querySelector('input')
                    : null;
                const closeButton = authDialog.querySelector('[data-auth-modal-close-btn]');

                if (nativeInput instanceof HTMLElement) {
                    nativeInput.focus();
                } else if (closeButton instanceof HTMLElement) {
                    closeButton.focus();
                } else {
                    authDialog.focus();
                }
            });
        };

        const sanitizeIntendedPath = (value) => {
            if (typeof value !== 'string' || value.length === 0) {
                return null;
            }

            if (!value.startsWith('/') || value.startsWith('//')) {
                return null;
            }

            return value;
        };

        const buildAuthModalUrl = (url) => {
            const currentPath = sanitizeIntendedPath(`${window.location.pathname}${window.location.search}${window.location.hash}`);
            if (currentPath === null) {
                return url;
            }

            if (!isNativeAuthUrl(url) || url.includes('intended=')) {
                return url;
            }

            const separator = url.includes('?') ? '&' : '?';

            return `${url}${separator}intended=${encodeURIComponent(currentPath)}`;
        };

        const closeAuthModal = () => {
            if (!(authModal instanceof HTMLElement)) {
                return;
            }

            authModal.hidden = true;
            document.body.style.overflow = '';
            const main = document.querySelector('main');
            if (main instanceof HTMLElement) {
                if (savedMainAriaHidden === null) {
                    main.removeAttribute('aria-hidden');
                } else {
                    main.setAttribute('aria-hidden', savedMainAriaHidden);
                }
            }

            if (authLauncherElement instanceof HTMLElement) {
                authLauncherElement.focus();
            }
        };

        document.addEventListener('click', (event) => {
            const openMenu = document.querySelector('details[data-user-menu][open]');
            if (openMenu instanceof HTMLDetailsElement) {
                const clickedInsideMenu = event.target instanceof Node && openMenu.contains(event.target);
                if (!clickedInsideMenu) {
                    openMenu.removeAttribute('open');
                }
            }

            const authLauncher = event.target instanceof Element
                ? event.target.closest('[data-auth-modal-url]')
                : null;
            if (authLauncher instanceof HTMLElement) {
                const url = buildAuthModalUrl(authLauncher.dataset.authModalUrl || '/login');
                event.preventDefault();
                openAuthModal(url, authLauncher);
                return;
            }

            const closeTarget = event.target instanceof Element
                ? event.target.closest('[data-auth-modal-close]')
                : null;
            if (closeTarget) {
                event.preventDefault();
                closeAuthModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && authModal instanceof HTMLElement && !authModal.hidden) {
                closeAuthModal();
            }
            trapAuthFocus(event);
        });

        const query = new URLSearchParams(window.location.search);
        const authMode = query.get('auth');
        const intended = sanitizeIntendedPath(query.get('intended'));
        const queryTargetMap = {
            login: '/login',
            register: '/register',
        };
        const queryTarget = authMode ? queryTargetMap[authMode] : null;

        if (typeof queryTarget === 'string') {
            let modalUrl = queryTarget;

            if (intended !== null) {
                const separator = modalUrl.includes('?') ? '&' : '?';
                modalUrl = `${modalUrl}${separator}intended=${encodeURIComponent(intended)}`;
            }

            openAuthModal(modalUrl);

            const cleanUrl = new URL(window.location.href);
            cleanUrl.searchParams.delete('auth');
            cleanUrl.searchParams.delete('intended');
            window.history.replaceState({}, document.title, `${cleanUrl.pathname}${cleanUrl.search}${cleanUrl.hash}`);
        } else if (authModal instanceof HTMLElement && resolveNativeAuthPanelKey(`/${authModal.dataset.authModalOpenOnLoad}`) !== null) {
            // A login/register submission failed validation: the plain POST
            // redirected back here with errors in the session, and that
            // form's partial (always present, hidden, in this modal) already
            // re-rendered them server-side. Just reopen the modal on top of
            // whatever page that redirect landed on.
            openAuthModal(`/${authModal.dataset.authModalOpenOnLoad}`);
        }
    })();
