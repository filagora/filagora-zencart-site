(() => {
    const dialog = document.querySelector('[data-relocate-dialog]');
    if (!(dialog instanceof HTMLElement)) {
        return;
    }

    const panel = dialog.querySelector('[data-relocate-panel]');
    const titleEl = dialog.querySelector('[data-relocate-title]');
    const contextEl = dialog.querySelector('[data-relocate-context]');
    const errorEl = dialog.querySelector('[data-relocate-error]');
    const destinationWrap = dialog.querySelector('[data-relocate-destination]');
    const destinationLabel = dialog.querySelector('[data-relocate-destination-label]');
    const searchInput = dialog.querySelector('[data-relocate-search]');
    const destinationId = dialog.querySelector('[data-relocate-destination-id]');
    const selectedEl = dialog.querySelector('[data-relocate-selected]');
    const recentEl = dialog.querySelector('[data-relocate-recent]');
    const resultsEl = dialog.querySelector('[data-relocate-results]');
    const hintEl = dialog.querySelector('[data-relocate-hint]');
    const titleFieldWrap = dialog.querySelector('[data-relocate-title-field]');
    const titleFieldLabel = dialog.querySelector('[data-relocate-title-label]');
    const newTitleInput = dialog.querySelector('[data-relocate-new-title]');
    const reasonWrap = dialog.querySelector('[data-relocate-reason-field]');
    const reasonInput = dialog.querySelector('[data-relocate-reason]');
    const confirmButton = dialog.querySelector('[data-relocate-confirm]');

    const threadId = dialog.dataset.threadId ?? '';
    const forumId = dialog.dataset.forumId ?? '';
    const threadTitle = dialog.dataset.threadTitle ?? '';
    const returnTo = dialog.dataset.returnTo ?? '';
    const undoUrl = dialog.dataset.undoUrl ?? '';

    const t = (key, replacements = {}) => {
        const table = window.forumRelocateStrings ?? {};
        let value = key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), table);
        if (typeof value !== 'string') {
            value = key;
        }
        Object.entries(replacements).forEach(([token, replacement]) => {
            value = value.replace(`:${token}`, String(replacement));
        });
        return value;
    };

    const csrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta instanceof HTMLMetaElement ? meta.getAttribute('content') ?? '' : '';
    };

    // ---- Mode definitions -------------------------------------------------

    // destination: false | 'thread' | 'forum'
    const MODES = {
        'thread-move': {
            destination: 'forum', title: false, reason: true, transport: 'json',
            heading: 'dialog.thread_move_title', destinationLabel: 'dialog.destination_forum_label',
            confirm: 'dialog.confirm_move_thread', searchPlaceholder: 'dialog.search_forum_placeholder',
            // ModerateThreadRequest validates the destination as `forum_id`.
            action: 'move', field: 'forum_id',
        },
        'thread-rename': {
            destination: false, title: true, reason: false, transport: 'native',
            heading: 'dialog.thread_rename_title', titleLabel: 'dialog.rename_label',
            confirm: 'dialog.confirm_rename', titleFrom: 'current',
        },
        'thread-bug': {
            destination: false, title: false, reason: true, transport: 'native',
            heading: 'dialog.confirm_report_bug', reasonLabel: 'dialog.bug_label',
            confirm: 'dialog.confirm_report_bug', reasonTarget: 'description',
        },
        'post-move': {
            destination: 'thread', title: false, reason: true, transport: 'json',
            heading: 'dialog.post_move_title', destinationLabel: 'dialog.destination_thread_label',
            confirm: 'dialog.confirm_move_post', searchPlaceholder: 'dialog.search_placeholder',
            action: 'move', field: 'destination_thread_id',
        },
        'post-promote': {
            destination: 'forum', title: true, reason: true, transport: 'json',
            heading: 'dialog.post_promote_title', destinationLabel: 'dialog.destination_forum_label',
            titleLabel: 'dialog.title_label', confirm: 'dialog.confirm_promote',
            searchPlaceholder: 'dialog.search_forum_placeholder',
            action: 'promote', field: 'destination_forum_id', titleFrom: 're',
        },
        'post-delete': {
            destination: false, title: false, reason: true, transport: 'native',
            heading: 'dialog.confirm_delete_post', reasonLabel: 'dialog.reason_label',
            confirm: 'dialog.confirm_delete_post', reasonRequired: true, reasonTarget: 'reason',
        },
        'bulk-move': {
            destination: 'thread', title: false, reason: true, transport: 'json', bulk: true,
            heading: 'select.move', destinationLabel: 'dialog.destination_thread_label',
            confirm: 'dialog.confirm_bulk_move', searchPlaceholder: 'dialog.search_placeholder',
            action: 'move', field: 'destination_thread_id',
        },
        'bulk-promote': {
            destination: 'forum', title: true, reason: true, transport: 'json', bulk: true,
            heading: 'select.promote', destinationLabel: 'dialog.destination_forum_label',
            titleLabel: 'dialog.title_label', confirm: 'dialog.confirm_bulk_promote',
            searchPlaceholder: 'dialog.search_forum_placeholder',
            action: 'promote', field: 'destination_forum_id', titleFrom: 're',
        },
        'bulk-delete': {
            destination: false, title: false, reason: true, transport: 'json', bulk: true,
            heading: 'select.delete', reasonLabel: 'dialog.reason_label',
            confirm: 'dialog.confirm_bulk_delete', action: 'delete',
        },
    };

    let state = null;
    let launcher = null;
    let searchTimer = null;
    let searchAbort = null;
    let activeRow = -1;

    // ---- Dialog open / close -------------------------------------------------

    const showError = (message) => {
        if (errorEl instanceof HTMLElement) {
            errorEl.textContent = message;
            errorEl.hidden = false;
        }
    };

    const clearError = () => {
        if (errorEl instanceof HTMLElement) {
            errorEl.hidden = true;
            errorEl.textContent = '';
        }
    };

    const hideResults = () => {
        if (resultsEl instanceof HTMLElement) {
            resultsEl.hidden = true;
            resultsEl.replaceChildren();
        }
        activeRow = -1;
        if (searchInput instanceof HTMLElement) {
            searchInput.setAttribute('aria-expanded', 'false');
        }
    };

    const closeDialog = () => {
        dialog.hidden = true;
        document.body.style.overflow = '';
        hideResults();
        state = null;
        if (launcher instanceof HTMLElement) {
            launcher.focus();
        }
    };

    const openDialog = (mode, trigger, extra = {}) => {
        const config = MODES[mode];
        if (!config) {
            return;
        }

        // Thread context can come from the opener (a row in a thread list) or
        // fall back to the dialog's own dataset (the single-thread page).
        state = {
            mode,
            config,
            form: extra.form ?? null,
            endpoint: extra.endpoint ?? '',
            postIds: extra.postIds ?? [],
            threadId: extra.threadId || threadId,
            forumId: extra.forumId || forumId,
            threadTitle: extra.threadTitle || threadTitle,
            forumName: extra.forumName || (dialog.dataset.forumName ?? ''),
            threadModerateUrl: extra.threadModerateUrl || (dialog.dataset.threadModerateUrl ?? ''),
        };
        launcher = trigger;
        clearError();

        if (titleEl instanceof HTMLElement) {
            titleEl.textContent = t(config.heading);
        }
        if (contextEl instanceof HTMLElement) {
            contextEl.textContent = extra.context ?? '';
            contextEl.hidden = !extra.context;
        }

        // Destination field
        const hasDestination = Boolean(config.destination);
        if (destinationWrap instanceof HTMLElement) {
            destinationWrap.hidden = !hasDestination;
        }
        if (hasDestination) {
            if (destinationLabel instanceof HTMLElement) {
                destinationLabel.textContent = t(config.destinationLabel);
            }
            if (searchInput instanceof HTMLInputElement) {
                searchInput.value = '';
                searchInput.placeholder = t(config.searchPlaceholder ?? 'dialog.search_placeholder');
            }
            if (destinationId instanceof HTMLInputElement) {
                destinationId.value = '';
            }
            if (selectedEl instanceof HTMLElement) {
                selectedEl.hidden = true;
                selectedEl.textContent = '';
            }
            if (hintEl instanceof HTMLElement) {
                hintEl.hidden = true;
            }
            hideResults();
            loadRecents();
        }

        // Title field
        if (titleFieldWrap instanceof HTMLElement) {
            titleFieldWrap.hidden = !config.title;
        }
        if (config.title) {
            if (titleFieldLabel instanceof HTMLElement) {
                titleFieldLabel.textContent = t(config.titleLabel ?? 'dialog.title_label');
            }
            if (newTitleInput instanceof HTMLInputElement) {
                if (config.titleFrom === 'current') {
                    newTitleInput.value = state.threadTitle;
                } else if (config.titleFrom === 're') {
                    newTitleInput.value = `Re: ${state.threadTitle}`;
                } else {
                    newTitleInput.value = '';
                }
            }
        }

        // Reason field
        if (reasonWrap instanceof HTMLElement) {
            reasonWrap.hidden = !config.reason;
        }
        if (config.reason) {
            const reasonLabelEl = dialog.querySelector('[data-relocate-reason-label]');
            if (reasonLabelEl instanceof HTMLElement && reasonLabelEl.firstChild) {
                reasonLabelEl.firstChild.textContent = `${t(config.reasonLabel ?? 'dialog.reason_label')} `;
            }
            const optionalEl = dialog.querySelector('[data-relocate-reason-optional]');
            if (optionalEl instanceof HTMLElement) {
                optionalEl.hidden = Boolean(config.reasonRequired);
            }
            if (reasonInput instanceof HTMLTextAreaElement) {
                reasonInput.value = '';
            }
        }

        if (confirmButton instanceof HTMLElement) {
            confirmButton.textContent = t(config.confirm, { count: state.postIds.length });
        }

        dialog.hidden = false;
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => {
            if (hasDestination && searchInput instanceof HTMLElement) {
                searchInput.focus();
            } else if (config.title && newTitleInput instanceof HTMLElement) {
                newTitleInput.focus();
                if (newTitleInput instanceof HTMLInputElement) {
                    newTitleInput.select();
                }
            } else if (config.reason && reasonInput instanceof HTMLElement) {
                reasonInput.focus();
            } else if (confirmButton instanceof HTMLElement) {
                confirmButton.focus();
            }
        });
    };

    // ---- Destination search ----------------------------------------------

    const searchUrlFor = (config) => (config.destination === 'forum'
        ? dialog.dataset.forumSearchUrl ?? ''
        : dialog.dataset.threadSearchUrl ?? '');

    const searchParams = (config, query) => {
        const params = new URLSearchParams({ q: query });
        if (config.destination === 'thread') {
            params.set('source_forum_id', (state && state.forumId) || forumId);
            if (state && state.mode === 'post-move' && state.form instanceof HTMLElement) {
                params.set('exclude_post_id', state.form.dataset.postId ?? '');
            } else {
                params.set('exclude_thread_id', (state && state.threadId) || threadId);
            }
        }
        return params;
    };

    const chooseRow = (row) => {
        if (destinationId instanceof HTMLInputElement) {
            destinationId.value = String(row.id ?? '');
        }
        if (searchInput instanceof HTMLInputElement) {
            searchInput.value = String(row.title ?? '');
        }
        if (selectedEl instanceof HTMLElement) {
            const path = row.forum
                ? `${row.forum.name}${row.forum.group_name ? ` › ${row.forum.group_name}` : ''}`
                : (row.group_name ?? '');
            selectedEl.textContent = `✓ ${row.title}${path ? ` — ${path}` : ''}`;
            selectedEl.hidden = false;
        }
        hideResults();
        clearError();
    };

    const renderRows = (rows, { asChips = false } = {}) => {
        if (!(resultsEl instanceof HTMLElement) || !(recentEl instanceof HTMLElement)) {
            return;
        }

        if (asChips) {
            recentEl.querySelectorAll('[data-relocate-chip]').forEach((node) => node.remove());
            if (!Array.isArray(rows) || rows.length === 0) {
                recentEl.hidden = true;
                return;
            }
            rows.forEach((row) => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.dataset.relocateChip = '';
                chip.className = 'inline-flex items-center rounded-full border border-[#cabca8] bg-white px-2.5 py-1 text-xs font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f]';
                chip.textContent = String(row.title ?? '');
                chip.addEventListener('click', () => chooseRow(row));
                recentEl.appendChild(chip);
            });
            recentEl.hidden = false;
            return;
        }

        if (!Array.isArray(rows) || rows.length === 0) {
            resultsEl.replaceChildren();
            resultsEl.hidden = true;
            const li = document.createElement('li');
            li.className = 'px-3 py-2 text-sm text-[#5c6570]';
            li.textContent = t(state && state.config.action === 'promote' ? 'dialog.no_results_promote' : 'dialog.no_results');
            resultsEl.appendChild(li);
            resultsEl.hidden = false;
            return;
        }

        const items = rows.map((row) => {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'grid w-full gap-0.5 px-3 py-2 text-left text-[#0f2133] transition hover:bg-[#f7e8df] focus:bg-[#f7e8df] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[#8f3f1f]';

            const line1 = document.createElement('span');
            line1.className = 'flex flex-wrap items-center gap-1.5';
            const strong = document.createElement('strong');
            strong.className = 'font-semibold';
            strong.textContent = String(row.title ?? '');
            line1.appendChild(strong);
            if (row.is_locked) {
                line1.appendChild(pill(t('dialog.locked_badge')));
            }
            if (row.moderation_status && row.moderation_status !== 'approved') {
                line1.appendChild(pill(t('dialog.pending_badge')));
            }
            button.appendChild(line1);

            const metaBits = [];
            if (row.forum) {
                metaBits.push(`${row.forum.name}${row.forum.group_name ? ` › ${row.forum.group_name}` : ''}`);
            } else if (row.group_name) {
                metaBits.push(row.group_name);
            }
            if (typeof row.reply_count === 'number') {
                metaBits.push(`${row.reply_count} ${row.reply_count === 1 ? 'reply' : 'replies'}`);
            }
            if (row.last_activity_human) {
                metaBits.push(String(row.last_activity_human));
            }
            if (metaBits.length > 0) {
                const meta = document.createElement('small');
                meta.className = 'text-[#5c6570]';
                meta.textContent = metaBits.join(' · ');
                button.appendChild(meta);
            }

            if (typeof row.snippet_html === 'string' && row.snippet_html !== '') {
                const snippet = document.createElement('small');
                snippet.className = 'text-[#5c6570] [&_mark]:bg-[#f7e8df] [&_mark]:text-[#8f3f1f]';
                snippet.innerHTML = row.snippet_html;
                button.appendChild(snippet);
            }

            button.addEventListener('click', () => chooseRow(row));
            li.appendChild(button);
            return li;
        });

        resultsEl.replaceChildren(...items);
        resultsEl.hidden = false;
        activeRow = -1;
        if (searchInput instanceof HTMLElement) {
            searchInput.setAttribute('aria-expanded', 'true');
        }
    };

    const pill = (text) => {
        const span = document.createElement('span');
        span.className = 'inline-flex items-center rounded-full border border-[#933c1f] bg-[#f7e8df] px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.03em] text-[#933c1f]';
        span.textContent = text;
        return span;
    };

    const runSearch = async (query) => {
        if (!state || !state.config.destination) {
            return;
        }
        if (searchAbort) {
            searchAbort.abort();
        }
        searchAbort = new AbortController();
        try {
            const url = `${searchUrlFor(state.config)}?${searchParams(state.config, query).toString()}`;
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: searchAbort.signal });
            if (!response.ok) {
                hideResults();
                return;
            }
            const payload = await response.json();
            renderRows(Array.isArray(payload?.data) ? payload.data : []);
            if (hintEl instanceof HTMLElement) {
                hintEl.hidden = !(resultsEl instanceof HTMLElement) || resultsEl.hidden;
            }
        } catch (error) {
            if (error instanceof Error && error.name !== 'AbortError') {
                hideResults();
            }
        }
    };

    async function loadRecents() {
        if (!state || !state.config.destination) {
            return;
        }
        try {
            const response = await fetch(`${searchUrlFor(state.config)}?q=`, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                return;
            }
            const payload = await response.json();
            renderRows(Array.isArray(payload?.data) ? payload.data : [], { asChips: true });
        } catch (error) {
            // recents are a nicety; ignore failures
        }
    }

    if (searchInput instanceof HTMLInputElement) {
        searchInput.addEventListener('input', () => {
            if (destinationId instanceof HTMLInputElement) {
                destinationId.value = '';
            }
            if (selectedEl instanceof HTMLElement) {
                selectedEl.hidden = true;
            }
            if (searchTimer !== null) {
                clearTimeout(searchTimer);
            }
            const query = searchInput.value.trim();
            searchTimer = window.setTimeout(() => {
                if (query === '') {
                    hideResults();
                    loadRecents();
                    return;
                }
                runSearch(query);
            }, 180);
        });

        searchInput.addEventListener('keydown', (event) => {
            const rows = resultsEl instanceof HTMLElement ? Array.from(resultsEl.querySelectorAll('button')) : [];
            if (event.key === 'ArrowDown' && rows.length > 0) {
                event.preventDefault();
                activeRow = Math.min(activeRow + 1, rows.length - 1);
                rows[activeRow].focus();
            } else if (event.key === 'ArrowUp' && rows.length > 0) {
                event.preventDefault();
                activeRow = Math.max(activeRow - 1, 0);
                rows[activeRow].focus();
            } else if (event.key === 'Escape' && resultsEl instanceof HTMLElement && !resultsEl.hidden) {
                event.preventDefault();
                event.stopPropagation();
                hideResults();
            }
        });
    }

    if (resultsEl instanceof HTMLElement) {
        resultsEl.addEventListener('keydown', (event) => {
            const rows = Array.from(resultsEl.querySelectorAll('button'));
            const index = rows.indexOf(document.activeElement instanceof HTMLElement ? document.activeElement : null);
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (index < rows.length - 1) {
                    rows[index + 1].focus();
                }
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                if (index <= 0) {
                    if (searchInput instanceof HTMLElement) {
                        searchInput.focus();
                    }
                } else {
                    rows[index - 1].focus();
                }
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                hideResults();
                if (searchInput instanceof HTMLElement) {
                    searchInput.focus();
                }
            }
        });
    }

    // ---- Submit ---------------------------------------------------------

    const submitNative = () => {
        if (!state || !(state.form instanceof HTMLFormElement)) {
            return;
        }
        const config = state.config;
        if (config.title) {
            const hidden = state.form.querySelector('[data-thread-rename-title]');
            if (hidden instanceof HTMLInputElement && newTitleInput instanceof HTMLInputElement) {
                hidden.value = newTitleInput.value.trim();
            }
        }
        if (config.reason) {
            const targetName = config.reasonTarget ?? 'reason';
            const hidden = state.form.querySelector(`input[name="${targetName}"], [data-post-moderation-reason], [data-thread-moderation-reason]`);
            if (hidden instanceof HTMLInputElement && reasonInput instanceof HTMLTextAreaElement) {
                hidden.value = reasonInput.value.trim();
            }
        }
        const form = state.form;
        closeDialog();
        form.submit();
    };

    const submitJson = async () => {
        if (!state) {
            return;
        }
        const config = state.config;
        const body = new URLSearchParams();
        body.set('action', config.action);
        body.set('return_to', returnTo);
        if (config.reason && reasonInput instanceof HTMLTextAreaElement && reasonInput.value.trim() !== '') {
            body.set('reason', reasonInput.value.trim());
        }
        if (config.destination && destinationId instanceof HTMLInputElement) {
            body.set(config.field, destinationId.value);
        }
        if (config.title && newTitleInput instanceof HTMLInputElement) {
            body.set('new_thread_title', newTitleInput.value.trim());
        }
        if (config.bulk) {
            state.postIds.forEach((id) => body.append('post_ids[]', String(id)));
        }

        let endpoint = state.endpoint;
        if (config.bulk) {
            endpoint = dialog.dataset.bulkUrl ?? '';
        } else if (state.mode === 'thread-move') {
            endpoint = state.threadModerateUrl;
        }

        if (confirmButton instanceof HTMLButtonElement) {
            confirmButton.disabled = true;
        }

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: body.toString(),
            });
            const payload = await response.json().catch(() => ({}));
            const message = typeof payload?.message === 'string' ? payload.message : '';

            if (response.status === 429) {
                showError(t('toast.rate_limited'));
                return;
            }
            if (!response.ok || response.redirected || message === '') {
                showError(message || t('toast.undo_failed'));
                return;
            }

            const toast = {
                message,
                destination_url: typeof payload?.destination_url === 'string' ? payload.destination_url : '',
                undo_token: typeof payload?.undo_token === 'string' ? payload.undo_token : '',
            };

            const mode = state.mode;
            const removeIds = state.config.bulk
                ? state.postIds.slice()
                : (state.form instanceof HTMLElement && state.form.dataset.postId ? [state.form.dataset.postId] : []);

            closeDialog();

            if (mode.startsWith('thread-') || payload?.navigate_away === true) {
                // Either the whole thread moved, or this move emptied and
                // removed the thread we're looking at — leave the stale page.
                stashToast(toast);
                window.location.assign(toast.destination_url || window.location.href);
                return;
            }

            removeIds.forEach((id) => {
                const article = document.getElementById(`post-${id}`);
                if (article instanceof HTMLElement) {
                    article.remove();
                }
            });
            if (selectRoot instanceof HTMLElement && selectRoot.dataset.selecting === 'on') {
                setSelecting(false);
            }
            buildToast(toast);
        } catch (error) {
            showError(t('toast.undo_failed'));
        } finally {
            if (confirmButton instanceof HTMLButtonElement) {
                confirmButton.disabled = false;
            }
        }
    };

    const confirm = () => {
        if (!state) {
            return;
        }
        clearError();
        const config = state.config;

        if (config.destination) {
            const chosen = destinationId instanceof HTMLInputElement ? destinationId.value.trim() : '';
            if (chosen === '') {
                showError(t('dialog.err_no_destination'));
                if (searchInput instanceof HTMLElement) {
                    searchInput.focus();
                }
                return;
            }
        }
        if (config.title) {
            const value = newTitleInput instanceof HTMLInputElement ? newTitleInput.value.trim() : '';
            if (value.length < 3) {
                showError(t('dialog.err_title_short'));
                if (newTitleInput instanceof HTMLElement) {
                    newTitleInput.focus();
                }
                return;
            }
        }
        if (config.reasonRequired) {
            const value = reasonInput instanceof HTMLTextAreaElement ? reasonInput.value.trim() : '';
            if (value === '') {
                showError(t('dialog.err_reason_required'));
                if (reasonInput instanceof HTMLElement) {
                    reasonInput.focus();
                }
                return;
            }
        }

        if (config.transport === 'native') {
            submitNative();
        } else {
            submitJson();
        }
    };

    // ---- Toast ---------------------------------------------------------

    const stashToast = (data) => {
        try {
            sessionStorage.setItem('relocate:toast', JSON.stringify(data));
        } catch (error) {
            // storage unavailable — a cross-navigation toast just won't carry
        }
    };

    const dismissToast = (toast) => {
        if (!(toast instanceof HTMLElement) || toast.dataset.leaving === '1') {
            return;
        }
        toast.dataset.leaving = '1';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(-50%) translateY(-10px)';
        window.setTimeout(() => toast.remove(), 220);
    };

    // Built in JS after the CSS is compiled, so it can't lean on utility
    // classes — everything is inline. Mirrors the app's green flash banner
    // (session('thread_status')): #f1fbf2 / #b9d6bd / #1f5130, sharp corners.
    const buildToast = (data, variant = 'success') => {
        if (!data || typeof data.message !== 'string' || data.message === '') {
            return;
        }

        document.querySelectorAll('[data-relocate-toast]').forEach((node) => node.remove());

        const palette = variant === 'error'
            ? { bg: '#fff1f2', border: '#f1b6bc', ink: '#9f2230' }
            : { bg: '#f1fbf2', border: '#b9d6bd', ink: '#1f5130' };

        const toast = document.createElement('div');
        toast.dataset.relocateToast = '';
        toast.setAttribute('role', 'status');
        toast.style.cssText = [
            'position:fixed', 'left:50%', 'top:20px', 'transform:translateX(-50%) translateY(-10px)',
            'z-index:2147483000', 'display:flex', 'align-items:center', 'gap:14px',
            'min-width:320px', 'max-width:min(92vw,560px)', 'box-sizing:border-box',
            `background:${palette.bg}`, `border:1px solid ${palette.border}`, `color:${palette.ink}`,
            'padding:12px 14px', 'font:600 14px/1.45 "Open Sans",ui-sans-serif,system-ui,sans-serif',
            'box-shadow:0 22px 50px rgba(12,10,8,0.28)', 'opacity:0',
            'transition:opacity .18s ease, transform .18s ease',
        ].join(';');

        const text = document.createElement('span');
        text.textContent = data.message;
        text.style.cssText = 'flex:1 1 auto;font-weight:600';
        toast.appendChild(text);

        const actions = document.createElement('span');
        actions.style.cssText = `display:flex;align-items:center;gap:12px;padding-left:12px;border-left:1px solid ${palette.border}`;
        const linkStyle = `font:inherit;font-weight:700;color:${palette.ink};background:none;border:0;padding:0;cursor:pointer;text-decoration:underline;text-underline-offset:2px`;

        if (typeof data.destination_url === 'string' && data.destination_url !== '') {
            const view = document.createElement('a');
            view.href = data.destination_url;
            view.style.cssText = linkStyle;
            view.textContent = t('toast.view');
            actions.appendChild(view);
        }

        if (typeof data.undo_token === 'string' && data.undo_token !== '') {
            const undo = document.createElement('button');
            undo.type = 'button';
            undo.style.cssText = linkStyle;
            undo.textContent = t('toast.undo');
            undo.addEventListener('click', async () => {
                undo.disabled = true;
                undo.style.opacity = '0.5';
                try {
                    const response = await fetch(undoUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                        body: new URLSearchParams({ token: data.undo_token }).toString(),
                    });
                    if (response.status === 410) {
                        // Token gone (expired or already used) — Undo is spent.
                        text.textContent = t('undo_expired');
                        undo.remove();
                        return;
                    }
                    if (response.status === 429) {
                        // Rate limited — leave the button disabled briefly.
                        text.textContent = t('toast.rate_limited');
                        window.setTimeout(() => {
                            undo.disabled = false;
                            undo.style.opacity = '';
                        }, 5000);
                        return;
                    }
                    if (!response.ok) {
                        throw new Error('undo failed');
                    }
                    stashToast({ message: t('toast.undone'), destination_url: '', undo_token: '' });
                    window.location.reload();
                } catch (error) {
                    // Keep the toast + its Undo button so the moderator can retry.
                    text.textContent = t('toast.undo_failed');
                    undo.disabled = false;
                    undo.style.opacity = '';
                }
            });
            actions.appendChild(undo);
        }

        if (actions.childElementCount > 0) {
            toast.appendChild(actions);
        }

        const dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.setAttribute('aria-label', 'Dismiss');
        dismiss.textContent = '✕';
        dismiss.style.cssText = `font:inherit;font-weight:400;color:${palette.ink};opacity:0.55;background:none;border:0;padding:0 0 0 2px;cursor:pointer;line-height:1`;
        dismiss.addEventListener('click', () => dismissToast(toast));
        toast.appendChild(dismiss);

        document.body.appendChild(toast);
        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(-50%) translateY(0)';
        });
        window.setTimeout(() => dismissToast(toast), variant === 'error' ? 8000 : 12000);
    };

    const renderToast = () => {
        try {
            const raw = sessionStorage.getItem('relocate:toast');
            if (!raw) {
                return;
            }
            sessionStorage.removeItem('relocate:toast');
            buildToast(JSON.parse(raw));
        } catch (error) {
            // no stashed toast to show
        }
    };

    // ---- Wiring --------------------------------------------------------

    const contextForPost = (form) => {
        const number = form.dataset.postNumber ?? form.dataset.postId ?? '';
        return number !== '' ? t('dialog.context_post', { number, author: form.dataset.postAuthor ?? '', thread: threadTitle }) : '';
    };

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) {
            return;
        }

        const opener = target.closest('[data-relocate-open]');
        if (opener instanceof HTMLElement) {
            event.preventDefault();
            const mode = opener.dataset.relocateOpen ?? '';
            const config = MODES[mode];
            if (!config) {
                return;
            }
            const menu = opener.closest('details[open]');
            if (menu instanceof HTMLDetailsElement) {
                menu.open = false;
            }
            // A row in a thread list carries its own thread context on the opener.
            const threadCtx = {
                threadId: opener.dataset.threadId ?? '',
                forumId: opener.dataset.forumId ?? '',
                threadTitle: opener.dataset.threadTitle ?? '',
                forumName: opener.dataset.forumName ?? '',
                threadModerateUrl: opener.dataset.threadModerateUrl ?? '',
            };
            if (config.bulk) {
                const ids = selectedPostIds();
                if (ids.length === 0) {
                    return;
                }
                openDialog(mode, opener, { ...threadCtx, postIds: ids, context: t('dialog.context_bulk', { count: ids.length }) });
            } else {
                openDialog(mode, opener, {
                    ...threadCtx,
                    context: mode === 'thread-move'
                        ? t('dialog.context_thread', { forum: threadCtx.forumName || (dialog.dataset.forumName ?? '') })
                        : '',
                });
            }
            return;
        }

        if (target.closest('[data-relocate-close]')) {
            event.preventDefault();
            closeDialog();
            return;
        }

        if (target === confirmButton || target.closest('[data-relocate-confirm]')) {
            event.preventDefault();
            confirm();
            return;
        }

        const chip = target.closest('[data-relocate-reason-chip]');
        if (chip instanceof HTMLElement && reasonInput instanceof HTMLTextAreaElement) {
            event.preventDefault();
            reasonInput.value = reasonInput.value.trim() === '' ? chip.textContent ?? '' : `${reasonInput.value.trim()} ${chip.textContent ?? ''}`.trim();
            reasonInput.focus();
        }
    });

    // Intercept the field-taking forms so they run through the dialog.
    document.addEventListener('submit', (event) => {
        const form = event.target instanceof Element ? event.target.closest('form') : null;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const actionInput = form.querySelector('input[name="action"]');
        const action = actionInput instanceof HTMLInputElement ? actionInput.value : '';

        if (form.hasAttribute('data-thread-moderation-form') && (action === 'rename' || action === 'report_bug')) {
            event.preventDefault();
            openDialog(action === 'rename' ? 'thread-rename' : 'thread-bug', form.querySelector('button[type="submit"]'), {
                form,
                threadTitle: form.dataset.threadRenameTitleDefault || threadTitle,
                context: t('dialog.context_thread', { forum: form.dataset.forumName || (dialog.dataset.forumName ?? '') }),
            });
            return;
        }

        if (form.hasAttribute('data-post-move-form')) {
            event.preventDefault();
            openDialog('post-move', form.querySelector('button[type="submit"]'), { form, endpoint: form.getAttribute('action') ?? '', context: contextForPost(form) });
            return;
        }

        if (form.hasAttribute('data-post-promote-form')) {
            event.preventDefault();
            openDialog('post-promote', form.querySelector('button[type="submit"]'), { form, endpoint: form.getAttribute('action') ?? '', context: contextForPost(form) });
            return;
        }

        if (form.hasAttribute('data-post-moderation-form') && action === 'delete') {
            event.preventDefault();
            openDialog('post-delete', form.querySelector('button[type="submit"]'), { form, context: contextForPost(form) });
        }
    });

    document.addEventListener('keydown', (event) => {
        if (dialog.hidden || !(panel instanceof HTMLElement)) {
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
        const focusable = Array.from(panel.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
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

    // ---- Select-posts mode ------------------------------------------------

    const selectRoot = document.querySelector('[data-select-root]');
    const selectBar = document.querySelector('[data-select-bar]');
    const selectCount = document.querySelector('[data-select-count]');

    const selectedPostIds = () => Array.from(document.querySelectorAll('[data-post-checkbox]:checked'))
        .map((node) => (node instanceof HTMLInputElement ? node.value : ''))
        .filter((value) => value !== '');

    const refreshSelectBar = () => {
        const ids = selectedPostIds();
        if (selectCount instanceof HTMLElement) {
            selectCount.textContent = t('select.count', { count: ids.length });
        }
        if (selectBar instanceof HTMLElement) {
            selectBar.querySelectorAll('[data-relocate-open]').forEach((node) => {
                if (node instanceof HTMLButtonElement) {
                    node.disabled = ids.length === 0;
                }
            });
        }
    };

    const setSelecting = (on) => {
        if (!(selectRoot instanceof HTMLElement)) {
            return;
        }
        selectRoot.dataset.selecting = on ? 'on' : '';
        document.querySelectorAll('[data-post-select]').forEach((node) => {
            if (node instanceof HTMLElement) {
                node.hidden = !on;
            }
        });
        if (selectBar instanceof HTMLElement) {
            selectBar.hidden = !on;
        }
        if (!on) {
            document.querySelectorAll('[data-post-checkbox]:checked').forEach((node) => {
                if (node instanceof HTMLInputElement) {
                    node.checked = false;
                }
            });
        }
        refreshSelectBar();
    };

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) {
            return;
        }
        if (target.closest('[data-select-posts-toggle]')) {
            event.preventDefault();
            const menu = target.closest('details[open]');
            if (menu instanceof HTMLDetailsElement) {
                menu.open = false;
            }
            setSelecting(!(selectRoot instanceof HTMLElement && selectRoot.dataset.selecting === 'on'));
            return;
        }
        if (target.closest('[data-select-clear]')) {
            event.preventDefault();
            setSelecting(false);
        }
    });

    document.addEventListener('change', (event) => {
        if (event.target instanceof HTMLInputElement && event.target.hasAttribute('data-post-checkbox')) {
            refreshSelectBar();
        }
    });

    // Native <details> menus don't close on an outside click — do it here.
    const MENU_SELECTOR = 'details[data-mod-menu], details[data-post-menu]';
    document.addEventListener('click', (event) => {
        const clicked = event.target instanceof Element ? event.target.closest(MENU_SELECTOR) : null;
        document.querySelectorAll(MENU_SELECTOR).forEach((node) => {
            if (node instanceof HTMLDetailsElement && node.open && node !== clicked) {
                node.open = false;
            }
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }
        document.querySelectorAll(MENU_SELECTOR).forEach((node) => {
            if (node instanceof HTMLDetailsElement && node.open) {
                node.open = false;
            }
        });
    });

    renderToast();
})();
