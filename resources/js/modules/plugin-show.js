(() => {
    const reportModal = document.getElementById('report-plugin-modal');
    const reportDialog = reportModal?.querySelector('[data-report-dialog]');
    const reportForm = document.getElementById('report-plugin-modal-form');
    const reportReleaseId = document.getElementById('report-plugin-modal-release-id');
    const reportReason = document.getElementById('report-plugin-modal-reason');
    const reportDescription = document.getElementById('report-plugin-modal-description');
    const reportError = document.getElementById('report-plugin-modal-error');

    let reportLauncher = null;

    const getFocusable = (container) => {
        const nodes = container.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');

        return Array.from(nodes).filter((node) => node instanceof HTMLElement && !node.hasAttribute('hidden'));
    };

    const closeReportModal = () => {
        if (!(reportModal instanceof HTMLElement)) {
            return;
        }

        reportModal.hidden = true;
        document.body.style.overflow = '';
        if (reportError instanceof HTMLElement) {
            reportError.hidden = true;
            reportError.textContent = '';
        }
        if (reportReason instanceof HTMLTextAreaElement) {
            reportReason.value = '';
        }
        if (reportReleaseId instanceof HTMLInputElement) {
            reportReleaseId.value = '';
        }
        if (reportLauncher instanceof HTMLElement) {
            reportLauncher.focus();
        }
        reportLauncher = null;
    };

    document.addEventListener('click', (event) => {
        const openTrigger = event.target instanceof Element
            ? event.target.closest('[data-report-open]')
            : null;
        if (openTrigger instanceof HTMLElement) {
            event.preventDefault();
            if (!(reportModal instanceof HTMLElement) || !(reportForm instanceof HTMLFormElement)) {
                return;
            }

            reportLauncher = openTrigger;
            const releaseId = openTrigger.dataset.reportReleaseId || '';
            if (reportReleaseId instanceof HTMLInputElement) {
                reportReleaseId.value = releaseId;
            }
            if (reportDescription instanceof HTMLElement) {
                const releaseVersion = openTrigger.dataset.reportReleaseVersion || '';
                reportDescription.textContent = releaseVersion !== ''
                    ? `Tell staff why release ${releaseVersion} should be reviewed.`
                    : 'Tell staff why this plugin should be reviewed.';
            }
            reportModal.hidden = false;
            document.body.style.overflow = 'hidden';
            requestAnimationFrame(() => {
                if (reportReason instanceof HTMLTextAreaElement) {
                    reportReason.focus();
                }
            });
            return;
        }

        const closeTrigger = event.target instanceof Element
            ? event.target.closest('[data-report-close]')
            : null;
        if (closeTrigger) {
            event.preventDefault();
            closeReportModal();
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target instanceof Element ? event.target.closest('form') : null;
        if (!(form instanceof HTMLFormElement) || form !== reportForm) {
            return;
        }

        const value = reportReason instanceof HTMLTextAreaElement
            ? reportReason.value.trim()
            : '';
        if (value !== '') {
            return;
        }

        event.preventDefault();
        if (reportError instanceof HTMLElement) {
            reportError.hidden = false;
            reportError.textContent = 'Reason is required.';
        }
        if (reportReason instanceof HTMLTextAreaElement) {
            reportReason.focus();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!(reportModal instanceof HTMLElement) || reportModal.hidden || !(reportDialog instanceof HTMLElement)) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeReportModal();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = getFocusable(reportDialog);
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
    });
})();

(() => {
    const CLAMP_CLASS = 'plugin-description-clamp';

    const setUp = (expander) => {
        const body = expander.querySelector('[data-description-body]');
        const toggle = expander.querySelector('[data-description-toggle]');
        if (!(body instanceof HTMLElement) || !(toggle instanceof HTMLButtonElement)) {
            return null;
        }

        let expanded = false;

        const overflows = () => body.scrollHeight - body.clientHeight > 1;

        const applyExpanded = () => {
            body.classList.remove(CLAMP_CLASS);
            toggle.textContent = 'Show less';
            toggle.setAttribute('aria-expanded', 'true');
        };

        const applyCollapsed = () => {
            body.classList.add(CLAMP_CLASS);
            toggle.textContent = 'Show more';
            toggle.setAttribute('aria-expanded', 'false');
        };

        // Only surface the toggle when the text actually spills past the
        // four-line clamp. Without JS the button stays hidden and the full
        // text renders unclamped.
        const evaluate = () => {
            if (expanded) {
                return;
            }

            body.classList.add(CLAMP_CLASS);
            if (overflows()) {
                toggle.hidden = false;
            } else {
                body.classList.remove(CLAMP_CLASS);
                toggle.hidden = true;
            }
        };

        toggle.addEventListener('click', () => {
            expanded = !expanded;
            if (expanded) {
                applyExpanded();
            } else {
                applyCollapsed();
            }
        });

        return evaluate;
    };

    const evaluators = Array.from(document.querySelectorAll('[data-description-expander]'))
        .map(setUp)
        .filter((evaluate) => typeof evaluate === 'function');

    if (evaluators.length === 0) {
        return;
    }

    const evaluateAll = () => evaluators.forEach((evaluate) => evaluate());

    evaluateAll();

    if (document.fonts && typeof document.fonts.ready?.then === 'function') {
        document.fonts.ready.then(evaluateAll);
    }

    let resizeRaf = 0;
    window.addEventListener('resize', () => {
        if (resizeRaf) {
            return;
        }
        resizeRaf = requestAnimationFrame(() => {
            resizeRaf = 0;
            evaluateAll();
        });
    });
})();

(() => {
    const buttons = document.querySelectorAll('[data-copy-plugin-id]');

    buttons.forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        const originalText = button.textContent;
        const status = button.parentElement?.querySelector('[data-copy-plugin-id-status]') ?? null;
        let resetTimer = 0;

        const setStatus = (message) => {
            if (status instanceof HTMLElement) {
                status.textContent = message;
            }
        };

        button.addEventListener('click', async () => {
            const value = button.getAttribute('data-copy-value')?.trim() ?? '';

            if (value === '') {
                return;
            }

            try {
                await navigator.clipboard.writeText(value);
                button.textContent = 'Copied';
                setStatus('Copied.');
            } catch (error) {
                button.textContent = 'Copy failed';
                setStatus('Copy failed — select the ID manually.');
            }

            window.clearTimeout(resetTimer);
            resetTimer = window.setTimeout(() => {
                button.textContent = originalText;
            }, 2000);
        });
    });
})();
