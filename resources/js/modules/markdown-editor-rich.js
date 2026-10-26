// Markdown-backed rich text composer.
//
// Progressive enhancement layered on an existing markdown <textarea>: mounts a
// TipTap editor whose full action set matches the classic markdown toolbar
// markdown toolbar, keeps the
// textarea's value in sync as serialized markdown, and offers a checkbox to
// switch back to the plain textarea. If anything throws, the textarea is left
// exactly as it was. Gated in app.js on [data-rich-editor]; the composer
// starts in the mode named by data-rich-editor-default (the user's
// prefers_rich_editor profile setting).

import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Image from '@tiptap/extension-image';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import { parseMarkdown, serializeMarkdown } from './markdown-serialize';
import { openImagePicker, uploadInlineImage } from '../lib/image-picker';
import { iconSvg } from '../lib/editor-icons';
import { lowlight } from '../lib/lowlight-instance';
import { CODE_LANGUAGES } from '../lib/code-languages';

const btnClass =
    'inline-flex min-h-8 min-w-8 items-center justify-center rounded-control border border-transparent px-2 text-sm font-semibold text-[#304458] transition hover:border-[#cabca8] hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f] aria-pressed:border-[#933c1f] aria-pressed:bg-[#f7e8df] aria-pressed:text-[#933c1f]';

// Same control set and order as the classic toolbar
// (resources/views/components/markdown-toolbar.blade.php) so switching
// editors doesn't rearrange or drop buttons.
const RICH_TOOLS = [
    { cmd: 'bold', icon: 'bold', title: 'Bold' },
    { cmd: 'italic', icon: 'italic', title: 'Italic' },
    { cmd: 'strike', icon: 'strikethrough', title: 'Strikethrough' },
    { sep: true },
    { cmd: 'h2', icon: 'heading', title: 'Heading' },
    { cmd: 'bulletList', icon: 'list-bullet', title: 'Bulleted list' },
    { cmd: 'orderedList', icon: 'list-ordered', title: 'Numbered list' },
    { cmd: 'blockquote', icon: 'quote', title: 'Quote' },
    { sep: true },
    { cmd: 'code', icon: 'code', title: 'Inline code' },
    { cmd: 'codeBlock', icon: 'code-block', title: 'Code block' },
    { cmd: 'horizontalRule', icon: 'rule', title: 'Horizontal rule' },
    { sep: true },
    { cmd: 'link', icon: 'link', title: 'Insert link' },
    { cmd: 'email', icon: 'email', title: 'Insert email address' },
    { cmd: 'image', icon: 'image', title: 'Insert image' },
];

function el(tag, className, attrs = {}) {
    const node = document.createElement(tag);
    if (className) {
        node.className = className;
    }
    Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));

    return node;
}

function runCommand(editor, cmd) {
    const chain = editor.chain().focus();
    switch (cmd) {
        case 'bold': return chain.toggleBold().run();
        case 'italic': return chain.toggleItalic().run();
        case 'strike': return chain.toggleStrike().run();
        case 'code': return chain.toggleCode().run();
        case 'codeBlock': return chain.toggleCodeBlock().run();
        case 'h2': return chain.toggleHeading({ level: 2 }).run();
        case 'bulletList': return chain.toggleBulletList().run();
        case 'orderedList': return chain.toggleOrderedList().run();
        case 'blockquote': return chain.toggleBlockquote().run();
        case 'horizontalRule': return chain.setHorizontalRule().run();
        case 'link': {
            const prev = editor.getAttributes('link').href || '';
            const href = window.prompt('Link URL', prev);
            if (href === null) return false;
            if (href.trim() === '') return chain.unsetLink().run();

            return chain.extendMarkRange('link').setLink({ href: href.trim() }).run();
        }
        case 'email': {
            const address = window.prompt('Email address', '');
            if (!address || address.trim() === '') return false;

            return chain.extendMarkRange('link').setLink({ href: `mailto:${address.trim()}` }).run();
        }
        case 'image': {
            openImagePicker().then((result) => {
                if (result && result.src) {
                    editor.chain().focus().setImage({ src: result.src, alt: result.alt || '' }).run();
                }
            });

            return true;
        }
        default: return false;
    }
}

function isActive(editor, cmd) {
    switch (cmd) {
        case 'h2': return editor.isActive('heading', { level: 2 });
        case 'link': case 'email': return editor.isActive('link');
        default: return editor.isActive(cmd);
    }
}

// The old forum's formatting tags still render for existing posts, but this composer cannot
// represent them: opening such a post here would show the tags as text and save them back mangled.
// A body that holds one is edited in the plain Markdown box.
const LEGACY_TAG = /\[(?:u|sup|sub|highlight|left|center|right|indent|color|size|font|spoiler|video|attach)(?:=[^\]]*)?\]/i;

function enhance(host) {
    const textarea = document.getElementById(host.dataset.richEditorTarget || '');
    if (!(textarea instanceof HTMLTextAreaElement) || host.dataset.richEditorReady === '1') {
        return;
    }
    host.dataset.richEditorReady = '1';

    const classicToolbar = host.querySelector('[data-markdown-classic-toolbar]');
    const form = textarea.closest('form');

    // Always-visible switch between the rich editor and the plain Markdown
    // textarea. It starts on the user's `prefers_rich_editor` profile default
    // (data-rich-editor-default) and is a transient per-composer override -
    // reloading returns to the profile default.
    const switcher = el('div', 'flex items-center justify-end border-b border-[#d8dce2] bg-[#f7f4ef] px-3 py-1.5');
    const switcherLabel = el('label', 'inline-flex cursor-pointer select-none items-center gap-2 text-xs font-semibold text-[#5c6570]');
    const switcherCheckbox = el('input', 'h-3.5 w-3.5 accent-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]', { type: 'checkbox' });
    switcherLabel.append(switcherCheckbox, document.createTextNode('Rich text editor'));
    switcher.append(switcherLabel);
    if (LEGACY_TAG.test(textarea.value)) {
        switcherCheckbox.disabled = true;
        switcherLabel.title = 'This post uses formatting the rich editor cannot show, so it is edited as Markdown.';
    }
    switcherCheckbox.addEventListener('change', () => setMode(switcherCheckbox.checked ? 'rich' : 'source'));
    host.prepend(switcher);

    const shell = el('div', 'flex flex-col');
    const toolbar = el('div', 'flex flex-wrap items-center gap-0.5 border-b border-[#d8dce2] bg-[#f7f4ef] px-2 py-1.5', {
        role: 'toolbar',
        'aria-label': 'Formatting',
    });
    const mount = el('div', 'rich-editor-mount min-h-[220px] px-3 py-2.5 text-sm text-[#0f2133] md:border-r md:border-[#d8dce2]');
    shell.append(toolbar, mount);

    const toolButtons = [];
    RICH_TOOLS.forEach((tool) => {
        if (tool.sep) {
            toolbar.append(el('span', 'mx-1 h-4 w-px bg-[#d8dce2]'));

            return;
        }
        const button = el('button', btnClass, { type: 'button', title: tool.title, 'aria-label': tool.title, 'aria-pressed': 'false' });
        button.innerHTML = iconSvg(tool.icon);
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            runCommand(editor, tool.cmd);
            // Toggling a mark with no selection only changes stored marks, which
            // does not fire onSelectionUpdate - refresh here so the button
            // reflects the armed state straight away.
            refreshToolbar();
        });
        toolButtons.push([button, tool.cmd]);
        toolbar.append(button);
    });

    // Language picker for the current code block - inert until the caret is
    // inside one. Changing it re-tokenises the block via lowlight.
    const langSelect = el('select', 'ml-1 h-8 rounded-control border border-[#d8dce2] bg-white px-1.5 text-xs text-[#304458] disabled:opacity-40', {
        title: 'Code block language',
        'aria-label': 'Code block language',
    });
    CODE_LANGUAGES.forEach(({ value, label }) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        langSelect.append(option);
    });
    langSelect.disabled = true;
    langSelect.addEventListener('change', () => {
        editor.chain().focus().updateAttributes('codeBlock', { language: langSelect.value || null }).run();
    });
    toolbar.append(langSelect);

    // Serialising the whole document to Markdown and re-checking ~20 toolbar
    // states on every keystroke made typing laggy in long posts. Coalesce both:
    // the textarea sync waits for a short pause (and is flushed whenever focus
    // leaves the editor, on submit and when switching modes, so nothing ever
    // reads a stale value), and the toolbar refresh runs once per frame.
    const SYNC_DEBOUNCE_MS = 250;
    let syncTimer = null;
    let toolbarFrame = null;

    function scheduleSync() {
        if (syncTimer !== null) {
            window.clearTimeout(syncTimer);
        }
        syncTimer = window.setTimeout(flushPendingSync, SYNC_DEBOUNCE_MS);
    }

    // Sync only if typing is waiting to be written out.
    function flushPendingSync() {
        if (syncTimer !== null) {
            syncNow();
        }
    }

    // Sync unconditionally, dropping any pending debounced sync.
    function syncNow() {
        if (syncTimer !== null) {
            window.clearTimeout(syncTimer);
            syncTimer = null;
        }
        syncToTextarea();
    }

    function scheduleToolbarRefresh() {
        if (toolbarFrame !== null) {
            return;
        }
        toolbarFrame = window.requestAnimationFrame(() => {
            toolbarFrame = null;
            refreshToolbar();
        });
    }

    let editor;
    try {
        editor = new Editor({
            element: mount,
            extensions: [
                StarterKit.configure({ heading: { levels: [1, 2, 3] }, codeBlock: false }),
                CodeBlockLowlight.configure({ lowlight, defaultLanguage: null }),
                Placeholder.configure({ placeholder: textarea.getAttribute('placeholder') || 'Write something…' }),
                Image.configure({ inline: true }),
            ],
            content: '',
            onUpdate: () => {
                scheduleSync();
                scheduleToolbarRefresh();
            },
            onSelectionUpdate: scheduleToolbarRefresh,
        });
        editor.commands.setContent(parseMarkdown(editor.schema, textarea.value).toJSON(), false);
    } catch (error) {
        host.dataset.richEditorReady = '';
        shell.remove();
        switcher.remove();
        // eslint-disable-next-line no-console
        console.warn('Rich composer unavailable, using the markdown textarea.', error);

        return;
    }

    function syncToTextarea() {
        try {
            textarea.value = serializeMarkdown(editor.state.doc);
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        } catch (error) {
            /* keep whatever is already in the textarea */
        }
    }

    function refreshToolbar() {
        toolButtons.forEach(([button, cmd]) => button.setAttribute('aria-pressed', isActive(editor, cmd) ? 'true' : 'false'));

        const inCodeBlock = editor.isActive('codeBlock');
        langSelect.disabled = !inCodeBlock;
        if (inCodeBlock) {
            langSelect.value = editor.getAttributes('codeBlock').language || '';
        }
    }

    // The site setting gates whether this module loads at all; the user's
    // `prefers_rich_editor` profile setting picks the starting mode, and the
    // switcher checkbox above flips it for this composer only.
    function setMode(mode) {
        const rich = mode !== 'source' && !LEGACY_TAG.test(textarea.value);
        switcherCheckbox.checked = rich;
        shell.hidden = !rich;
        textarea.hidden = rich;
        if (classicToolbar instanceof HTMLElement) {
            classicToolbar.hidden = rich;
        }
        if (rich) {
            editor.commands.setContent(parseMarkdown(editor.schema, textarea.value).toJSON(), false);
            refreshToolbar();
        } else {
            syncNow();
            textarea.focus();
        }
    }

    // Smiley buttons target the textarea by id; in rich mode, insert into the editor instead.
    host.addEventListener('click', (event) => {
        const smiley = event.target instanceof Element
            ? event.target.closest(`[data-smiley-target="${textarea.id}"][data-smiley]`)
            : null;
        if (!smiley || shell.hidden) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        editor.chain().focus().insertContent(smiley.getAttribute('data-smiley') || '').run();
    }, true);

    // Reply-with-Quote (and any other code that writes markdown straight into
    // the textarea) fires this so the editor picks the new content up.
    textarea.addEventListener('markdown:external-change', () => {
        if (shell.hidden) {
            return;
        }
        if (syncTimer !== null) {
            window.clearTimeout(syncTimer);
            syncTimer = null;
        }
        editor.commands.setContent(parseMarkdown(editor.schema, textarea.value).toJSON(), false);
        refreshToolbar();
        editor.chain().focus('end').run();
    });

    if (form) {
        form.addEventListener('submit', () => {
            if (!shell.hidden) {
                syncNow();
            }
        }, true);
    }

    // Clicking Submit or Preview moves focus first, so flushing here keeps the
    // textarea current before the browser validates or reads it.
    mount.addEventListener('focusout', flushPendingSync);

    // Drag-drop or paste an image file straight into the editor.
    function insertUploadedImages(files) {
        const images = Array.from(files || []).filter((file) => file.type.startsWith('image/'));
        if (images.length === 0) {
            return false;
        }
        images.forEach((file) => {
            uploadInlineImage(file)
                .then((url) => editor.chain().focus().setImage({ src: url }).run())
                .catch(() => {
                    editor.chain().focus().insertContent('_(image upload failed)_').run();
                });
        });

        return true;
    }

    mount.addEventListener('paste', (event) => {
        if (!shell.hidden && event.clipboardData && insertUploadedImages(event.clipboardData.files)) {
            event.preventDefault();
        }
    }, true);
    mount.addEventListener('drop', (event) => {
        if (!shell.hidden && event.dataTransfer && insertUploadedImages(event.dataTransfer.files)) {
            event.preventDefault();
        }
    }, true);

    textarea.insertAdjacentElement('beforebegin', shell);

    setMode(host.dataset.richEditorDefault === 'source' ? 'source' : 'rich');
}

document.querySelectorAll('[data-rich-editor]').forEach(enhance);
