<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiResponseMessage;
use Closure;
use Filagora\Compliance\Services\PolicyAcceptance;
use Filagora\Forums\Support\Models;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureFrontendEmailIsVerified
{
    public function __construct(private readonly PolicyAcceptance $policyAcceptance) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            if ($request->routeIs('messages.users.search') || $request->expectsJson()) {
                return response()->json(['message' => ApiResponseMessage::unauthenticated()], 401);
            }

            return redirect()->to($this->loginModalRedirectUrl($this->guestIntendedPath($request)));
        }

        if ($this->isBannedUser($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->routeIs('messages.users.search') || $request->expectsJson()) {
                return response()->json(['message' => ApiResponseMessage::accountBanned()], 403);
            }

            return redirect()->to($this->loginModalRedirectUrl($this->guestIntendedPath($request)));
        }

        if (method_exists($user, 'hasVerifiedEmail') && ! $user->hasVerifiedEmail()) {
            // Background fetch calls (e.g. the cookie-consent status check
            // that fires on every page load for any authenticated user) hit
            // this same branch. Redirecting + overwriting session
            // `url.intended` for those clobbers the path a real page
            // navigation was trying to preserve, so email verification's
            // final redirect ends up pointing at whatever AJAX endpoint
            // last polled through here instead of where the user actually
            // was. Only real page navigations should touch `url.intended`.
            if ($request->expectsJson()) {
                return response()->json(['message' => ApiResponseMessage::emailVerificationRequired()], 403);
            }

            $intended = $this->intendedPath($request);
            $request->session()->put('url.intended', $intended);

            return redirect()->route('verification.notice', [
                'intended' => $intended,
            ]);
        }

        if ($user instanceof User && $this->hasOutstandingPrivacyPolicyAcknowledgement($user)) {
            if (! $this->isPrivacyAcknowledgementRoute($request)) {
                $intended = $this->intendedPath($request);
                $request->session()->put('privacy_policy.intended', $intended);

                return redirect()->route('profile.show', ['tab' => 'privacy'])
                    ->with('profile_status', __('profile.flash.privacy_acknowledgement_required'))
                    ->with('privacy_ack_required', true);
            }
        }

        return $next($request);
    }

    private function guestIntendedPath(Request $request): string
    {
        $routeName = $request->route()?->getName();

        return match ($routeName) {
            'threads.reply', 'threads.subscribe', 'threads.unsubscribe' => route('threads.show', ['thread' => $request->route('thread')]),
            'threads.poll.create', 'threads.poll.store' => route('threads.show', ['thread' => $request->route('thread')]),
            'polls.vote', 'polls.moderate', 'polls.edit', 'polls.update' => $this->intendedThreadPathForPoll($request->route('poll')),
            'plugins.subscribe', 'plugins.unsubscribe', 'plugins.report' => $this->pathOnly(route('plugins.show', ['plugin' => $request->route('plugin')])),
            'posts.reactions.toggle' => $this->intendedThreadPathForPost($request->route('post')),
            'posts.report' => $this->intendedThreadPathForPost($request->route('post')),
            'plugins.store' => $this->pathOnly(route('plugins.submit')),
            'plugins.release.store' => $this->pathOnly(route('plugins.release.new', ['plugin' => $request->route('plugin')])),
            'profile.details.update',
            'profile.password.update',
            'profile.notifications.update',
            'profile.api-tokens.store',
            'profile.api-tokens.revoke',
            'profile.privacy-requests.store',
            'profile.privacy-requests.export.download',
            'profile.privacy-policy.accept',
            'profile.avatar.update',
            'profile.avatar.remove',
            'profile.2fa.setup',
            'profile.2fa.confirm',
            'profile.2fa.disable',
            'profile.subscriptions.remove',
            'profile.plugin-subscriptions.remove' => $this->pathOnly(route('profile.show')),
            default => $this->intendedPath($request),
        };
    }

    private function pathOnly(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = (string) parse_url($url, PHP_URL_QUERY);

        return $query === '' ? $path : $path.'?'.$query;
    }

    private function intendedPath(Request $request): string
    {
        $query = Arr::except($request->query(), ['auth', 'intended']);
        $path = '/'.ltrim($request->path(), '/');

        if ($query === []) {
            return $path;
        }

        return $path.'?'.http_build_query($query);
    }

    private function loginModalRedirectUrl(string $intended): string
    {
        return '/?auth=login&intended='.rawurlencode($intended);
    }

    private function intendedThreadPathForPost(mixed $postRouteParam): string
    {
        $threadId = null;

        $postClass = Models::class('post');

        if ($postRouteParam instanceof $postClass) {
            $threadId = (int) $postRouteParam->thread_id;
        } elseif (is_numeric($postRouteParam)) {
            $threadId = (int) ($postClass::query()
                ->whereKey((int) $postRouteParam)
                ->value('thread_id') ?? 0);
        }

        if (($threadId ?? 0) > 0) {
            return $this->pathOnly(route('threads.show', ['thread' => $threadId]));
        }

        return $this->pathOnly(route('forums.index'));
    }

    private function intendedThreadPathForPoll(mixed $pollRouteParam): string
    {
        $threadId = null;

        $pollClass = Models::class('poll');

        if ($pollRouteParam instanceof $pollClass) {
            $threadId = (int) $pollRouteParam->thread_id;
        } elseif (is_numeric($pollRouteParam)) {
            $threadId = (int) ($pollClass::query()
                ->whereKey((int) $pollRouteParam)
                ->value('thread_id') ?? 0);
        }

        if (($threadId ?? 0) > 0) {
            return $this->pathOnly(route('threads.show', ['thread' => $threadId]));
        }

        return $this->pathOnly(route('forums.index'));
    }

    private function isBannedUser(mixed $user): bool
    {
        return method_exists($user, 'isCurrentlyBanned')
            && $user->isCurrentlyBanned();
    }

    private function hasOutstandingPrivacyPolicyAcknowledgement(User $user): bool
    {
        return ! $this->policyAcceptance->hasAcknowledgedActivePolicy($user);
    }

    private function isPrivacyAcknowledgementRoute(Request $request): bool
    {
        if ($request->routeIs('profile.privacy-policy.accept')) {
            return true;
        }

        if (! $request->routeIs('profile.show')) {
            return false;
        }

        return (string) $request->query('tab', 'details') === 'privacy';
    }
}
