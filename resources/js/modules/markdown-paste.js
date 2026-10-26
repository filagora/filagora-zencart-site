(() => {
    // A <textarea> only ever receives the clipboard's plain-text data on
    // paste, so copying a rendered link (e.g. from a GitHub release page)
    // and pasting it into a post/thread/message body drops the href and
    // keeps only the visible label. Recover it: read the richer text/html
    // payload the browser also puts on the clipboard, find each link's
    // visible text inside the plain-text version, and splice in Markdown
    // link syntax at that position.
    //
    // Loaded on every page that has a markdown toolbar (gate:
    // [data-markdown-trigger] in app.js). Only markdown-authoring
    // textareas are touched, so plain-text fields elsewhere are unaffected.
    const isMarkdownTextarea = (element) => {
        if (!(element instanceof HTMLTextAreaElement) || element.id === '') {
            return false;
        }

        // thread-show.js wires its reply toolbar against a hardcoded
        // #reply-body reference instead of the data-markdown-target
        // convention every other markdown textarea's toolbar uses.
        if (element.id === 'reply-body') {
            return true;
        }

        return document.querySelector(`[data-markdown-target="${CSS.escape(element.id)}"]`) !== null;
    };

    document.addEventListener('paste', (event) => {
        const textarea = event.target;
        if (!isMarkdownTextarea(textarea)) {
            return;
        }

        const clipboardData = event.clipboardData;
        const html = clipboardData ? clipboardData.getData('text/html') : '';
        const plainText = clipboardData ? clipboardData.getData('text/plain') : '';
        if (html === '' || plainText === '') {
            return;
        }

        // Parsed via DOMParser rather than assigned into a live element's
        // innerHTML, so the untrusted fragment never touches document.body
        // even momentarily - matches members-index.js's pattern for
        // handling clipboard/response HTML that isn't from this origin.
        const links = Array.from(new DOMParser().parseFromString(html, 'text/html').querySelectorAll('a[href]'))
            .map((anchor) => ({
                text: anchor.textContent.trim(),
                href: anchor.getAttribute('href') || '',
            }))
            .filter(({ text, href }) => text !== '' && /^(https?:|mailto:)/i.test(href));

        if (links.length === 0) {
            return;
        }

        // The source page's link text/href are attacker-reachable (a page
        // the victim is tricked into copying from), so both are escaped
        // before splicing into markdown: an unescaped "]" in the label or
        // ")" / whitespace in the href would otherwise close the [label]
        // or (href) span early and let the rest of the string be parsed as
        // fresh, unrelated markdown - a link the poster never saw or
        // clicked ending up in their post. CommonMark still refuses a
        // javascript:/data: href at render time either way
        // (allow_unsafe_links: false in MarkdownRenderer), so this is
        // about link-spoofing/content-integrity, not script execution.
        const escapeLinkLabel = (text) => text.replace(/\\/g, '\\\\').replace(/\]/g, '\\]');
        // encodeURIComponent leaves "(" and ")" unescaped - they're in its
        // legacy RFC 2396 "unreserved mark" safe list along with ! ~ * ' -
        // so they need an explicit map; encodeURIComponent still handles
        // the \s class (space, tab, newline, ...) correctly on its own.
        const PERCENT_ENCODE_PARENS = { '(': '%28', ')': '%29' };
        const encodeLinkDestination = (href) => href.replace(
            /[\s()]/g,
            (char) => PERCENT_ENCODE_PARENS[char] ?? encodeURIComponent(char),
        );

        let cursor = 0;
        let rebuilt = '';
        let recoveredALink = false;

        for (const { text, href } of links) {
            const index = plainText.indexOf(text, cursor);
            if (index === -1) {
                continue;
            }

            rebuilt += plainText.slice(cursor, index) + `[${escapeLinkLabel(text)}](${encodeLinkDestination(href)})`;
            cursor = index + text.length;
            recoveredALink = true;
        }
        rebuilt += plainText.slice(cursor);

        if (!recoveredALink) {
            return;
        }

        event.preventDefault();
        const start = textarea.selectionStart ?? textarea.value.length;
        const end = textarea.selectionEnd ?? textarea.value.length;
        textarea.setRangeText(rebuilt, start, end, 'end');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    });
})();
