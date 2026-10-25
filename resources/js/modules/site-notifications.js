(() => {
    if (window.__forumSiteNotificationsInit) {
        return;
    }
    window.__forumSiteNotificationsInit = true;

    const DISMISSED_COOKIE_KEY = 'forum_dismissed_notifications_v1';
    const DISMISSED_COOKIE_DAYS = 365;
    const DISMISSAL_ENDPOINT = '/profile/site-notification-dismissals';
    const VIEW_ENDPOINT = '/profile/site-notification-views';
    const AUTHENTICATED = document.querySelector('meta[name="forum-authenticated"]')?.getAttribute('content') === '1';

    const getCsrfToken = () => {
        const token = document.querySelector('meta[name="csrf-token"]');
        return token instanceof HTMLMetaElement ? token.getAttribute('content') || '' : '';
    };

    const dismissedCookieDomains = () => {
        const host = window.location.hostname || '';
        const domains = [''];
        if (host) {
            domains.push(`; Domain=${host}`);
            if (host.includes('.')) {
                domains.push(`; Domain=.${host}`);
            }
        }

        return domains;
    };

    const readDismissedIds = () => {
        const all = document.cookie ? document.cookie.split(';') : [];
        const prefix = `${DISMISSED_COOKIE_KEY}=`;
        const matches = all.filter((item) => item.trim().startsWith(prefix));
        const match = matches.length > 0 ? matches[matches.length - 1] : null;
        if (!match) {
            return [];
        }

        try {
            const raw = decodeURIComponent(match.trim().slice(prefix.length));
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed.filter((id) => Number.isInteger(id)) : [];
        } catch (error) {
            return [];
        }
    };

    const writeDismissedIds = (ids) => {
        dismissedCookieDomains().forEach((domainPart) => {
            document.cookie = `${DISMISSED_COOKIE_KEY}=; Max-Age=0; Path=/; SameSite=Lax${domainPart}`;
        });

        const value = encodeURIComponent(JSON.stringify(ids));
        const maxAge = DISMISSED_COOKIE_DAYS * 24 * 60 * 60;
        document.cookie = `${DISMISSED_COOKIE_KEY}=${value}; Max-Age=${maxAge}; Path=/; SameSite=Lax`;
    };

    const rememberDismissal = (notificationId) => {
        const ids = readDismissedIds();
        if (!ids.includes(notificationId)) {
            ids.push(notificationId);
            writeDismissedIds(ids);
        }
    };

    const postNotificationId = async (endpoint, notificationId) => {
        if (!AUTHENTICATED) {
            return;
        }

        const csrfToken = getCsrfToken();
        if (csrfToken === '') {
            return;
        }

        try {
            await fetch(endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ notification_id: notificationId }),
            });
        } catch (error) {
            // Ignore network errors: local cookie dismissal still succeeds.
        }
    };

    const persistServerDismissal = (notificationId) => postNotificationId(DISMISSAL_ENDPOINT, notificationId);
    const persistServerView = (notificationId) => postNotificationId(VIEW_ENDPOINT, notificationId);

    const applyCookieDismissalsAndFirstViews = () => {
        const dismissedIds = readDismissedIds();

        document.querySelectorAll('[data-site-notification]').forEach((banner) => {
            if (!(banner instanceof HTMLElement)) {
                return;
            }

            const notificationId = parseInt(banner.dataset.siteNotificationId || '', 10);
            if (!Number.isInteger(notificationId)) {
                return;
            }

            if (dismissedIds.includes(notificationId)) {
                banner.hidden = true;
                return;
            }

            if (banner.hidden) {
                return;
            }

            if (banner.dataset.siteNotificationPersistent === '0') {
                rememberDismissal(notificationId);
                void persistServerView(notificationId);
            }
        });
    };

    applyCookieDismissalsAndFirstViews();

    document.addEventListener('click', (event) => {
        const trigger = event.target instanceof Element
            ? event.target.closest('[data-site-notification-dismiss]')
            : null;

        if (!trigger) {
            return;
        }

        const banner = trigger.closest('[data-site-notification]');
        if (!(banner instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();

        const notificationId = parseInt(banner.dataset.siteNotificationId || '', 10);
        if (!Number.isInteger(notificationId)) {
            return;
        }

        banner.hidden = true;
        rememberDismissal(notificationId);
        void persistServerDismissal(notificationId);
    });
})();
