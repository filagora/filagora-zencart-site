(() => {
    const form = document.querySelector('form[data-pm-inbox]');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const selectAll = form.querySelector('[data-pm-select-all]');
    const countLabel = form.querySelector('[data-pm-count]');
    const clearButton = form.querySelector('[data-pm-clear]');
    const actionField = form.querySelector('[data-pm-action]');
    const folderSelect = form.querySelector('select[name="folder_id"]');
    const checkboxes = () => Array.from(form.querySelectorAll('[data-pm-checkbox]'));

    let lastToggledIndex = null;

    const selectedCount = () => checkboxes().filter((box) => box.checked).length;

    const syncToolbar = () => {
        const total = checkboxes().length;
        const selected = selectedCount();

        if (countLabel instanceof HTMLElement) {
            countLabel.textContent = selected > 0 ? `${selected} selected` : '';
            countLabel.hidden = selected === 0;
        }

        if (clearButton instanceof HTMLElement) {
            clearButton.hidden = selected === 0;
        }

        if (selectAll instanceof HTMLInputElement) {
            selectAll.checked = total > 0 && selected === total;
            selectAll.indeterminate = selected > 0 && selected < total;
        }
    };

    const setAll = (checked) => {
        checkboxes().forEach((box) => {
            box.checked = checked;
        });
        syncToolbar();
    };

    if (selectAll instanceof HTMLInputElement) {
        selectAll.addEventListener('change', () => setAll(selectAll.checked));
    }

    if (clearButton instanceof HTMLElement) {
        clearButton.addEventListener('click', () => setAll(false));
    }

    // Shift+click on a row checkbox selects the range since the last one toggled.
    // A checkbox's `checked` state is already updated by the time `click` fires.
    form.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || !target.hasAttribute('data-pm-checkbox')) {
            return;
        }

        const boxes = checkboxes();
        const index = boxes.indexOf(target);

        if (event.shiftKey && lastToggledIndex !== null && lastToggledIndex !== index) {
            const [from, to] = [lastToggledIndex, index].sort((a, b) => a - b);
            for (let i = from; i <= to; i += 1) {
                boxes[i].checked = target.checked;
            }
        }

        lastToggledIndex = index;
        syncToolbar();
    });

    form.addEventListener('change', (event) => {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.hasAttribute('data-pm-checkbox')) {
            syncToolbar();
        }
    });

    const confirmIfNeeded = (element) => {
        const message = element instanceof HTMLElement ? element.getAttribute('data-pm-confirm') : null;
        if (!message) {
            return true;
        }

        return window.confirm(message);
    };

    // Confirm-guard for destructive submit buttons and the Empty Trash form.
    form.addEventListener('submit', (event) => {
        const submitter = event.submitter;
        if (submitter instanceof HTMLElement && !confirmIfNeeded(submitter)) {
            event.preventDefault();
        }
    });

    document.querySelectorAll('form[data-pm-confirm-form]').forEach((confirmForm) => {
        confirmForm.addEventListener('submit', (event) => {
            if (!confirmIfNeeded(confirmForm)) {
                event.preventDefault();
            }
        });
    });

    // Per-row quick actions: operate on that one conversation only.
    form.addEventListener('click', (event) => {
        const trigger = event.target instanceof Element
            ? event.target.closest('[data-pm-row-action]')
            : null;
        if (!(trigger instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();

        if (!confirmIfNeeded(trigger)) {
            return;
        }

        const action = trigger.getAttribute('data-pm-row-action') || '';
        const conversationId = trigger.getAttribute('data-pm-id') || '';
        const folderId = trigger.getAttribute('data-pm-folder-id');
        if (action === '' || conversationId === '') {
            return;
        }

        setAll(false);
        const box = checkboxes().find((candidate) => candidate.value === conversationId);
        if (box) {
            box.checked = true;
        }

        if (actionField instanceof HTMLInputElement) {
            actionField.value = action;
        }

        if (folderId && folderSelect instanceof HTMLSelectElement) {
            folderSelect.value = folderId;
        }

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });

    // Close any open row menu when clicking elsewhere or pressing Escape.
    const closeRowMenus = (except) => {
        document.querySelectorAll('details[data-pm-row-menu][open]').forEach((menu) => {
            if (menu !== except) {
                menu.open = false;
            }
        });
    };

    document.addEventListener('click', (event) => {
        const openedMenu = event.target instanceof Element
            ? event.target.closest('details[data-pm-row-menu]')
            : null;
        closeRowMenus(openedMenu);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeRowMenus(null);
        }
    });

    syncToolbar();
})();
