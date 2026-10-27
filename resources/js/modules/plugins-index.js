// Dynamically imported from app.js only once #moderation-queue is
// confirmed present (see docs/homepage-performance-fixes-plan.md, Stage
// 5), so the DOM is already ready - runs immediately instead of waiting
// for DOMContentLoaded, which may have already fired by the time an
// async import resolves.
(() => {
    const queue = document.getElementById('moderation-queue');
    if (!(queue instanceof HTMLElement)) {
        return;
    }

    const hasModerationError = queue.dataset.hasModerationError === '1';
    const keepQueueOpen = queue.dataset.keepQueueOpen === '1';

    if (!hasModerationError && !keepQueueOpen) {
        queue.removeAttribute('open');
    }
})();

// Confirmation dialog for permanently deleting a plugin or release
// (plugins/partials/delete_dialog.blade.php), filled from the opener's data-*.
(() => {
    const dialog = document.querySelector('[data-plugin-delete-dialog]');
    const panel = dialog?.querySelector('[data-plugin-delete-panel]');
    if (!(dialog instanceof HTMLElement) || !(panel instanceof HTMLFormElement)) {
        return;
    }

    const q = (selector) => panel.querySelector(selector);
    const setText = (selector, text) => {
        const el = q(selector);
        if (el instanceof HTMLElement) {
            el.textContent = text;
        }
    };
    const authorWrap = q('[data-plugin-delete-author]');
    const allBox = q('[data-plugin-delete-all]');
    const banBox = q('[data-plugin-delete-ban]');
    const banReason = q('[data-plugin-delete-ban-reason]');
    const reason = q('[data-plugin-delete-reason]');

    let launcher = null;

    const syncBanReason = () => {
        if (banReason instanceof HTMLInputElement && banBox instanceof HTMLInputElement) {
            banReason.disabled = !banBox.checked;
        }
    };

    const closeDialog = () => {
        dialog.hidden = true;
        document.body.style.overflow = '';
        if (launcher instanceof HTMLElement) {
            launcher.focus();
        }
        launcher = null;
    };

    const openDialog = (trigger) => {
        launcher = trigger;
        const d = trigger.dataset;
        panel.action = d.action || '';
        setText('[data-plugin-delete-title]', d.title || '');
        setText('[data-plugin-delete-context]', d.context || '');
        setText('[data-plugin-delete-summary]', d.summary || '');
        setText('[data-plugin-delete-all-label]', d.allLabel || '');
        setText('[data-plugin-delete-all-hint]', d.allHint || '');
        setText('[data-plugin-delete-ban-label]', d.banLabel || '');
        setText('[data-plugin-delete-confirm]', d.confirm || 'Delete');

        // No author options when there is no author or it is the moderator.
        const hasAuthor = (d.banLabel || '') !== '';
        if (authorWrap instanceof HTMLElement) {
            authorWrap.hidden = !hasAuthor;
        }
        [allBox, banBox].forEach((box) => {
            if (box instanceof HTMLInputElement) {
                box.checked = false;
                box.disabled = !hasAuthor;
            }
        });
        if (banReason instanceof HTMLInputElement) {
            banReason.value = '';
        }
        if (reason instanceof HTMLTextAreaElement) {
            reason.value = '';
        }
        syncBanReason();

        dialog.hidden = false;
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => {
            if (reason instanceof HTMLTextAreaElement) {
                reason.focus();
            }
        });
    };

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const opener = target?.closest('[data-plugin-delete-open]');
        if (opener instanceof HTMLElement) {
            event.preventDefault();
            openDialog(opener);
            return;
        }
        if (!dialog.hidden && target?.closest('[data-plugin-delete-close]')) {
            event.preventDefault();
            closeDialog();
        }
    });

    banBox?.addEventListener('change', syncBanReason);

    document.addEventListener('keydown', (event) => {
        if (dialog.hidden) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            closeDialog();
            return;
        }
        if (event.key !== 'Tab') {
            return;
        }
        const focusable = Array.from(panel.querySelectorAll('button:not([disabled]), input:not([disabled]):not([type="hidden"]), textarea:not([disabled])'))
            .filter((node) => node instanceof HTMLElement && node.offsetParent !== null);
        if (focusable.length === 0) {
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
})();

// Reject dialog (plugins/partials/reject_dialog.blade.php): filled from the
// opener's data-*, note required.
(() => {
    const dialog = document.querySelector('[data-plugin-reject-dialog]');
    const panel = dialog?.querySelector('[data-plugin-reject-panel]');
    const note = dialog?.querySelector('[data-plugin-reject-note]');
    if (!(dialog instanceof HTMLElement) || !(panel instanceof HTMLFormElement) || !(note instanceof HTMLTextAreaElement)) {
        return;
    }

    let launcher = null;

    const closeDialog = () => {
        dialog.hidden = true;
        document.body.style.overflow = '';
        if (launcher instanceof HTMLElement) {
            launcher.focus();
        }
        launcher = null;
    };

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const opener = target?.closest('[data-plugin-reject-open]');
        if (opener instanceof HTMLElement) {
            event.preventDefault();
            launcher = opener;
            panel.action = opener.dataset.action || '';
            panel.querySelector('[data-plugin-reject-title]').textContent = opener.dataset.title || '';
            panel.querySelector('[data-plugin-reject-context]').textContent = opener.dataset.context || '';
            note.value = '';
            dialog.hidden = false;
            document.body.style.overflow = 'hidden';
            requestAnimationFrame(() => note.focus());
            return;
        }
        if (!dialog.hidden && target?.closest('[data-plugin-reject-close]')) {
            event.preventDefault();
            closeDialog();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (dialog.hidden) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            closeDialog();
            return;
        }
        if (event.key !== 'Tab') {
            return;
        }
        const focusable = Array.from(panel.querySelectorAll('button:not([disabled]), textarea:not([disabled])'));
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
})();
