// "Forum Support Thread" picker on the plugin submit form.
//
// Three modes, chosen by the support_thread_mode radios:
//   existing - typeahead against plugins.support-threads.search, writes the
//              chosen thread id into the hidden forum_thread_id input
//   url      - a free-text support_thread_url field
//   create   - the support_thread_forum_id <select>
// Loaded via app.js on [data-support-thread-field].

(() => {
    const field = document.querySelector('[data-support-thread-field]');
    if (!(field instanceof HTMLElement)) {
        return;
    }

    const modeInputs = Array.from(field.querySelectorAll('[data-support-thread-mode]'));
    const existingWrap = field.querySelector('[data-support-thread-existing]');
    const urlWrap = field.querySelector('[data-support-thread-url]');
    const newWrap = field.querySelector('[data-support-thread-new]');

    const searchInput = field.querySelector('[data-support-thread-search]');
    const results = field.querySelector('[data-support-thread-results]');
    const hiddenId = field.querySelector('[data-support-thread-id]');
    const chosen = field.querySelector('[data-support-thread-chosen]');
    const chosenTitle = field.querySelector('[data-support-thread-chosen-title]');
    const urlInput = urlWrap ? urlWrap.querySelector('input') : null;
    const newSelect = newWrap ? newWrap.querySelector('select') : null;
    const titleInput = field.querySelector('[data-support-thread-title-input]');
    const nameInput = field.closest('form')?.querySelector('[name="name"]') ?? null;

    const searchUrl = field.dataset.supportThreadSearchUrl || '';

    // Keep the new-thread title mirroring the plugin name until the author
    // types their own, then leave it alone.
    if (titleInput instanceof HTMLInputElement && nameInput instanceof HTMLInputElement) {
        let titleDirty = titleInput.value.trim() !== '';

        titleInput.addEventListener('input', () => {
            titleDirty = titleInput.value.trim() !== '';
        });

        const mirrorName = () => {
            if (!titleDirty) {
                titleInput.value = nameInput.value;
            }
        };

        nameInput.addEventListener('input', mirrorName);
        mirrorName();
    }

    const currentMode = () => {
        const checked = modeInputs.find((input) => input.checked);

        return checked instanceof HTMLInputElement ? checked.value : 'existing';
    };

    const syncMode = () => {
        const mode = currentMode();

        if (existingWrap instanceof HTMLElement) {
            existingWrap.hidden = mode !== 'existing';
        }
        if (urlWrap instanceof HTMLElement) {
            urlWrap.hidden = mode !== 'url';
        }
        if (newWrap instanceof HTMLElement) {
            newWrap.hidden = mode !== 'create';
        }
        if (urlInput instanceof HTMLInputElement) {
            urlInput.disabled = mode !== 'url';
        }
        if (newSelect instanceof HTMLSelectElement) {
            newSelect.disabled = mode !== 'create';
        }
        if (titleInput instanceof HTMLInputElement) {
            titleInput.disabled = mode !== 'create';
        }
    };

    modeInputs.forEach((input) => input.addEventListener('change', syncMode));
    syncMode();

    // --- typeahead -----------------------------------------------------------
    if (
        !(searchInput instanceof HTMLInputElement)
        || !(results instanceof HTMLElement)
        || !(hiddenId instanceof HTMLInputElement)
        || searchUrl === ''
    ) {
        return;
    }

    let abortController = null;
    let debounceTimer = null;

    const hideResults = () => {
        results.hidden = true;
        results.replaceChildren();
    };

    const setChosen = (id, title) => {
        hiddenId.value = id;
        if (chosenTitle instanceof HTMLElement) {
            chosenTitle.textContent = title;
        }
        if (chosen instanceof HTMLElement) {
            chosen.hidden = id === '';
        }
        searchInput.value = '';
        hideResults();
    };

    const renderResults = (rows) => {
        if (!Array.isArray(rows) || rows.length === 0) {
            results.replaceChildren(
                Object.assign(document.createElement('p'), {
                    className: 'px-2.5 py-2 text-sm text-[#5c6570]',
                    textContent: 'No matching support threads.',
                }),
            );
            results.hidden = false;

            return;
        }

        results.replaceChildren(...rows.map((row) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'grid w-full gap-0.5 px-2.5 py-2 text-left text-[#0f2133] transition hover:bg-[#f7e8df] focus:bg-[#f7e8df] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[#8f3f1f]';
            button.dataset.supportThreadOption = '';
            button.dataset.threadId = String(row.id ?? '');

            const strong = document.createElement('strong');
            strong.className = 'font-semibold';
            strong.textContent = String(row.title ?? '');

            const small = document.createElement('small');
            small.className = 'text-[#5c6570]';
            small.textContent = String(row.forum ?? '');

            button.append(strong, small);

            return button;
        }));
        results.hidden = false;
    };

    const runSearch = async () => {
        const query = searchInput.value.trim();

        if (abortController) {
            abortController.abort();
        }
        abortController = new AbortController();

        try {
            const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                signal: abortController.signal,
            });
            if (!response.ok) {
                hideResults();

                return;
            }
            const payload = await response.json();
            renderResults(Array.isArray(payload?.data) ? payload.data : []);
        } catch (error) {
            if (!(error instanceof DOMException && error.name === 'AbortError')) {
                hideResults();
            }
        }
    };

    searchInput.addEventListener('input', () => {
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(runSearch, 180);
    });

    searchInput.addEventListener('focus', () => {
        if (searchInput.value.trim() === '') {
            runSearch();
        }
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hideResults();
        }
    });

    results.addEventListener('click', (event) => {
        const option = event.target instanceof Element
            ? event.target.closest('[data-support-thread-option]')
            : null;
        if (!(option instanceof HTMLButtonElement)) {
            return;
        }
        event.preventDefault();
        setChosen(option.dataset.threadId || '', option.querySelector('strong')?.textContent || '');
    });

    field.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest('[data-support-thread-clear]')) {
            event.preventDefault();
            setChosen('', '');
        }
    });

    document.addEventListener('click', (event) => {
        const inside = event.target instanceof Node
            && (results.contains(event.target) || searchInput.contains(event.target));
        if (!inside) {
            hideResults();
        }
    });
})();
