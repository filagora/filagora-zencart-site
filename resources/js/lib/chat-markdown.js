import MarkdownIt from 'markdown-it';

// The model answers in markdown. html is off, so any markup in the text
// (a malicious forum post that made it into the model's context, say) is
// shown as inert text, and markdown-it refuses javascript: links itself.
const markdown = new MarkdownIt({ html: false, linkify: false, breaks: true });

const defaultLinkOpen = markdown.renderer.rules.link_open
    || ((tokens, idx, options, env, self) => self.renderToken(tokens, idx, options));
markdown.renderer.rules.link_open = (tokens, idx, options, env, self) => {
    tokens[idx].attrSet('target', '_blank');
    tokens[idx].attrSet('rel', 'noopener noreferrer');

    return defaultLinkOpen(tokens, idx, options, env, self);
};

export const renderMarkdown = (container, text) => {
    container.classList.remove('whitespace-pre-wrap');
    container.innerHTML = markdown.render(text);
};

export const getCsrfToken = () => {
    const token = document.querySelector('meta[name="csrf-token"]');

    return token instanceof HTMLMetaElement ? token.getAttribute('content') || '' : '';
};

// The stream is SSE-shaped (`data: {...}\n\n` frames) but sent over a
// plain POST response body, not EventSource (which only supports GET) -
// so this reads and splits the frames by hand instead.
export const readEventStream = async (response, onEvent) => {
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    for (;;) {
        const { value, done } = await reader.read();
        if (done) {
            break;
        }

        buffer += decoder.decode(value, { stream: true });
        const frames = buffer.split('\n\n');
        buffer = frames.pop() ?? '';

        for (const frame of frames) {
            const line = frame.split('\n').find((candidate) => candidate.startsWith('data: '));
            if (!line) {
                continue;
            }

            let payload;
            try {
                payload = JSON.parse(line.slice('data: '.length));
            } catch {
                // Malformed frame - ignore rather than break the stream.
                continue;
            }

            // Outside the try: a bug in a handler must not look like a bad frame.
            onEvent(payload);
        }
    }
};
