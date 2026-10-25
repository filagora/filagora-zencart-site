// Forum shortcut-row search (forum/partials/search_bar.blade.php).
//
// The form works without this: the scope is then a short native select of
// the contextual choices. Here that select is hidden and a pill takes its
// place, opening a popover with the same choices plus "Find a forum"
// type-ahead over every forum the viewer can search (fetched from
// data-forum-options-url on first open).
// The select stays the value the form submits; picking another forum adds
// it as an option. Scope changes also swap the scope-specific filters,
// relabel "My threads only" / "My posts only" and update the placeholder.

const escapeHtml = (value) => String(value).replace(/[&<>"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;',
})[character]);

document.querySelectorAll('[data-forum-search-bar]').forEach((form) => {
    const scope = form.querySelector('[data-forum-search-scope]');
    const input = form.querySelector('input[name="q"]');
    if (!(scope instanceof HTMLSelectElement)) {
        return;
    }

    const syncFields = () => {
        const kind = scope.value.split(':')[0];
        form.querySelectorAll('[data-forum-search-forum-only]').forEach((field) => {
            field.hidden = kind === 'thread';
        });
        form.querySelectorAll('[data-forum-search-thread-only]').forEach((field) => {
            field.hidden = kind !== 'thread';
        });
        form.querySelectorAll('[data-forum-search-mine-label]').forEach((label) => {
            label.textContent = kind === 'thread' ? label.dataset.labelThread : label.dataset.labelOther;
        });
        if (input instanceof HTMLInputElement) {
            const template = input.dataset[`placeholder${kind.charAt(0).toUpperCase()}${kind.slice(1)}`] || '';
            const forumName = scope.selectedOptions[0]?.dataset.forumName || '';
            input.placeholder = template.replace('__FORUM__', forumName) || input.placeholder;
        }
    };

    scope.addEventListener('change', syncFields);
    syncFields();

    const pill = form.querySelector('[data-forum-search-pill]');
    const pillLabel = form.querySelector('[data-forum-search-pill-label]');
    const popover = form.querySelector('[data-forum-search-popover]');
    const choices = form.querySelector('[data-forum-search-choices]');
    const find = form.querySelector('[data-forum-search-find]');
    const results = form.querySelector('[data-forum-search-results]');
    const noMatch = form.querySelector('[data-forum-search-no-match]');
    if (!(pill instanceof HTMLButtonElement) || !(popover instanceof HTMLElement) || !(find instanceof HTMLInputElement) || !(results instanceof HTMLElement)) {
        return;
    }

    const loading = form.querySelector('[data-forum-search-loading]');

    const loadError = form.querySelector('[data-forum-search-load-error]');

    // The forum list is fetched the first time the popover opens and kept
    // once it arrives. A failed fetch is not kept: the popover says so and
    // the next open tries again.
    let forums = [];
    let forumsLoaded = false;
    let forumsRequest = null;
    const loadForums = () => {
        if (forumsLoaded) {
            return Promise.resolve(true);
        }
        forumsRequest ??= fetch(form.dataset.forumOptionsUrl || '', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then((payload) => {
                forums = Array.isArray(payload?.data) ? payload.data : [];
                forumsLoaded = true;
                return true;
            })
            .catch(() => false)
            .finally(() => {
                forumsRequest = null;
            });

        return forumsRequest;
    };

    const contextualCount = scope.options.length;
    let activeIndex = -1;

    const setPillLabel = () => {
        if (pillLabel instanceof HTMLElement) {
            pillLabel.textContent = scope.selectedOptions[0]?.textContent?.trim() || '';
        }
    };

    function close() {
        popover.hidden = true;
        pill.setAttribute('aria-expanded', 'false');
    }

    const choose = (value, label, forumName) => {
        let option = Array.from(scope.options).find((candidate) => candidate.value === value);
        if (!option) {
            // Drop an earlier non-contextual pick so the select stays short.
            Array.from(scope.options).slice(contextualCount).forEach((extra) => extra.remove());
            option = new Option(label, value);
            option.dataset.forumName = forumName;
            scope.add(option);
        }
        scope.value = value;
        scope.dispatchEvent(new Event('change', { bubbles: true }));
        setPillLabel();
        close();
        pill.focus();
    };

    const renderChoices = () => {
        if (!(choices instanceof HTMLElement)) {
            return;
        }
        choices.innerHTML = Array.from(scope.options).slice(0, contextualCount).map((option) => {
            const current = option.value === scope.value;
            return `<button type="button" class="flex items-center justify-between gap-2 rounded-control px-2 py-1.5 text-left text-sm font-semibold text-[#304458] hover:bg-[#faf8f4] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-value="${escapeHtml(option.value)}" aria-pressed="${current}">`
                + `<span>${escapeHtml(option.textContent.trim())}</span>${current ? '<span aria-hidden="true" class="text-[#8f3f1f]">✓</span>' : ''}</button>`;
        }).join('');
    };

    const renderResults = () => {
        const term = find.value.trim().toLowerCase();
        const matches = forums.filter((forum) => term === ''
            || forum.name.toLowerCase().includes(term)
            || forum.group.toLowerCase().includes(term));
        let group = null;
        results.innerHTML = matches.map((forum, index) => {
            const heading = forum.group !== group
                ? `<li role="presentation" class="px-2 pb-0.5 pt-2 text-[10px] font-bold uppercase tracking-[0.06em] text-[#5c6570]">${escapeHtml(forum.group)}</li>`
                : '';
            group = forum.group;
            return `${heading}<li role="option" id="forum-search-option-${index}" aria-selected="false" data-value="forum:${Number(forum.id)}" data-name="${escapeHtml(forum.name)}" class="cursor-pointer rounded-control px-2 py-1 text-sm text-[#0f2133] hover:bg-[#f7e8df] hover:text-[#8f3f1f] aria-selected:bg-[#f7e8df] aria-selected:text-[#8f3f1f]">${escapeHtml(forum.name)}</li>`;
        }).join('');
        if (noMatch instanceof HTMLElement) {
            noMatch.classList.toggle('hidden', matches.length > 0 || !forumsLoaded);
        }
        activeIndex = -1;
        find.removeAttribute('aria-activedescendant');
    };

    const options = () => Array.from(results.querySelectorAll('[role="option"]'));

    const setActive = (index) => {
        const list = options();
        if (list.length === 0) {
            return;
        }
        activeIndex = (index + list.length) % list.length;
        list.forEach((option, position) => option.setAttribute('aria-selected', String(position === activeIndex)));
        find.setAttribute('aria-activedescendant', list[activeIndex].id);
        list[activeIndex].scrollIntoView({ block: 'nearest' });
    };

    const open = () => {
        renderChoices();
        find.value = '';
        renderResults();
        popover.hidden = false;
        pill.setAttribute('aria-expanded', 'true');
        find.focus();

        // renderResults() above already reflects the forum list when it was
        // loaded by an earlier open(); only the first open (or a retry after
        // a failed fetch) needs the loading state and a second render.
        if (forumsLoaded) {
            return;
        }

        if (loading instanceof HTMLElement) {
            loading.classList.remove('hidden');
        }
        if (loadError instanceof HTMLElement) {
            loadError.classList.add('hidden');
        }
        loadForums().then((ok) => {
            if (loading instanceof HTMLElement) {
                loading.classList.add('hidden');
            }
            if (!ok && loadError instanceof HTMLElement) {
                loadError.classList.remove('hidden');
            }
            renderResults();
        });
    };

    scope.hidden = true;
    pill.hidden = false;
    setPillLabel();

    pill.addEventListener('click', () => (popover.hidden ? open() : close()));

    popover.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const choice = target?.closest('[data-value]');
        if (!(choice instanceof HTMLElement)) {
            return;
        }
        if (choice.getAttribute('role') === 'option') {
            choose(choice.dataset.value, choice.dataset.name, choice.dataset.name);
        } else {
            choose(choice.dataset.value, '', '');
        }
    });

    find.addEventListener('input', renderResults);
    find.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive(activeIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(activeIndex - 1);
        } else if (event.key === 'Enter') {
            // Enter picks a forum here; it must never submit the search form.
            event.preventDefault();
            const option = options()[Math.max(activeIndex, 0)];
            if (option instanceof HTMLElement) {
                choose(option.dataset.value, option.dataset.name, option.dataset.name);
            }
        }
    });

    popover.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            pill.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (!popover.hidden && event.target instanceof Node && !popover.contains(event.target) && !pill.contains(event.target)) {
            close();
        }
    });
});
