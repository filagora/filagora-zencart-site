<?php

namespace App\Http\Controllers;

use App\Models\User;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Services\ForumAccess;
use Filagora\Forums\Support\Models;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The notification bell's pages and actions: the full list, opening one (which marks it read and
 * forwards to what it points at), and clearing.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->member($request);
        $onlyUnread = $request->query('filter') === 'unread';

        return view('notifications.index', [
            'notifications' => ($onlyUnread ? $user->unreadNotifications() : $user->notifications())->paginate(30)->withQueryString(),
            'unreadCount' => $user->unreadNotifications()->count(),
            'onlyUnread' => $onlyUnread,
        ]);
    }

    /**
     * Marks one read and forwards to its target. A GET so it can be a plain link in the bell and in
     * mail.
     */
    public function open(Request $request, string $notification, ForumAccess $access): RedirectResponse
    {
        $user = $this->member($request);
        $record = $user->notifications()->findOrFail($notification);
        $record->markAsRead();

        $data = is_array($record->data) ? $record->data : [];
        $url = (string) ($data['url'] ?? '');

        // A notification can outlive the member's access to what it points at (a forum locked down
        // after it was sent). Following it would be a bare 403.
        $threadId = $this->threadIdFor($data, $url);
        $thread = $threadId !== null ? Models::class('thread')::query()->with('forum')->find($threadId) : null;

        if ($thread instanceof Thread && ! $access->canReadThread($thread, $user)) {
            return redirect()->route('notifications.index')->with('profile_status', __('notifications.bell.unavailable'));
        }

        return redirect()->to($this->safeTarget($url));
    }

    /**
     * The thread a notification links into: its stored `thread_id`, or, for one stored before that
     * was recorded, the id in a `/threads/{id}` URL.
     *
     * @param  array<string, mixed>  $data
     */
    private function threadIdFor(array $data, string $url): int|string|null
    {
        if (isset($data['thread_id']) && is_scalar($data['thread_id']) && $data['thread_id'] !== '') {
            return $data['thread_id'];
        }

        return preg_match('#^(?:https?://[^/]+)?/threads/(\d+)(?:[/?\#]|$)#', $url, $matches) === 1 ? (int) $matches[1] : null;
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->member($request)->unreadNotifications->markAsRead();

        return redirect()->back()->with('profile_status', __('notifications.bell.all_read'));
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $this->member($request)->notifications()->whereKey($notification)->delete();

        return redirect()->back()->with('profile_status', __('notifications.bell.removed'));
    }

    /**
     * Deletes everything the member has, read or not. The bell lists only the latest few, so its
     * form asks first.
     */
    public function clearAll(Request $request): RedirectResponse
    {
        $this->member($request)->notifications()->delete();

        return redirect()->back()->with('profile_status', __('notifications.bell.cleared_all'));
    }

    public function clearRead(Request $request): RedirectResponse
    {
        $this->member($request)->notifications()->whereNotNull('read_at')->delete();

        return redirect()->route('notifications.index')->with('profile_status', __('notifications.bell.cleared_read'));
    }

    private function member(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Reduces a stored URL to a target on this site, or the bell index. A bare "starts with /" test
     * lets `//evil.example` through and a prefix test lets `https://<host>.evil.example` through, so
     * the URL is parsed and its host compared exactly.
     */
    private function safeTarget(string $url): string
    {
        $fallback = route('notifications.index');
        $parts = $url === '' ? false : parse_url($url);

        if ($parts === false) {
            return $fallback;
        }

        $path = (string) ($parts['path'] ?? '');
        $tail = $path.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');

        if (isset($parts['host'])) {
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));

            if ($appHost === null || strcasecmp($parts['host'], (string) $appHost) !== 0 || ! in_array($scheme, ['http', 'https'], true)) {
                return $fallback;
            }

            return $tail !== '' ? $tail : $fallback;
        }

        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_starts_with($path, '/\\')) {
            return $fallback;
        }

        return $tail;
    }
}
