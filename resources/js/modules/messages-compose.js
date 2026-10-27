    (() => {
        const splitTokens = (value) => {
            const parts = value.split(',');
            const active = parts.pop() ?? '';

            return {
                prefix: parts.join(','),
                active: active.trim(),
            };
        };

        const setupLookup = (inputId, lookupId) => {
            const input = document.getElementById(inputId);
            const lookup = document.getElementById(lookupId);
            if (!(input instanceof HTMLInputElement) || !(lookup instanceof HTMLElement)) {
                return;
            }

            let abortController = null;
            let debounceTimer = null;

            const hideLookup = () => {
                lookup.hidden = true;
                lookup.replaceChildren();
            };

            const replaceActiveToken = (username) => {
                const { prefix } = splitTokens(input.value);
                const normalizedPrefix = prefix.trim();
                input.value = normalizedPrefix === '' ? `${username}, ` : `${normalizedPrefix}, ${username}, `;
                input.focus();
                hideLookup();
            };

            const buildRecipientOption = (row) => {
                const username = String(row.username || '');
                const name = String(row.name || '');
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'grid w-full gap-0.5 px-2.5 py-2 text-left text-[#0f2133] transition hover:bg-[#f7e8df] focus:bg-[#f7e8df] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[#8f3f1f]';
                button.dataset.recipientOption = '';
                button.dataset.username = username;

                const strong = document.createElement('strong');
                strong.className = 'font-semibold';
                strong.textContent = username;

                const small = document.createElement('small');
                small.className = 'text-[#5c6570]';
                small.textContent = name !== '' ? name : username;

                button.append(strong, small);

                return button;
            };

            const renderLookup = (rows) => {
                if (!Array.isArray(rows) || rows.length === 0) {
                    hideLookup();
                    return;
                }

                lookup.replaceChildren(...rows.map((row) => buildRecipientOption(row)));
                lookup.hidden = false;
            };

            input.addEventListener('input', () => {
                if (debounceTimer !== null) {
                    clearTimeout(debounceTimer);
                }

                debounceTimer = setTimeout(async () => {
                    const { active } = splitTokens(input.value);
                    if (active.length < 2) {
                        hideLookup();
                        return;
                    }

                    if (abortController) {
                        abortController.abort();
                    }
                    abortController = new AbortController();

                    try {
                        const response = await fetch(`{{ route('messages.users.search') }}?q=${encodeURIComponent(active)}`, {
                            headers: { 'Accept': 'application/json' },
                            signal: abortController.signal,
                        });
                        if (!response.ok) {
                            hideLookup();
                            return;
                        }

                        const payload = await response.json();
                        renderLookup(Array.isArray(payload?.data) ? payload.data : []);
                    } catch (error) {
                        hideLookup();
                    }
                }, 180);
            });

            lookup.addEventListener('click', (event) => {
                const button = event.target instanceof Element
                    ? event.target.closest('[data-recipient-option]')
                    : null;
                if (!(button instanceof HTMLButtonElement)) {
                    return;
                }
                event.preventDefault();
                const username = button.dataset.username || '';
                if (username !== '') {
                    replaceActiveToken(username);
                }
            });

            document.addEventListener('click', (event) => {
                const clickedInside = event.target instanceof Node && (lookup.contains(event.target) || input.contains(event.target));
                if (!clickedInside) {
                    hideLookup();
                }
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    hideLookup();
                }
            });
        };

        setupLookup('messages-to-input', 'messages-recipient-lookup');
        // Markdown toolbar/smiley/preview and link-preserving paste are now
        // shared modules (markdown-toolbar.js, markdown-paste.js), loaded
        // via app.js on any page with a markdown toolbar.
    })();
