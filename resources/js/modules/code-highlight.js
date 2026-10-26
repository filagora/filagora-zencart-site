// Syntax-highlight code blocks in rendered content (forum posts, private
// messages, CMS pages) and in the Markdown "Preview" panels. highlight.js
// itself is only fetched once a code block is actually on the page - see
// ../lib/hljs-run.js. Gated in app.js on .forum-rendered-content /
// .cms-rich-content.

let runnerPromise = null;

function loadRunner() {
    if (!runnerPromise) {
        runnerPromise = import('../lib/hljs-run.js').then((module) => module.highlightWithin);
    }

    return runnerPromise;
}

const CODE_SELECTOR = '.forum-rendered-content pre code, .cms-rich-content pre code';

function highlight(root) {
    if (!root.querySelector(CODE_SELECTOR)) {
        return;
    }
    loadRunner().then((highlightWithin) => highlightWithin(root));
}

highlight(document);

// The Markdown preview swaps fresh HTML into its panel; re-run over it whenever
// that content changes.
document.querySelectorAll('[data-reply-preview-content]').forEach((panel) => {
    const observer = new MutationObserver(() => highlight(panel));
    observer.observe(panel, { childList: true, subtree: true });
});
