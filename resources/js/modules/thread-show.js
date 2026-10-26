    (() => {
        const replySection = document.getElementById('reply');
        const replyBody = document.getElementById('reply-body');
        const replyPreviewPanel = document.getElementById('reply-body-preview');
        const showReplyComposer = () => {
            if (!(replySection instanceof HTMLElement)) {
                return false;
            }
            replySection.hidden = false;

            return true;
        };

        const scrollToReply = () => {
            if (!showReplyComposer()) {
                return;
            }
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            replySection.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
            if (replyBody instanceof HTMLTextAreaElement) {
                replyBody.focus();
            }
        };

        const hideReplyComposer = () => {
            if (!(replySection instanceof HTMLElement) || !(replyBody instanceof HTMLTextAreaElement)) {
                return;
            }

            // Cancel used to silently no-op whenever the box had any typed
            // text, which is virtually always true when someone reaches for
            // Cancel — the button visibly did nothing. Confirm instead of
            // silently refusing, so a discard is still one click away.
            if (replyBody.value.trim() !== '' && !window.confirm('Discard your reply?')) {
                return;
            }

            replySection.hidden = true;
            replyBody.value = '';
            replyBody.removeAttribute('aria-invalid');

            if (replyPreviewPanel instanceof HTMLElement) {
                replyPreviewPanel.hidden = true;

                const previewContent = replyPreviewPanel.querySelector('[data-reply-preview-content]');
                if (previewContent instanceof HTMLElement) {
                    previewContent.innerHTML = '';
                }
            }
        };

        // Markdown toolbar, smiley insertion and the Preview fetch are the
        // shared markdown-toolbar.js module (loaded via the
        // [data-markdown-trigger] gate in app.js) - only the reply
        // composer's own open/cancel/quote behaviour lives here.
        document.addEventListener('click', (event) => {
            const replyTrigger = event.target instanceof Element
                ? event.target.closest('[data-thread-reply]')
                : null;
            if (replyTrigger) {
                event.preventDefault();
                scrollToReply();
                return;
            }

            const replyCancelTrigger = event.target instanceof Element
                ? event.target.closest('[data-thread-reply-cancel]')
                : null;
            if (replyCancelTrigger) {
                event.preventDefault();
                hideReplyComposer();
                return;
            }

            const quoteTrigger = event.target instanceof Element
                ? event.target.closest('[data-thread-quote]')
                : null;
            if (!(quoteTrigger instanceof HTMLElement) || !(replyBody instanceof HTMLTextAreaElement)) {
                return;
            }

            event.preventDefault();

            const sourceId = quoteTrigger.dataset.quoteSource || '';
            const author = (quoteTrigger.dataset.quoteAuthor || 'Unknown').trim();
            const source = sourceId !== '' ? document.getElementById(sourceId) : null;
            const sourceBody = source instanceof HTMLTextAreaElement ? source.value.trim() : '';
            const quotedBody = sourceBody.split(/\r?\n/).map((line) => `> ${line}`).join('\n');
            const quoteBlock = `> **${author}:**\n>\n${quotedBody}\n\n`;

            replyBody.value = replyBody.value.trim() === '' ? quoteBlock : `${replyBody.value.trim()}\n\n${quoteBlock}`;
            // The rich composer hides this textarea and drives its value from
            // the editor, so it needs a nudge to re-read the quote we just wrote.
            replyBody.dispatchEvent(new CustomEvent('markdown:external-change', { bubbles: true }));
            scrollToReply();
        });


        const getFocusable = (container) => {
            const nodes = container.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');

            return Array.from(nodes).filter((node) => node instanceof HTMLElement && !node.hasAttribute('hidden'));
        };

        const reportModal = document.getElementById('report-post-modal');
        const reportDialog = reportModal?.querySelector('[data-report-dialog]');
        const reportForm = document.getElementById('report-post-modal-form');
        const reportReason = document.getElementById('report-post-modal-reason');
        const reportDescription = document.getElementById('report-post-modal-description');
        const reportError = document.getElementById('report-post-modal-error');

        let reportLauncher = null;

        const closeReportModal = () => {
            if (!(reportModal instanceof HTMLElement)) {
                return;
            }

            reportModal.hidden = true;
            document.body.style.overflow = '';
            if (reportError instanceof HTMLElement) {
                reportError.hidden = true;
                reportError.textContent = '';
            }
            if (reportReason instanceof HTMLTextAreaElement) {
                reportReason.value = '';
            }
            if (reportLauncher instanceof HTMLElement) {
                reportLauncher.focus();
            }
            reportLauncher = null;
        };

        document.addEventListener('click', (event) => {
            const openTrigger = event.target instanceof Element
                ? event.target.closest('[data-report-open]')
                : null;
            if (openTrigger instanceof HTMLElement) {
                event.preventDefault();
                if (!(reportModal instanceof HTMLElement) || !(reportForm instanceof HTMLFormElement)) {
                    return;
                }

                const action = openTrigger.dataset.reportAction || '';
                if (action === '') {
                    return;
                }

                reportLauncher = openTrigger;
                reportForm.action = action;
                if (reportDescription instanceof HTMLElement) {
                    const postNumber = openTrigger.dataset.reportPostNumber || '';
                    reportDescription.textContent = postNumber !== ''
                        ? `Tell staff why post #${postNumber} should be reviewed.`
                        : 'Tell staff why this post should be reviewed.';
                }
                reportModal.hidden = false;
                document.body.style.overflow = 'hidden';
                requestAnimationFrame(() => {
                    if (reportReason instanceof HTMLTextAreaElement) {
                        reportReason.focus();
                    }
                });
                return;
            }

            const closeTrigger = event.target instanceof Element
                ? event.target.closest('[data-report-close]')
                : null;
            if (closeTrigger) {
                event.preventDefault();
                closeReportModal();
            }
        });

        document.addEventListener('submit', (event) => {
            const form = event.target instanceof Element ? event.target.closest('form') : null;
            if (!(form instanceof HTMLFormElement) || form !== reportForm) {
                return;
            }

            const value = reportReason instanceof HTMLTextAreaElement
                ? reportReason.value.trim()
                : '';
            if (value !== '') {
                return;
            }

            event.preventDefault();
            if (reportError instanceof HTMLElement) {
                reportError.hidden = false;
                reportError.textContent = 'Reason is required.';
            }
            if (reportReason instanceof HTMLTextAreaElement) {
                reportReason.focus();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (!(reportModal instanceof HTMLElement) || reportModal.hidden || !(reportDialog instanceof HTMLElement)) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeReportModal();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const focusable = getFocusable(reportDialog);
            if (focusable.length === 0) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            const active = document.activeElement;

            if (event.shiftKey && active === first) {
                event.preventDefault();
                last.focus();
                return;
            }

            if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        });
    })();
