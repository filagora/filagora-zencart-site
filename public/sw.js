/* Service worker: Web Push notifications only.
 *
 * Deliberately minimal - no offline caching / precache. Its one job is to
 * receive push messages and turn them into OS notifications, and to route a
 * click to the right page.
 *
 * Payload shape (see App\Notifications\Concerns\* toWebPush()):
 *   { title, body, icon, badge, tag, data: { url } }
 */

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (e) {
        payload = { title: 'Zen Cart', body: event.data ? event.data.text() : '' };
    }

    const title = payload.title || 'Zen Cart';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/assets/img/icon-192.png',
        badge: payload.badge || '/assets/img/badge-72.png',
        tag: payload.tag || undefined,
        renotify: Boolean(payload.tag),
        data: { url: (payload.data && payload.data.url) || '/' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            return self.clients.openWindow(url);
        }),
    );
});

/* Chrome/Firefox rotate the endpoint occasionally; re-subscribe and tell the
 * server. The page also re-syncs on load, this covers the closed-tab case. */
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(
        (async () => {
            try {
                const oldEndpoint = event.oldSubscription && event.oldSubscription.endpoint;
                if (!oldEndpoint) {
                    // Without the prior endpoint the server can't tell this
                    // apart from a forged request (see resync's CSRF-exemption
                    // note in bootstrap/app.php) and will reject it anyway -
                    // skip the round trip. The next page load re-syncs instead.
                    return;
                }

                const applicationServerKey = event.oldSubscription.options
                    && event.oldSubscription.options.applicationServerKey;

                const subscription = await self.registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey,
                });

                // toJSON() omits the content encoding; send it explicitly so a
                // rotated subscription is not stored with a null encoding (which
                // Firefox silently drops). Hits the CSRF-exempt resync route -
                // this background context has no page and no token - so
                // old_endpoint stands in for the token, proving this caller
                // already knows a subscription on file for this account.
                const payload = {
                    ...subscription.toJSON(),
                    old_endpoint: oldEndpoint,
                    content_encoding: (self.PushManager && PushManager.supportedContentEncodings
                        && PushManager.supportedContentEncodings[0]) || 'aes128gcm',
                };

                await fetch('/push/subscriptions/resync', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });
            } catch (e) {
                /* best effort - the next page load re-syncs */
            }
        })(),
    );
});
