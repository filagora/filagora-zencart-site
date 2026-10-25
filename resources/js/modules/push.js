/* Web Push client.
 *
 * Registers the service worker for every signed-in visitor and keeps the
 * server's copy of this device's subscription fresh. The opt-in itself is
 * driven from Profile Settings via the `window.zcPush` API exposed here.
 *
 * The passive re-sync at the bottom used to POST on every page load. On
 * 18 Sep 2026 a member unsubscribing from ~960 threads one click at a time
 * (each click a full page load) crossed the route's 20/min throttle, and the
 * resulting 429s got him firewall-banned. The server only needs the row to
 * exist and `last_used_at` to beat a 180-day prune, so a sync per endpoint
 * per day is plenty; the marker below is what enforces that.
 */

const STORE_URL = '/push/subscriptions';
const SW_URL = '/sw.js';
const SYNC_MARKER_KEY = 'zcPush:synced';
const SYNC_INTERVAL_MS = 24 * 60 * 60 * 1000;

const supported = 'serviceWorker' in navigator
    && 'PushManager' in window
    && 'Notification' in window;

const metaContent = (name) => document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? '';

const isAuthenticated = () => metaContent('forum-authenticated') === '1';
const vapidPublicKey = () => metaContent('vapid-public-key');
const csrfToken = () => metaContent('csrf-token');

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i += 1) {
        output[i] = raw.charCodeAt(i);
    }
    return output;
}

async function readyRegistration() {
    if (!supported) {
        return null;
    }
    await navigator.serviceWorker.register(SW_URL);
    return navigator.serviceWorker.ready;
}

async function currentSubscription() {
    const registration = await readyRegistration();
    return registration ? registration.pushManager.getSubscription() : null;
}

/* Last successful sync, as { endpoint, at }. localStorage can throw (private
 * mode, storage disabled) and the value can be garbage; either way the answer
 * is "not synced", which just means one extra POST. */
function readSyncMarker() {
    try {
        const raw = window.localStorage.getItem(SYNC_MARKER_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch (e) {
        return null;
    }
}

function writeSyncMarker(endpoint) {
    try {
        window.localStorage.setItem(SYNC_MARKER_KEY, JSON.stringify({ endpoint, at: Date.now() }));
    } catch (e) {
        /* best effort */
    }
}

function clearSyncMarker() {
    try {
        window.localStorage.removeItem(SYNC_MARKER_KEY);
    } catch (e) {
        /* best effort */
    }
}

function syncedRecently(endpoint) {
    const marker = readSyncMarker();
    if (!marker || marker.endpoint !== endpoint || typeof marker.at !== 'number') {
        return false;
    }
    const age = Date.now() - marker.at;
    // A negative age means the clock moved backwards; treat it as stale.
    return age >= 0 && age < SYNC_INTERVAL_MS;
}

async function postSubscription(subscription) {
    // toJSON() carries endpoint + keys but not the content encoding; send it
    // explicitly so the server stores aes128gcm rather than null.
    const payload = {
        ...subscription.toJSON(),
        content_encoding: (window.PushManager && PushManager.supportedContentEncodings
            && PushManager.supportedContentEncodings[0]) || 'aes128gcm',
    };

    const response = await fetch(STORE_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });
    if (!response.ok) {
        throw new Error(`push subscribe failed: ${response.status}`);
    }
    writeSyncMarker(subscription.endpoint);
}

async function deleteSubscription(endpoint) {
    await fetch(STORE_URL, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ endpoint }),
    });
}

const api = {
    get supported() {
        return supported;
    },
    get permission() {
        return supported ? Notification.permission : 'unsupported';
    },
    async isSubscribed() {
        const subscription = await currentSubscription();
        return subscription !== null;
    },
    /** Ask for permission (if needed), subscribe, and register with the server. */
    async subscribe() {
        if (!supported) {
            throw new Error('unsupported');
        }
        const key = vapidPublicKey();
        if (!key) {
            throw new Error('missing-vapid-key');
        }

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            throw new Error('permission-denied');
        }

        const registration = await readyRegistration();
        let subscription = await registration.pushManager.getSubscription();
        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(key),
            });
        }

        await postSubscription(subscription);
        return true;
    },
    /** Unsubscribe this device and drop it server-side. */
    async unsubscribe() {
        const subscription = await currentSubscription();
        if (!subscription) {
            return true;
        }
        const { endpoint } = subscription;
        await subscription.unsubscribe().catch(() => {});
        clearSyncMarker();
        await deleteSubscription(endpoint);
        return true;
    },
};

window.zcPush = api;

/* Passive re-sync: keep last_used_at fresh and re-create a row the server may
 * have pruned, but never prompt for permission here. At most once a day per
 * endpoint (see the header); a rotated endpoint misses the marker and syncs
 * straight away. */
if (supported && isAuthenticated() && vapidPublicKey()) {
    (async () => {
        try {
            const subscription = await currentSubscription();
            if (subscription && Notification.permission === 'granted' && !syncedRecently(subscription.endpoint)) {
                await postSubscription(subscription);
            }
        } catch (e) {
            /* best effort */
        }
    })();
}
