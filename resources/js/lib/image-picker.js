// Shared "insert image" dialog for the composer: paste a URL or upload a file
// (POSTed to /post-attachments/inline-upload, which returns { url }). Resolves
// to { src, alt } or null if cancelled.

const ACCEPT = 'image/jpeg,image/png,image/gif,image/webp';

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    return meta instanceof HTMLMetaElement ? meta.getAttribute('content') || '' : '';
}

export async function uploadInlineImage(file) {
    const body = new FormData();
    body.append('image', file);

    const response = await fetch('/post-attachments/inline-upload', {
        method: 'POST',
        body,
        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error('Upload failed');
    }

    const payload = await response.json().catch(() => ({}));
    if (typeof payload?.url !== 'string' || payload.url === '') {
        throw new Error('Upload returned no URL');
    }

    return payload.url;
}

export function openImagePicker() {
    return new Promise((resolve) => {
        const previouslyFocused = document.activeElement;
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-[1200] flex items-center justify-center p-4';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', 'Insert image');
        modal.innerHTML = `
            <div class="absolute inset-0 bg-[rgba(12,10,8,0.58)]" data-cancel></div>
            <div class="relative z-[1] w-full max-w-md rounded-surface border border-[#d8dce2] bg-white p-4 shadow-[0_30px_60px_rgba(0,0,0,0.32)]">
                <h2 class="text-lg font-semibold text-[#0f2133]">Insert image</h2>
                <label class="mt-3 block text-sm font-semibold text-[#5c6570]" for="image-picker-url">Image URL</label>
                <input id="image-picker-url" type="url" placeholder="https://" class="mt-1 w-full rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-url>
                <label class="mt-3 block text-sm font-semibold text-[#5c6570]" for="image-picker-alt">Alt text (optional)</label>
                <input id="image-picker-alt" type="text" class="mt-1 w-full rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-alt>
                <div class="mt-3 border-t border-[#d8dce2] pt-3">
                    <label class="block text-sm font-semibold text-[#5c6570]" for="image-picker-file">Or upload an image</label>
                    <input id="image-picker-file" type="file" accept="${ACCEPT}" class="mt-1 w-full text-sm text-[#0f2133]" data-file>
                    <p class="mt-1 text-xs text-[#5c6570]" data-status aria-live="polite"></p>
                </div>
                <div class="mt-4 flex items-center justify-end gap-2">
                    <button type="button" class="inline-flex items-center rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133]" data-cancel>Cancel</button>
                    <button type="button" class="inline-flex items-center rounded-control border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white" data-confirm>Insert</button>
                </div>
            </div>`;

        const urlInput = modal.querySelector('[data-url]');
        const altInput = modal.querySelector('[data-alt]');
        const fileInput = modal.querySelector('[data-file]');
        const status = modal.querySelector('[data-status]');
        const confirmBtn = modal.querySelector('[data-confirm]');

        const close = (result) => {
            modal.remove();
            document.body.style.overflow = '';
            if (previouslyFocused instanceof HTMLElement) {
                previouslyFocused.focus();
            }
            resolve(result);
        };

        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';
        urlInput.focus();

        modal.addEventListener('click', (event) => {
            if (event.target instanceof Element && event.target.closest('[data-cancel]')) {
                close(null);

                return;
            }
            if (event.target instanceof Element && event.target.closest('[data-confirm]')) {
                const src = urlInput.value.trim();
                close(src === '' ? null : { src, alt: altInput.value.trim() });
            }
        });

        modal.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close(null);
            }
        });

        fileInput.addEventListener('change', async () => {
            const file = fileInput.files && fileInput.files[0];
            if (!file) {
                return;
            }
            status.textContent = 'Uploading…';
            confirmBtn.disabled = true;
            fileInput.disabled = true;
            try {
                const url = await uploadInlineImage(file);
                close({ src: url, alt: altInput.value.trim() });
            } catch (error) {
                status.textContent = 'Upload failed. Try again, or paste a URL instead.';
                confirmBtn.disabled = false;
                fileInput.disabled = false;
            }
        });
    });
}
