// highlight.js core with the curated grammar set, used to colour code blocks
// in already-rendered content (forum posts, PMs, CMS pages, live previews).
// Loaded on demand by modules/code-highlight.js only when a page actually
// contains a code block.

import hljs from 'highlight.js/lib/core';
import { LANGUAGE_DEFINITIONS, LANGUAGE_ALIASES } from './code-languages';

let registered = false;

function ensureRegistered() {
    if (registered) {
        return;
    }
    Object.entries(LANGUAGE_DEFINITIONS).forEach(([name, definition]) => {
        hljs.registerLanguage(name, definition);
    });
    Object.entries(LANGUAGE_ALIASES).forEach(([name, aliases]) => {
        hljs.registerAliases(aliases, { languageName: name });
    });
    // The markup is server-rendered and already escaped; don't warn about it.
    hljs.configure({ ignoreUnescapedHTML: true });
    registered = true;
}

const SELECTOR = '.forum-rendered-content pre code, .cms-rich-content pre code';

/**
 * Highlight every not-yet-highlighted code block within `root`. A block whose
 * fenced language is outside the curated set is left as plain text.
 *
 * @param {ParentNode} root
 */
export function highlightWithin(root) {
    ensureRegistered();

    root.querySelectorAll(SELECTOR).forEach((code) => {
        if (code.dataset.highlighted === 'yes') {
            return;
        }

        const languageClass = Array.from(code.classList).find((name) => name.startsWith('language-'));
        const language = languageClass ? languageClass.slice('language-'.length).toLowerCase() : '';

        if (language && !hljs.getLanguage(language)) {
            code.dataset.highlighted = 'yes';

            return;
        }

        try {
            hljs.highlightElement(code);
        } catch (error) {
            /* leave the block as plain text */
        }
    });
}
