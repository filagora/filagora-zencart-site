(() => {
    // Shared markdown authoring toolbar: tag/list/url/image/video/email/
    // color/size/font/spoiler/hr buttons, smiley insertion, and the
    // "Preview" fetch. Every markdown textarea resolves its own target via
    // data-markdown-target / data-smiley-target / data-markdown-source, so
    // one listener here serves every editor on the page (new thread, edit
    // post, thread reply, message compose, message reply) without knowing
    // which specific textarea ids exist. Loaded via the [data-markdown-trigger]
    // gate in app.js, alongside markdown-paste.js.
    const getSelection = (textarea) => {
        const start = textarea.selectionStart ?? 0;
        const end = textarea.selectionEnd ?? start;

        return {
            start,
            end,
            selected: textarea.value.slice(start, end),
        };
    };

    const replaceSelection = (textarea, replacement, cursorOffset = replacement.length) => {
        const { start, end } = getSelection(textarea);
        const before = textarea.value.slice(0, start);
        const after = textarea.value.slice(end);
        textarea.value = `${before}${replacement}${after}`;
        const caret = start + cursorOffset;
        textarea.focus();
        textarea.setSelectionRange(caret, caret);
    };

    const wrapSelection = (textarea, open, close) => {
        const { selected } = getSelection(textarea);
        if (selected !== '') {
            replaceSelection(textarea, `${open}${selected}${close}`);
            return;
        }

        const insertion = `${open}${close}`;
        replaceSelection(textarea, insertion, open.length);
    };

    const getCsrfToken = () => {
        const token = document.querySelector('meta[name="csrf-token"]');
        return token instanceof HTMLMetaElement ? token.getAttribute('content') || '' : '';
    };

    const promptWithModal = ({ title, placeholder = '', defaultValue = '', inputType = 'text' }) => new Promise((resolve) => {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-[1200] flex items-center justify-center p-4';
        modal.innerHTML = `
            <div class="absolute inset-0 bg-[rgba(12,10,8,0.58)]" data-modal-cancel></div>
            <div class="relative z-[1] w-full max-w-md rounded-[14px] border border-[#cabca8] bg-[#ececed] p-4 shadow-[0_30px_60px_rgba(0,0,0,0.32)]">
                <h2 class="text-lg font-semibold text-[#0f2133]">${title}</h2>
                <input type="${inputType}" value="${defaultValue.replaceAll('"', '&quot;')}" placeholder="${placeholder}" class="mt-3 w-full rounded-lg border border-[#cabca8] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-modal-input>
                <div class="mt-4 flex items-center justify-end gap-2">
                    <button type="button" class="inline-flex items-center rounded-lg border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133]" data-modal-cancel>Cancel</button>
                    <button type="button" class="inline-flex items-center rounded-lg border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white" data-modal-confirm>Insert</button>
                </div>
            </div>`;

        const close = (value) => {
            modal.remove();
            document.body.style.overflow = '';
            resolve(value);
        };

        const input = modal.querySelector('[data-modal-input]');
        const confirm = modal.querySelector('[data-modal-confirm]');
        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';
        if (input instanceof HTMLInputElement) {
            input.focus();
            input.select();
        }

        modal.addEventListener('click', (event) => {
            const cancel = event.target instanceof Element ? event.target.closest('[data-modal-cancel]') : null;
            if (cancel) {
                close(null);
                return;
            }
            const ok = event.target instanceof Element ? event.target.closest('[data-modal-confirm]') : null;
            if (ok) {
                const value = input instanceof HTMLInputElement ? input.value.trim() : '';
                close(value === '' ? null : value);
            }
        });

        modal.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close(null);
                return;
            }
            if (event.key === 'Enter' && confirm instanceof HTMLElement) {
                event.preventDefault();
                confirm.click();
            }
        });
    });

    const promptForImage = () => new Promise((resolve) => {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-[1200] flex items-center justify-center p-4';
        modal.innerHTML = `
            <div class="absolute inset-0 bg-[rgba(12,10,8,0.58)]" data-modal-cancel></div>
            <div class="relative z-[1] w-full max-w-md rounded-[14px] border border-[#cabca8] bg-[#ececed] p-4 shadow-[0_30px_60px_rgba(0,0,0,0.32)]">
                <h2 class="text-lg font-semibold text-[#0f2133]">Insert image</h2>
                <label class="mt-3 block text-sm font-semibold text-[#5c6570]">Image URL</label>
                <input type="url" placeholder="https://" class="mt-1 w-full rounded-lg border border-[#cabca8] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-modal-input>
                <div class="mt-3 border-t border-[#cabca8] pt-3">
                    <label class="block text-sm font-semibold text-[#5c6570]">Or upload an image</label>
                    <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="mt-1 w-full text-sm text-[#0f2133]" data-modal-file>
                    <p class="mt-1 text-xs text-[#5c6570]" data-modal-status></p>
                </div>
                <div class="mt-4 flex items-center justify-end gap-2">
                    <button type="button" class="inline-flex items-center rounded-lg border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133]" data-modal-cancel>Cancel</button>
                    <button type="button" class="inline-flex items-center rounded-lg border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white" data-modal-confirm>Insert</button>
                </div>
            </div>`;

        const close = (value) => {
            modal.remove();
            document.body.style.overflow = '';
            resolve(value);
        };

        const input = modal.querySelector('[data-modal-input]');
        const fileInput = modal.querySelector('[data-modal-file]');
        const status = modal.querySelector('[data-modal-status]');
        const confirm = modal.querySelector('[data-modal-confirm]');
        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';
        if (input instanceof HTMLInputElement) {
            input.focus();
        }

        modal.addEventListener('click', (event) => {
            const cancel = event.target instanceof Element ? event.target.closest('[data-modal-cancel]') : null;
            if (cancel) {
                close(null);
                return;
            }
            const ok = event.target instanceof Element ? event.target.closest('[data-modal-confirm]') : null;
            if (ok) {
                const value = input instanceof HTMLInputElement ? input.value.trim() : '';
                close(value === '' ? null : value);
            }
        });

        modal.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close(null);
            }
        });

        if (fileInput instanceof HTMLInputElement) {
            fileInput.addEventListener('change', async () => {
                const file = fileInput.files && fileInput.files[0];
                if (!file) {
                    return;
                }

                if (status instanceof HTMLElement) {
                    status.textContent = 'Uploading…';
                }
                if (confirm instanceof HTMLButtonElement) {
                    confirm.disabled = true;
                }
                fileInput.disabled = true;

                try {
                    const formData = new FormData();
                    formData.append('image', file);

                    const response = await fetch('/post-attachments/inline-upload', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': getCsrfToken(),
                            'Accept': 'application/json',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Upload failed');
                    }

                    const payload = await response.json();
                    close(typeof payload?.url === 'string' ? payload.url : null);
                } catch (error) {
                    if (status instanceof HTMLElement) {
                        status.textContent = 'Upload failed. Please try again or use a URL instead.';
                    }
                    if (confirm instanceof HTMLButtonElement) {
                        confirm.disabled = false;
                    }
                    fileInput.disabled = false;
                }
            });
        }
    });

    document.addEventListener('click', async (event) => {
        const trigger = event.target instanceof Element
            ? event.target.closest('[data-markdown-trigger][data-markdown-target]')
            : null;
        if (!(trigger instanceof HTMLButtonElement)) {
            return;
        }

        const targetId = trigger.dataset.markdownTarget || '';
        const textarea = targetId !== '' ? document.getElementById(targetId) : null;
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return;
        }

        event.preventDefault();

        if (trigger.dataset.markdownTag) {
            const tag = trigger.dataset.markdownTag;
            const markdownWrappers = {
                b: ['**', '**'],
                i: ['*', '*'],
                s: ['~~', '~~'],
                code: ['```\n', '\n```'],
                codeinline: ['`', '`'],
            };
            if (markdownWrappers[tag]) {
                wrapSelection(textarea, ...markdownWrappers[tag]);
                return;
            }
            if (tag === 'quote') {
                const { selected } = getSelection(textarea);
                const source = selected.trim() !== '' ? selected : 'Quoted text';
                replaceSelection(textarea, source.split(/\r?\n/).map((line) => `> ${line}`).join('\n'));
                return;
            }
            if (!['list', 'list=1'].includes(tag)) {
                const closeTag = tag.includes('=') ? tag.split('=')[0] : tag;
                wrapSelection(textarea, `[${tag}]`, `[/${closeTag}]`);
                return;
            }
        }

        if (trigger.hasAttribute('data-markdown-heading')) {
            const { selected } = getSelection(textarea);
            const source = selected.trim() !== '' ? selected : 'Heading';
            replaceSelection(textarea, source
                .split(/\r?\n/)
                .map((line) => `## ${line.replace(/^#+\s*/, '')}`)
                .join('\n'));
            return;
        }

        if (trigger.hasAttribute('data-markdown-line-list')) {
            const { selected } = getSelection(textarea);
            const source = selected.trim() !== '' ? selected : 'List item';
            const items = source
                .split(/\r?\n/)
                .map((line) => line.trim())
                .filter((line) => line !== '');
            replaceSelection(textarea, items.map((line) => `- ${line}`).join('\n'));
            return;
        }

        if (trigger.hasAttribute('data-markdown-ordered-list')) {
            const { selected } = getSelection(textarea);
            const source = selected.trim() !== '' ? selected : 'List item';
            const items = source
                .split(/\r?\n/)
                .map((line) => line.trim())
                .filter((line) => line !== '');
            replaceSelection(textarea, items.map((line, index) => `${index + 1}. ${line}`).join('\n'));
            return;
        }

        if (trigger.hasAttribute('data-markdown-url')) {
            const url = await promptWithModal({ title: 'Enter URL', defaultValue: 'https://', placeholder: 'https://', inputType: 'url' });
            if (!url) {
                return;
            }
            const { selected } = getSelection(textarea);
            const label = selected.trim() !== '' ? selected : url;
            replaceSelection(textarea, `[${label}](${url})`);
            return;
        }

        if (trigger.hasAttribute('data-markdown-image')) {
            const url = await promptForImage();
            if (!url) {
                return;
            }
            replaceSelection(textarea, `![](${url})`);
            return;
        }

        if (trigger.hasAttribute('data-markdown-video')) {
            const url = await promptWithModal({ title: 'Enter video URL', defaultValue: 'https://', placeholder: 'https://', inputType: 'url' });
            if (!url) {
                return;
            }
            replaceSelection(textarea, `[video]${url}[/video]`);
            return;
        }

        if (trigger.hasAttribute('data-markdown-email')) {
            const email = await promptWithModal({ title: 'Enter email address', defaultValue: '', placeholder: 'name@example.com', inputType: 'email' });
            if (!email) {
                return;
            }
            replaceSelection(textarea, `[${email}](mailto:${email})`);
            return;
        }

        if (trigger.hasAttribute('data-markdown-color')) {
            const color = await promptWithModal({ title: 'Enter color (name or hex)', defaultValue: '#ff0000', placeholder: '#ff0000' });
            if (!color) {
                return;
            }
            wrapSelection(textarea, `[color=${color}]`, '[/color]');
            return;
        }

        if (trigger.hasAttribute('data-markdown-size')) {
            const size = await promptWithModal({ title: 'Enter size (e.g. 12)', defaultValue: '12', placeholder: '12' });
            if (!size) {
                return;
            }
            wrapSelection(textarea, `[size=${size}]`, '[/size]');
            return;
        }

        if (trigger.hasAttribute('data-markdown-font')) {
            const font = await promptWithModal({ title: 'Enter font family', defaultValue: 'Arial', placeholder: 'Arial' });
            if (!font) {
                return;
            }
            wrapSelection(textarea, `[font=${font}]`, '[/font]');
            return;
        }

        if (trigger.hasAttribute('data-markdown-spoiler')) {
            wrapSelection(textarea, '[spoiler]', '[/spoiler]');
            return;
        }

        if (trigger.hasAttribute('data-markdown-hr')) {
            replaceSelection(textarea, '\n---\n');
        }
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target instanceof Element
            ? event.target.closest('[data-smiley-target][data-smiley]')
            : null;
        if (!(trigger instanceof HTMLButtonElement)) {
            return;
        }

        const targetId = trigger.dataset.smileyTarget || '';
        const textarea = targetId !== '' ? document.getElementById(targetId) : null;
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return;
        }

        event.preventDefault();
        const code = trigger.dataset.smiley || '';
        if (code !== '') {
            const insertion = ` ${code} `;
            const start = textarea.selectionStart ?? textarea.value.length;
            const end = textarea.selectionEnd ?? textarea.value.length;
            textarea.setRangeText(insertion, start, end, 'end');
            textarea.focus();
        }
    });

    document.addEventListener('click', async (event) => {
        const previewTrigger = event.target instanceof Element
            ? event.target.closest('[data-markdown-preview]')
            : null;
        if (!(previewTrigger instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();

        const sourceId = previewTrigger.dataset.markdownSource || '';
        const panelId = previewTrigger.dataset.markdownPreviewPanel || '';
        const source = sourceId !== '' ? document.getElementById(sourceId) : null;
        const panel = panelId !== '' ? document.getElementById(panelId) : null;
        const content = panel instanceof HTMLElement ? panel.querySelector('[data-reply-preview-content]') : null;
        if (!(source instanceof HTMLTextAreaElement) || !(panel instanceof HTMLElement) || !(content instanceof HTMLElement)) {
            return;
        }

        content.textContent = 'Loading preview…';
        panel.hidden = false;

        try {
            const response = await fetch('/markdown/preview', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ body: source.value }),
            });
            if (!response.ok) {
                throw new Error('Preview failed');
            }
            const payload = await response.json();
            content.innerHTML = typeof payload?.html === 'string' ? payload.html : '';
        } catch (error) {
            content.textContent = 'Preview unavailable. Please try again.';
        }
    });
})();
