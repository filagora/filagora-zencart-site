import { getCsrfToken, readEventStream, renderMarkdown } from '../lib/chat-markdown';

(() => {
    const root = document.querySelector('[data-thread-assist-root]');
    const openButtons = document.querySelectorAll('[data-thread-assist-open]');
    if (!(root instanceof HTMLElement) || openButtons.length === 0) {
        return;
    }

    const body = root.querySelector('[data-thread-assist-body]');
    const status = root.querySelector('[data-thread-assist-status]');
    const note = root.querySelector('[data-thread-assist-note]');
    const sources = root.querySelector('[data-thread-assist-sources]');
    const sourcesWrap = root.querySelector('[data-thread-assist-sources-wrap]');
    const sourcesCount = root.querySelector('[data-thread-assist-sources-count]');
    const closeButton = root.querySelector('[data-thread-assist-close]');
    const copyButton = root.querySelector('[data-thread-assist-copy]');
    if (!(body instanceof HTMLElement) || !(status instanceof HTMLElement) || !(note instanceof HTMLElement)
        || !(sources instanceof HTMLElement) || !(sourcesWrap instanceof HTMLElement)
        || !(sourcesCount instanceof HTMLElement) || !(closeButton instanceof HTMLButtonElement)
        || !(copyButton instanceof HTMLButtonElement)) {
        return;
    }

    let answer = '';
    let running = false;
    let asked = false;

    let ticker = null;

    const setStatus = (text, busy = false) => {
        status.textContent = text;
        status.hidden = text === '';
        status.classList.toggle('animate-pulse', busy);
    };

    const stopTicker = () => {
        if (ticker !== null) {
            clearInterval(ticker);
            ticker = null;
        }
    };

    // Until the first words arrive there is nothing to look at, and with a slow
    // model that can be a minute or more: count the seconds so it is clear
    // the page has not stalled.
    const startWaiting = () => {
        const startedAt = Date.now();
        const tick = () => {
            const seconds = Math.round((Date.now() - startedAt) / 1000);
            setStatus(`Found related material. Waiting for the AI to start writing - this can take a minute or two… ${seconds}s`, true);
        };
        stopTicker();
        tick();
        ticker = setInterval(tick, 1000);
    };

    const showSources = (items) => {
        sources.textContent = '';
        for (const item of items) {
            if (typeof item.url !== 'string' || !/^https?:\/\//.test(item.url)) {
                continue;
            }

            const entry = document.createElement('li');
            const anchor = document.createElement('a');
            anchor.href = item.url;
            anchor.textContent = item.title;
            anchor.target = '_blank';
            anchor.rel = 'noopener noreferrer';
            anchor.className = 'underline underline-offset-2 hover:text-[#8f3f1f]';
            entry.appendChild(anchor);
            sources.appendChild(entry);
        }
        sourcesCount.textContent = String(sources.childElementCount);
    };

    // The list of what was searched goes at the end, folded away: before the
    // answer it only looked like the answer, and a long list of links.
    const revealSources = () => {
        sourcesWrap.hidden = sources.childElementCount === 0;
    };

    const ask = async () => {
        running = true;
        asked = true;
        answer = '';
        body.textContent = '';
        note.hidden = true;
        sourcesWrap.hidden = true;
        sourcesWrap.open = false;
        copyButton.hidden = true;
        setStatus('Reading the thread and searching the docs and code…', true);

        let failed = false;

        try {
            const response = await fetch(root.dataset.threadAssistUrl || '', {
                method: 'POST',
                headers: {
                    Accept: 'text/event-stream',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });

            if (response.status === 429) {
                setStatus('Too many requests just now. Wait a minute and try again.');
                failed = true;

                return;
            }

            if (!response.ok || !response.body) {
                setStatus('The AI assist could not be started.');
                failed = true;

                return;
            }

            await readEventStream(response, (payload) => {
                if (Array.isArray(payload.sources)) {
                    showSources(payload.sources);
                    if (answer === '') {
                        startWaiting();
                    }
                }
                if (typeof payload.version_note === 'string' && payload.version_note !== '') {
                    note.textContent = payload.version_note;
                    note.hidden = false;
                }
                if (typeof payload.delta === 'string') {
                    if (answer === '') {
                        stopTicker();
                        setStatus('Writing…', true);
                    }
                    answer += payload.delta;
                    renderMarkdown(body, answer);
                }
                if (payload.error) {
                    failed = true;
                }
            });

            stopTicker();

            if (failed && answer === '') {
                setStatus('Something went wrong generating an answer.');
            } else if (failed) {
                setStatus('The answer was cut short by an error.');
            } else if (answer === '') {
                setStatus('The AI returned nothing for this thread.');
            } else {
                setStatus('');
            }
        } catch {
            stopTicker();
            setStatus(answer === ''
                ? 'The connection closed before the AI answered. It may have taken too long - try again in a moment.'
                : 'The connection closed before the answer finished.');
        } finally {
            running = false;
            stopTicker();
            copyButton.hidden = answer === '';
            revealSources();
        }
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            root.hidden = false;
            root.scrollIntoView({ block: 'nearest' });

            if (!running && !asked) {
                void ask();
            }
        });
    });

    closeButton.addEventListener('click', () => {
        root.hidden = true;
    });

    copyButton.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(answer);
            copyButton.textContent = 'Copied';
        } catch {
            copyButton.textContent = 'Copy failed';
        }
        setTimeout(() => {
            copyButton.textContent = 'Copy answer';
        }, 2000);
    });
})();
