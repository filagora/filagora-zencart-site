/* Profile Settings > Browser Notifications.
 *
 * Drives the enable/disable buttons off window.zcPush (see modules/push.js) and
 * keeps the per-category checkbox form locked until this browser is actually
 * subscribed. The category choices submit as a normal POST form.
 */

const root = document.querySelector('[data-push-settings]');

if (root && window.zcPush) {
    const push = window.zcPush;
    const stateEl = root.querySelector('[data-push-state]');
    const enableBtn = root.querySelector('[data-push-enable]');
    const disableBtn = root.querySelector('[data-push-disable]');
    const lockedNote = root.querySelector('[data-push-categories-locked]');
    const categoryForm = root.querySelector('[data-push-category-form]');
    const categoryPanel = root.querySelector('[data-push-categories-panel]');
    const stateDot = root.querySelector('[data-push-state-dot]');
    const checkboxes = Array.from(root.querySelectorAll('[data-push-category-checkbox]'));
    const saveBtn = root.querySelector('[data-push-category-save]');

    const text = (name) => root.getAttribute(`data-push-text-${name}`) ?? '';

    const DOT_ON = 'bg-emerald-600';
    const DOT_OFF = 'bg-stone-400';
    const DOT_BLOCKED = 'bg-amber-600';

    const setDot = (colorClass) => {
        if (!stateDot) return;
        stateDot.classList.remove(DOT_ON, DOT_OFF, DOT_BLOCKED);
        stateDot.classList.add(colorClass);
    };

    const setLocked = (locked) => {
        checkboxes.forEach((box) => { box.disabled = locked; });
        if (saveBtn) saveBtn.disabled = locked;
        if (lockedNote) lockedNote.hidden = !locked;
        categoryPanel?.classList.toggle('opacity-60', locked);
    };

    const render = async () => {
        if (!push.supported) {
            if (stateEl) stateEl.textContent = text('unsupported');
            setDot(DOT_BLOCKED);
            enableBtn?.setAttribute('hidden', '');
            disableBtn?.setAttribute('hidden', '');
            setLocked(true);
            return;
        }

        if (push.permission === 'denied') {
            if (stateEl) stateEl.textContent = text('denied');
            setDot(DOT_BLOCKED);
            enableBtn?.setAttribute('hidden', '');
            disableBtn?.setAttribute('hidden', '');
            setLocked(true);
            return;
        }

        let subscribed = false;
        try {
            subscribed = await push.isSubscribed();
        } catch (e) {
            subscribed = false;
        }

        if (stateEl) stateEl.textContent = subscribed ? text('on') : text('off');
        setDot(subscribed ? DOT_ON : DOT_OFF);
        enableBtn?.toggleAttribute('hidden', subscribed);
        disableBtn?.toggleAttribute('hidden', !subscribed);
        setLocked(!subscribed);
    };

    enableBtn?.addEventListener('click', async () => {
        enableBtn.disabled = true;
        try {
            await push.subscribe();
        } catch (e) {
            /* permission denied / unsupported - render() reflects it */
        }
        enableBtn.disabled = false;
        render();
    });

    disableBtn?.addEventListener('click', async () => {
        disableBtn.disabled = true;
        try {
            await push.unsubscribe();
        } catch (e) {
            /* ignore */
        }
        disableBtn.disabled = false;
        render();
    });

    // Don't let the category form submit before a subscription exists - the
    // choices would be saved but nothing could deliver them.
    categoryForm?.addEventListener('submit', (event) => {
        if (checkboxes.some((box) => box.disabled)) {
            event.preventDefault();
        }
    });

    render();
}
