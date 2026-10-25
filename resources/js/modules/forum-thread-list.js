(() => {
    const attachmentLinks = Array.from(document.querySelectorAll('[data-thread-attachments-popup]'));
    if (attachmentLinks.length === 0) {
        return;
    }

    attachmentLinks.forEach((link) => {
        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }

        link.addEventListener('click', (event) => {
            if (
                event.defaultPrevented
                || event.button !== 0
                || event.metaKey
                || event.ctrlKey
                || event.shiftKey
                || event.altKey
            ) {
                return;
            }

            const popup = window.open(
                link.href,
                `thread-attachments-${link.closest('[data-thread-id]')?.getAttribute('data-thread-id') ?? 'listing'}`,
                'popup=yes,width=760,height=620,resizable=yes,scrollbars=yes'
            );

            if (popup === null) {
                return;
            }

            event.preventDefault();
            popup.focus();
        });
    });
})();
