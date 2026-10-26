(() => {
    // Progressive enhancement for the front-of-site "Reported Posts" resolve
    // forms: hide the ban sub-fields until "Also ban" is ticked, and the
    // custom end-date until the "Custom date…" duration is chosen. Without JS
    // every field just stays visible and the server ignores the unused ones.
    const wire = (form) => {
        const banToggle = form.querySelector('input[name="ban"]');
        const banBlock = banToggle?.closest('div');
        const durationSelect = form.querySelector('select[name="ban_duration"]');
        const untilField = form.querySelector('input[name="ban_until"]')?.closest('label');

        const banExtras = banBlock
            ? Array.from(banBlock.children).filter((child) => !child.contains(banToggle))
            : [];

        const sync = () => {
            const banOn = Boolean(banToggle?.checked);
            banExtras.forEach((el) => {
                el.hidden = !banOn;
            });
            if (untilField instanceof HTMLElement) {
                untilField.hidden = !banOn || durationSelect?.value !== 'custom';
            }
        };

        banToggle?.addEventListener('change', sync);
        durationSelect?.addEventListener('change', sync);
        sync();
    };

    document.querySelectorAll('[data-report-resolve-form]').forEach(wire);
})();
