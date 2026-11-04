const section = document.querySelector('[data-bug-reports-section]');

if (section instanceof HTMLDetailsElement) {
    // The section starts collapsed, so a link to #bug-reports (e.g. from the
    // thread list indicator) must open it or it lands on a closed summary.
    const openWhenTargeted = () => {
        if (window.location.hash === '#bug-reports') {
            section.open = true;
            section.scrollIntoView();
        }
    };

    openWhenTargeted();
    window.addEventListener('hashchange', openWhenTargeted);
}
