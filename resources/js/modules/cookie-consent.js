(() => {
    if (window.__forumCookieConsentInit) {
        return;
    }
    window.__forumCookieConsentInit = true;

    const CONSENT_COOKIE_KEY = 'forum_cookie_consent_v2';
    const CONSENT_COOKIE_DAYS = 365;
    const USER_CONSENT_ENDPOINT = '/profile/cookie-consent';
    const AUTHENTICATED = document.querySelector('meta[name="forum-authenticated"]')?.getAttribute('content') === '1';
    const getActivePolicyVersion = () => {
        const node = document.querySelector('[data-cookie-policy-version]');
        if (!(node instanceof HTMLElement)) {
            return '';
        }

        return (node.dataset.cookiePolicyVersion || '').trim();
    };

    const getCsrfToken = () => {
        const token = document.querySelector('meta[name="csrf-token"]');
        return token instanceof HTMLMetaElement ? token.getAttribute('content') || '' : '';
    };

    const parseCookieConsent = () => {
        const all = document.cookie ? document.cookie.split(';') : [];
        const prefix = `${CONSENT_COOKIE_KEY}=`;
        const matches = all.filter((item) => item.trim().startsWith(prefix));
        const match = matches.length > 0 ? matches[matches.length - 1] : null;
        if (!match) {
            return null;
        }

        try {
            const raw = decodeURIComponent(match.trim().slice(prefix.length));
            const parsed = JSON.parse(raw);
            if (typeof parsed !== 'object' || parsed === null) {
                return null;
            }

            return parsed;
        } catch (error) {
            return null;
        }
    };

    const consentCookieDomains = () => {
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

    const clearCookieConsentVariants = () => {
        consentCookieDomains().forEach((domainPart) => {
            document.cookie = `${CONSENT_COOKIE_KEY}=; Max-Age=0; Path=/; SameSite=Lax${domainPart}`;
        });
    };

    const writeCookieConsent = (consent) => {
        clearCookieConsentVariants();
        const value = encodeURIComponent(JSON.stringify(consent));
        const maxAge = CONSENT_COOKIE_DAYS * 24 * 60 * 60;
        document.cookie = `${CONSENT_COOKIE_KEY}=${value}; Max-Age=${maxAge}; Path=/; SameSite=Lax`;
    };

    const fetchServerConsent = async () => {
        if (!AUTHENTICATED) {
            return null;
        }

        try {
            const response = await fetch(USER_CONSENT_ENDPOINT, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return null;
            }

            const payload = await response.json();
            if (typeof payload !== 'object' || payload === null) {
                return null;
            }

            return payload;
        } catch (error) {
            return null;
        }
    };

    const persistServerConsent = async (consent) => {
        if (!AUTHENTICATED) {
            return;
        }

        const csrfToken = getCsrfToken();
        if (csrfToken === '') {
            return;
        }

        try {
            await fetch(USER_CONSENT_ENDPOINT, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(consent),
            });
        } catch (error) {
            // Ignore network errors: local cookie persistence still succeeds.
        }
    };

    const getCookieGroupCheckboxes = () => {
        const nodes = document.querySelectorAll('[data-cookie-group-checkbox]');
        const checkboxes = [];
        nodes.forEach((node) => {
            if (node instanceof HTMLInputElement) {
                checkboxes.push(node);
            }
        });

        return checkboxes;
    };

    const getCookieCheckboxes = () => {
        const nodes = document.querySelectorAll('[data-cookie-checkbox]');
        const checkboxes = [];
        nodes.forEach((node) => {
            if (node instanceof HTMLInputElement) {
                checkboxes.push(node);
            }
        });

        return checkboxes;
    };

    const getCookieDefaults = () => {
        const defaults = { groups: {}, cookies: {} };
        getCookieGroupCheckboxes().forEach((checkbox) => {
            const key = (checkbox.dataset.cookieGroupKey || '').trim();
            if (key === '') {
                return;
            }

            const required = checkbox.dataset.cookieRequired === '1' || checkbox.disabled;
            const defaultEnabled = checkbox.dataset.cookieDefaultEnabled === '1';
            defaults.groups[key] = required || defaultEnabled;
        });

        getCookieCheckboxes().forEach((checkbox) => {
            const key = (checkbox.dataset.cookieKey || '').trim();
            if (key === '') {
                return;
            }

            const required = checkbox.dataset.cookieRequired === '1' || checkbox.disabled;
            const defaultEnabled = checkbox.dataset.cookieDefaultEnabled === '1';
            defaults.cookies[key] = required || defaultEnabled;
        });

        return defaults;
    };

    const mapCookieKeysByGroup = () => {
        const mapping = {};
        getCookieCheckboxes().forEach((checkbox) => {
            const key = (checkbox.dataset.cookieKey || '').trim();
            const groupKey = (checkbox.dataset.cookieGroupKey || '').trim();
            if (key === '' || groupKey === '') {
                return;
            }

            if (!Array.isArray(mapping[groupKey])) {
                mapping[groupKey] = [];
            }

            mapping[groupKey].push(key);
        });

        return mapping;
    };

    const mapCookieToGroup = () => {
        const mapping = {};
        getCookieCheckboxes().forEach((checkbox) => {
            const key = (checkbox.dataset.cookieKey || '').trim();
            const groupKey = (checkbox.dataset.cookieGroupKey || '').trim();
            if (key === '' || groupKey === '') {
                return;
            }

            mapping[key] = groupKey;
        });

        return mapping;
    };

    const normalizeConsent = (consent) => {
        const defaults = getCookieDefaults();
        const cookieToGroup = mapCookieToGroup();
        const cookieKeysByGroup = mapCookieKeysByGroup();
        const incomingGroups = (consent && typeof consent.groups === 'object' && consent.groups !== null)
            ? consent.groups
            : {};
        const incomingCookies = (consent && typeof consent.cookies === 'object' && consent.cookies !== null)
            ? consent.cookies
            : {};

        if (typeof consent?.analytics === 'boolean') {
            incomingGroups.analytics = consent.analytics;
        }
        if (typeof consent?.marketing === 'boolean') {
            incomingGroups.marketing = consent.marketing;
        }

        const cookies = {};
        Object.entries(defaults.cookies).forEach(([cookieKey, defaultValue]) => {
            const groupKey = cookieToGroup[cookieKey] || '';
            if (groupKey === '') {
                cookies[cookieKey] = defaultValue === true;
                return;
            }

            const groupRequired = defaults.groups[groupKey] === true && getCookieGroupCheckboxes().some((checkbox) => {
                return (checkbox.dataset.cookieGroupKey || '').trim() === groupKey
                    && (checkbox.dataset.cookieRequired === '1' || checkbox.disabled);
            });

            if (groupRequired) {
                cookies[cookieKey] = true;
                return;
            }

            if (typeof incomingCookies[cookieKey] === 'boolean') {
                cookies[cookieKey] = incomingCookies[cookieKey] === true;
                return;
            }

            if (typeof incomingGroups[groupKey] === 'boolean') {
                cookies[cookieKey] = incomingGroups[groupKey] === true;
                return;
            }

            cookies[cookieKey] = defaultValue === true;
        });

        const groups = {};
        Object.entries(defaults.groups).forEach(([key, defaultValue]) => {
            const groupCheckbox = getCookieGroupCheckboxes().find((checkbox) => (checkbox.dataset.cookieGroupKey || '').trim() === key);
            const required = groupCheckbox
                ? groupCheckbox.dataset.cookieRequired === '1' || groupCheckbox.disabled
                : false;

            if (required) {
                groups[key] = true;
                return;
            }

            const groupCookieKeys = cookieKeysByGroup[key] || [];
            if (groupCookieKeys.length > 0) {
                groups[key] = groupCookieKeys.some((cookieKey) => cookies[cookieKey] === true);
                return;
            }

            groups[key] = incomingGroups[key] === true ? true : defaultValue === true;
        });

        return {
            necessary: true,
            groups,
            cookies,
            policyVersion: typeof consent?.policyVersion === 'string' ? consent.policyVersion : null,
            updatedAt: typeof consent?.updatedAt === 'string' ? consent.updatedAt : null,
        };
    };

    const consentMatchesActivePolicy = (consent) => {
        const activePolicyVersion = getActivePolicyVersion();
        if (activePolicyVersion === '') {
            return true;
        }

        const storedPolicyVersion = typeof consent?.policyVersion === 'string'
            ? consent.policyVersion.trim()
            : '';

        return storedPolicyVersion !== '' && storedPolicyVersion === activePolicyVersion;
    };

    const activateDeferredScripts = (category) => {
        const selector = `script[data-consent-category="${category}"]:not([data-consent-activated="1"])`;
        document.querySelectorAll(selector).forEach((node) => {
            if (!(node instanceof HTMLScriptElement)) {
                return;
            }

            const script = document.createElement('script');
            Array.from(node.attributes).forEach((attr) => {
                const name = attr.name;
                const value = attr.value;
                if (name === 'type' || name === 'data-consent-category' || name === 'data-consent-activated') {
                    return;
                }
                if (name.startsWith('data-script-attr-')) {
                    const realName = name.replace('data-script-attr-', '');
                    script.setAttribute(realName, value);
                    return;
                }
                if (name === 'data-src') {
                    script.src = value;
                    return;
                }
                if (!name.startsWith('data-')) {
                    script.setAttribute(name, value);
                }
            });

            if (node.src === '' && !script.src) {
                script.textContent = node.textContent;
            }

            node.dataset.consentActivated = '1';
            node.replaceWith(script);
        });
    };

    const applyConsentState = (consent) => {
        const normalized = normalizeConsent(consent);

        window.forumConsent = normalized;
        const groupCheckboxes = getCookieGroupCheckboxes();
        const cookieCheckboxes = getCookieCheckboxes();
        const cookieKeysByGroup = mapCookieKeysByGroup();

        cookieCheckboxes.forEach((checkbox) => {
            const key = (checkbox.dataset.cookieKey || '').trim();
            if (key === '') {
                return;
            }

            const required = checkbox.dataset.cookieRequired === '1' || checkbox.disabled;
            checkbox.checked = required || normalized.cookies[key] === true;
        });

        groupCheckboxes.forEach((checkbox) => {
            const key = (checkbox.dataset.cookieGroupKey || '').trim();
            if (key === '') {
                return;
            }

            const required = checkbox.dataset.cookieRequired === '1' || checkbox.disabled;
            const groupCookieKeys = cookieKeysByGroup[key] || [];
            const groupCookieEnabled = groupCookieKeys.some((cookieKey) => normalized.cookies[cookieKey] === true);
            checkbox.checked = required || groupCookieEnabled || normalized.groups[key] === true;

            const attrKey = key.toLowerCase().replace(/[^a-z0-9]+/g, '-');
            document.documentElement.setAttribute(`data-cookie-group-${attrKey}`, checkbox.checked ? '1' : '0');

            if (checkbox.checked) {
                activateDeferredScripts(key);
            }
        });

        document.documentElement.setAttribute('data-cookie-analytics', normalized.groups.analytics === true ? '1' : '0');
        document.documentElement.setAttribute('data-cookie-marketing', normalized.groups.marketing === true ? '1' : '0');

        document.dispatchEvent(new CustomEvent('forum:consent-updated', { detail: normalized }));
    };

    const getCookieUi = () => {
        const getLast = (selector) => {
            const nodes = document.querySelectorAll(selector);
            if (nodes.length === 0) {
                return null;
            }

            return nodes[nodes.length - 1];
        };

        const banner = getLast('[data-cookie-banner]');
        const modal = getLast('[data-cookie-modal]');
        const fab = getLast('[data-cookie-open-settings]');
        const reconsentNotices = Array.from(document.querySelectorAll('[data-cookie-reconsent-notice]'))
            .filter((node) => node instanceof HTMLElement);
        return {
            banner: banner instanceof HTMLElement ? banner : null,
            modal: modal instanceof HTMLElement ? modal : null,
            fab: fab instanceof HTMLElement ? fab : null,
            reconsentNotices,
        };
    };

    const setReconsentNoticeVisibility = (visible) => {
        const ui = getCookieUi();
        ui.reconsentNotices.forEach((notice) => {
            notice.hidden = !visible;
        });
    };

    const openCookieModal = () => {
        const ui = getCookieUi();
        if (!ui.modal) {
            return;
        }

        const current = parseCookieConsent() || window.forumConsent;
        if (current) {
            applyConsentState(current);
        } else {
            applyConsentState({ necessary: true, ...getCookieDefaults(), updatedAt: null });
        }
        ui.modal.removeAttribute('hidden');
        ui.modal.hidden = false;
    };

    const closeCookieModal = () => {
        const ui = getCookieUi();
        if (!ui.modal) {
            return;
        }

        ui.modal.setAttribute('hidden', 'hidden');
        ui.modal.hidden = true;
    };

    const saveCookieConsent = (prefs) => {
        const normalizedPrefs = normalizeConsent({
            groups: prefs.groups || {},
            cookies: prefs.cookies || {},
            updatedAt: new Date().toISOString(),
        });
        const consent = {
            necessary: true,
            groups: normalizedPrefs.groups,
            cookies: normalizedPrefs.cookies,
            policyVersion: getActivePolicyVersion() || null,
            updatedAt: normalizedPrefs.updatedAt,
        };

        writeCookieConsent(consent);
        applyConsentState(consent);
        void persistServerConsent(consent);
        const ui = getCookieUi();
        if (ui.banner) {
            ui.banner.hidden = true;
        }
        if (ui.fab) {
            ui.fab.hidden = false;
        }
        closeCookieModal();
    };

    const readSelectedCookiePrefs = () => {
        const prefs = { groups: {}, cookies: {} };

        getCookieGroupCheckboxes().forEach((checkbox) => {
            const key = (checkbox.dataset.cookieGroupKey || '').trim();
            if (key === '') {
                return;
            }

            const required = checkbox.dataset.cookieRequired === '1' || checkbox.disabled;
            prefs.groups[key] = required || checkbox.checked;
        });

        getCookieCheckboxes().forEach((checkbox) => {
            const key = (checkbox.dataset.cookieKey || '').trim();
            if (key === '') {
                return;
            }

            const required = checkbox.dataset.cookieRequired === '1' || checkbox.disabled;
            prefs.cookies[key] = required || checkbox.checked;
        });

        return prefs;
    };

    const buildRejectCookiePrefs = () => {
        const prefs = { groups: {}, cookies: {} };
        getCookieGroupCheckboxes().forEach((checkbox) => {
            const key = (checkbox.dataset.cookieGroupKey || '').trim();
            if (key === '') {
                return;
            }

            const required = checkbox.dataset.cookieRequired === '1' || checkbox.disabled;
            prefs.groups[key] = required;
        });

        getCookieCheckboxes().forEach((checkbox) => {
            const key = (checkbox.dataset.cookieKey || '').trim();
            if (key === '') {
                return;
            }

            const required = checkbox.dataset.cookieRequired === '1' || checkbox.disabled;
            prefs.cookies[key] = required;
        });

        return prefs;
    };

    const buildAcceptAllCookiePrefs = () => {
        const prefs = { groups: {}, cookies: {} };
        getCookieGroupCheckboxes().forEach((checkbox) => {
            const key = (checkbox.dataset.cookieGroupKey || '').trim();
            if (key === '') {
                return;
            }
            prefs.groups[key] = true;
        });

        getCookieCheckboxes().forEach((checkbox) => {
            const key = (checkbox.dataset.cookieKey || '').trim();
            if (key === '') {
                return;
            }

            prefs.cookies[key] = true;
        });

        return prefs;
    };

    const syncGroupFromCookies = (groupKey) => {
        if (groupKey === '') {
            return;
        }

        const groupCheckbox = getCookieGroupCheckboxes().find((checkbox) => (checkbox.dataset.cookieGroupKey || '').trim() === groupKey);
        if (!groupCheckbox) {
            return;
        }

        const groupRequired = groupCheckbox.dataset.cookieRequired === '1' || groupCheckbox.disabled;
        if (groupRequired) {
            groupCheckbox.checked = true;
            return;
        }

        const cookieCheckboxes = getCookieCheckboxes().filter((checkbox) => (checkbox.dataset.cookieGroupKey || '').trim() === groupKey);
        if (cookieCheckboxes.length === 0) {
            return;
        }

        groupCheckbox.checked = cookieCheckboxes.some((checkbox) => checkbox.checked);
    };

    const bindCookieConsentGlobals = () => {
        if (document.documentElement.dataset.cookieConsentGlobalBound === '1') {
            return;
        }

        document.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element) || typeof target.closest !== 'function') {
                return;
            }

            if (target.closest('[data-cookie-accept-all]')) {
                event.preventDefault();
                saveCookieConsent(buildAcceptAllCookiePrefs());
                return;
            }

            if (target.closest('[data-cookie-reject]')) {
                event.preventDefault();
                saveCookieConsent(buildRejectCookiePrefs());
                return;
            }

            if (target.closest('[data-cookie-manage], [data-cookie-open-settings]')) {
                event.preventDefault();
                openCookieModal();
                return;
            }

            if (target.closest('[data-cookie-close]')) {
                event.preventDefault();
                closeCookieModal();
                return;
            }

            if (target.closest('[data-cookie-save]')) {
                event.preventDefault();
                saveCookieConsent(readSelectedCookiePrefs());
                return;
            }

            const ui = getCookieUi();
            if (ui && target === ui.modal) {
                closeCookieModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            const activeModal = document.querySelector('[data-cookie-modal]');
            if (activeModal instanceof HTMLElement && activeModal.hidden === false) {
                activeModal.hidden = true;
            }
        });

        document.addEventListener('change', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) {
                return;
            }

            if (target.matches('[data-cookie-group-checkbox]')) {
                const groupKey = (target.dataset.cookieGroupKey || '').trim();
                const required = target.dataset.cookieRequired === '1' || target.disabled;
                if (required) {
                    target.checked = true;
                    return;
                }

                getCookieCheckboxes()
                    .filter((checkbox) => (checkbox.dataset.cookieGroupKey || '').trim() === groupKey)
                    .forEach((checkbox) => {
                        const cookieRequired = checkbox.dataset.cookieRequired === '1' || checkbox.disabled;
                        checkbox.checked = cookieRequired || target.checked;
                    });
                return;
            }

            if (target.matches('[data-cookie-checkbox]')) {
                const groupKey = (target.dataset.cookieGroupKey || '').trim();
                syncGroupFromCookies(groupKey);
            }
        });

        document.documentElement.dataset.cookieConsentGlobalBound = '1';
    };

    const initCookieConsent = async () => {
        const ui = getCookieUi();
        if (!ui.banner || !ui.modal) {
            return;
        }

        ui.modal.setAttribute('hidden', 'hidden');
        ui.modal.hidden = true;

        window.forumCookieOpenModal = () => {
            openCookieModal();
            return false;
        };
        window.forumCookieCloseModal = () => {
            closeCookieModal();
            return false;
        };

        const existing = parseCookieConsent();
        if (existing && consentMatchesActivePolicy(existing)) {
            applyConsentState(existing);
            ui.banner.hidden = true;
            setReconsentNoticeVisibility(false);
        } else {
            if (existing) {
                clearCookieConsentVariants();
            }
            applyConsentState({ necessary: true, ...getCookieDefaults(), updatedAt: null });
            ui.banner.hidden = false;
            setReconsentNoticeVisibility(existing !== null);
        }

        if (ui.fab) {
            ui.fab.hidden = false;
        }

        const serverConsent = await fetchServerConsent();
        if (serverConsent && serverConsent.requiresReconsent !== true && typeof serverConsent.consent === 'object' && serverConsent.consent !== null) {
            const consent = {
                ...serverConsent.consent,
                policyVersion: typeof serverConsent.activePolicyVersion === 'string'
                    ? serverConsent.activePolicyVersion
                    : (typeof serverConsent.policyVersion === 'string' ? serverConsent.policyVersion : null),
            };

            writeCookieConsent(consent);
            applyConsentState(consent);
            ui.banner.hidden = true;
            setReconsentNoticeVisibility(false);
        } else if (serverConsent && serverConsent.requiresReconsent === true) {
            clearCookieConsentVariants();
            applyConsentState({ necessary: true, ...getCookieDefaults(), updatedAt: null });
            ui.banner.hidden = false;
            setReconsentNoticeVisibility(true);
        }

        closeCookieModal();
    };

    bindCookieConsentGlobals();
    void initCookieConsent();
})();
