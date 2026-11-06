import { getCsrfToken, readEventStream, renderMarkdown } from '../lib/chat-markdown';

(() => {
    if (window.__forumAiChatInit) {
        return;
    }
    window.__forumAiChatInit = true;

    const root = document.querySelector('[data-ai-chat-root]');
    const form = document.querySelector('[data-ai-chat-form]');
    const messagesEl = document.querySelector('[data-ai-chat-messages]');
    const input = document.querySelector('[data-ai-chat-input]');
    const submitButton = document.querySelector('[data-ai-chat-submit]');
    if (!(root instanceof HTMLElement)
        || !(form instanceof HTMLFormElement)
        || !(messagesEl instanceof HTMLElement)
        || !(input instanceof HTMLTextAreaElement)
        || !(submitButton instanceof HTMLButtonElement)) {
        return;
    }

    const isNewConversation = () => !root.dataset.conversationId;

    // What the member typed stays exactly as typed.
    const renderPlain = (container, text) => {
        container.textContent = text;
    };

    // Earlier replies arrive from the server as plain text.
    document.querySelectorAll('[data-ai-chat-markdown]').forEach((element) => {
        renderMarkdown(element, element.textContent || '');
    });

    const appendMessage = (role, text) => {
        const emptyState = messagesEl.querySelector('[data-ai-chat-empty-state]');
        if (emptyState instanceof HTMLElement) {
            emptyState.remove();
        }

        const article = document.createElement('article');
        article.className = role === 'user'
            ? 'ml-auto max-w-[80ch] border border-[#cabca8] bg-[#f8f6f1] px-3 py-2.5'
            : 'max-w-[80ch] border border-[#d8dce2] bg-white px-3 py-2.5';

        const label = document.createElement('p');
        label.className = 'ui-small-meta m-0';
        label.textContent = role === 'user' ? 'You' : 'Assistant';
        article.appendChild(label);

        const body = document.createElement('div');
        body.className = role === 'user'
            ? 'mt-1.5 whitespace-pre-wrap text-sm text-[#0f2133]'
            : 'forum-rendered-content mt-1.5 text-sm text-[#0f2133]';
        if (role === 'user') {
            renderPlain(body, text);
        } else {
            renderMarkdown(body, text);
        }
        article.appendChild(body);

        messagesEl.appendChild(article);
        messagesEl.scrollTop = messagesEl.scrollHeight;

        return body;
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const message = input.value.trim();
        if (message === '') {
            return;
        }

        submitButton.disabled = true;
        input.disabled = true;
        appendMessage('user', message);
        const assistantBody = appendMessage('assistant', '');
        input.value = '';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ message }),
            });

            if (!response.ok || !response.body) {
                assistantBody.textContent = 'Something went wrong sending that message.';
                return;
            }

            let assembled = '';
            let conversationId = null;
            let sawError = false;

            await readEventStream(response, (payload) => {
                if (typeof payload.delta === 'string') {
                    assembled += payload.delta;
                    renderMarkdown(assistantBody, assembled);
                    messagesEl.scrollTop = messagesEl.scrollHeight;
                }
                if (payload.error) {
                    sawError = true;
                }
                if (payload.done) {
                    conversationId = payload.conversation_id ?? null;
                }
            });

            if (sawError && assembled === '') {
                assistantBody.textContent = 'Sorry, something went wrong generating a reply.';
            }

            if (isNewConversation() && conversationId) {
                window.location.href = `/ai-chat/${conversationId}`;
                return;
            }
        } catch {
            assistantBody.textContent = 'Something went wrong sending that message.';
        } finally {
            submitButton.disabled = false;
            input.disabled = false;
            input.focus();
        }
    });
})();
