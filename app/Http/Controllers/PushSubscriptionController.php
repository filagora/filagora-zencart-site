<?php

namespace App\Http\Controllers;

use App\Models\User;
use Filagora\Push\Services\PushSubscriptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A browser's push registration for the signed-in member: saved when they switch push on, moved when
 * the browser rotates it, and removed when they switch it off. Which services an endpoint may belong
 * to is the push package's rule.
 */
class PushSubscriptionController extends Controller
{
    public function __construct(private readonly PushSubscriptions $subscriptions) {}

    /**
     * Page-driven and CSRF-protected.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $this->subscriptions->store(
            $this->member($request),
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['content_encoding'] ?? 'aes128gcm',
            Str::limit((string) $request->userAgent(), 500, ''),
        );

        return response()->json(['ok' => true]);
    }

    /**
     * The service worker's `pushsubscriptionchange` handler (public/sw.js) posts here from a
     * background context with no page and no CSRF token, so the route is exempt from CSRF. The
     * session cookie alone can be forged across sites, so the request must also name the endpoint it
     * is rotating from: only a caller whose browser already holds an endpoint on file for this member
     * gets through. Without that, a forged request could plant an attacker's endpoint and keys on a
     * victim's account and quietly receive their notifications.
     */
    public function resync(Request $request): JsonResponse
    {
        $data = $this->validated($request, ['old_endpoint' => ['required', 'string', 'max:1024']]);
        $user = $this->member($request);

        if (! $this->subscriptions->for($user)->contains('endpoint', $data['old_endpoint'])) {
            return response()->json(['ok' => false], 422);
        }

        $this->subscriptions->resync(
            $user,
            $data['old_endpoint'],
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['content_encoding'] ?? 'aes128gcm',
            Str::limit((string) $request->userAgent(), 500, ''),
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate(['endpoint' => ['required', 'string', 'max:1024']]);

        $this->subscriptions->remove($this->member($request), $validated['endpoint']);

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{endpoint: string, keys: array{p256dh: string, auth: string}, content_encoding?: ?string, old_endpoint?: string}
     */
    private function validated(Request $request, array $extra = []): array
    {
        return $request->validate([
            'endpoint' => ['required', 'string', 'max:1024', 'url', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->subscriptions->isAllowedEndpoint((string) $value)) {
                    $fail('That is not a browser push service this site can send to.');
                }
            }],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
            ...$extra,
        ]);
    }

    private function member(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
