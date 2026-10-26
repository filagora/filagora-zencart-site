// Confirmation dialog for permanently deleting a thread
// (forum/partials/thread_delete_dialog.blade.php). Each opener button carries
// its thread's moderate URL and pre-rendered copy in data-*; this fills the
// one dialog on the page from whichever opener was clicked.

const dialog = document.querySelector('[data-thread-delete-dialog]');
const panel = dialog?.querySelector('[data-thread-delete-panel]');

if (dialog instanceof HTMLElement && panel instanceof HTMLFormElement) {
    const contextEl = panel.querySelector('[data-thread-delete-context]');
    const summaryEl = panel.querySelector('[data-thread-delete-summary]');
    const returnToInput = panel.querySelector('[data-thread-delete-return-to]');
    const reasonInput = panel.querySelector('#thread-delete-reason');
    const banWrap = panel.querySelector('[data-thread-delete-ban]');
    const banCheckbox = panel.querySelector('[data-thread-delete-ban-checkbox]');
    const banLabel = panel.querySelector('[data-thread-delete-ban-label]');
    const banReason = panel.querySelector('[data-thread-delete-ban-reason]');

    let launcher = null;

    const syncBanReason = () => {
        if (banReason instanceof HTMLInputElement && banCheckbox instanceof HTMLInputElement) {
            banReason.disabled = !banCheckbox.checked;
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
        panel.action = trigger.dataset.action || '';
        if (contextEl instanceof HTMLElement) {
            contextEl.textContent = trigger.dataset.context || '';
        }
        if (summaryEl instanceof HTMLElement) {
            summaryEl.textContent = trigger.dataset.summary || '';
        }
        if (returnToInput instanceof HTMLInputElement) {
            returnToInput.value = trigger.dataset.returnTo || '';
        }
        if (reasonInput instanceof HTMLTextAreaElement) {
            reasonInput.value = '';
        }

        // No ban option for a missing author or for the moderator's own thread;
        // otherwise it starts unticked on every open.
        const banText = trigger.dataset.banLabel || '';
        if (banWrap instanceof HTMLElement) {
            banWrap.hidden = banText === '';
        }
        if (banLabel instanceof HTMLElement) {
            banLabel.textContent = banText;
        }
        if (banCheckbox instanceof HTMLInputElement) {
            banCheckbox.checked = false;
            banCheckbox.disabled = banText === '';
        }
        if (banReason instanceof HTMLInputElement) {
            banReason.value = '';
        }
        syncBanReason();

        dialog.hidden = false;
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => {
            if (reasonInput instanceof HTMLTextAreaElement) {
                reasonInput.focus();
            }
        });
    };

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const opener = target?.closest('[data-thread-delete-open]');
        if (opener instanceof HTMLElement) {
            event.preventDefault();
            openDialog(opener);
            return;
        }
        if (!dialog.hidden && target?.closest('[data-thread-delete-close]')) {
            event.preventDefault();
            closeDialog();
        }
    });

    banCheckbox?.addEventListener('change', syncBanReason);

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
        const focusable = Array.from(panel.querySelectorAll('button:not([disabled]), input:not([disabled]):not([type="hidden"]), textarea:not([disabled]), [href]'))
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
}
