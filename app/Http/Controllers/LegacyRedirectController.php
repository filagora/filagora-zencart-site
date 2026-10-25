<?php

namespace App\Http\Controllers;

use App\Services\LegacyUrlResolver;
use App\Support\LegacyRedirectResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class LegacyRedirectController extends Controller
{
    public function __construct(
        private readonly LegacyUrlResolver $resolver,
    ) {}

    public function showThread(Request $request, ?string $legacyPath = null): RedirectResponse|Response
    {
        if ($response = $this->rejectStyleId($request, 'showthread.php')) {
            return $response;
        }

        return $this->respond($request, $this->resolver->resolveShowThread($request, $legacyPath));
    }

    public function showPost(Request $request, ?string $legacyPath = null): RedirectResponse|Response
    {
        if ($response = $this->rejectStyleId($request, 'showpost.php')) {
            return $response;
        }

        return $this->respond($request, $this->resolver->resolveShowPost($request, $legacyPath));
    }

    public function forumDisplay(Request $request, ?string $legacyPath = null): RedirectResponse|Response
    {
        if ($response = $this->rejectStyleId($request, 'forumdisplay.php')) {
            return $response;
        }

        return $this->respond($request, $this->resolver->resolveForumDisplay($request, $legacyPath));
    }

    public function search(Request $request): RedirectResponse|Response
    {
        if ($response = $this->rejectStyleId($request, 'search.php')) {
            return $response;
        }

        return $this->respond($request, $this->resolver->resolveSearch($request));
    }

    public function downloads(Request $request): RedirectResponse|Response|JsonResponse
    {
        if ($response = $this->rejectStyleId($request, 'downloads.php')) {
            return $response;
        }

        // Zen Cart v1.5.4 and older call this URL for the plugin version check and the v1.5.5/1.5.6
        // curl client never follows redirects, so it must be answered here, not redirected. The
        // answer comes from the Zen Cart profile's version-check endpoint (profile slice 8c), which
        // is not released yet: until it is, this URL must not be cut over to.
        if (trim((string) $request->query('do', '')) === 'versioncheck') {
            abort(404);
        }

        return $this->respond($request, $this->resolver->resolveDownloads($request));
    }

    public function content(Request $request, ?string $legacyPath = null): RedirectResponse|Response
    {
        if ($response = $this->rejectStyleId($request, 'content.php')) {
            return $response;
        }

        return $this->respond($request, $this->resolver->resolveContent($request, $legacyPath));
    }

    public function faq(Request $request): RedirectResponse|Response
    {
        if ($response = $this->rejectStyleId($request, 'faq.php')) {
            return $response;
        }

        return $this->respond($request, $this->resolver->resolveFaq());
    }

    public function sendMessage(Request $request, ?string $legacyPath = null): RedirectResponse|Response
    {
        if ($response = $this->rejectStyleId($request, 'sendmessage.php')) {
            return $response;
        }

        return $this->respond($request, $this->resolver->resolveSendMessage($request, $legacyPath));
    }

    public function misc(Request $request): RedirectResponse|Response
    {
        if ($response = $this->rejectStyleId($request, 'misc.php')) {
            return $response;
        }

        return $this->respond($request, $this->resolver->resolveMisc($request));
    }

    public function wiki(Request $request, ?string $legacyPath = null): RedirectResponse|Response
    {
        return $this->respond($request, $this->resolver->resolveWiki($legacyPath));
    }

    public function member(Request $request, ?string $legacyPath = null): RedirectResponse|Response
    {
        if ($response = $this->rejectStyleId($request, 'member.php')) {
            return $response;
        }

        return $this->respond($request, $this->resolver->resolveMember($request, $legacyPath));
    }

    public function printThread(Request $request, ?string $legacyPath = null): RedirectResponse|Response
    {
        if ($response = $this->rejectStyleId($request, 'printthread.php')) {
            return $response;
        }

        return $this->respond($request, $this->resolver->resolvePrintThread($request, $legacyPath));
    }

    public function newReply(Request $request, ?string $legacyPath = null): RedirectResponse|Response
    {
        if ($response = $this->rejectStyleId($request, 'newreply.php')) {
            return $response;
        }

        return $this->respond($request, $this->resolver->resolveNewReply($request, $legacyPath));
    }

    public function external(Request $request, ?string $legacyPath = null): RedirectResponse|Response
    {
        if ($response = $this->rejectStyleId($request, 'external.php')) {
            return $response;
        }

        return $this->respond($request, $this->resolver->resolveExternal($request, $legacyPath));
    }

    public function showcase(Request $request): RedirectResponse|Response
    {
        if ($response = $this->rejectStyleId($request, 'showcase.php')) {
            return $response;
        }

        return $this->respond($request, $this->resolver->resolveShowcase());
    }

    public function blog(Request $request, ?string $legacyPath = null): RedirectResponse|Response
    {
        if ($response = $this->rejectStyleId($request, 'blog.php')) {
            return $response;
        }

        return $this->respond($request, $this->resolver->resolveBlog($request, $legacyPath));
    }

    public function entry(Request $request, ?string $legacyPath = null): RedirectResponse|Response
    {
        if ($response = $this->rejectStyleId($request, 'entry.php')) {
            return $response;
        }

        return $this->respond($request, $this->resolver->resolveEntry($request, $legacyPath));
    }

    public function private(Request $request, ?string $legacyPath = null): RedirectResponse|Response
    {
        if ($response = $this->rejectStyleId($request, 'private.php')) {
            return $response;
        }

        return $this->respond($request, $this->resolver->resolvePrivate($request, $legacyPath));
    }

    private function rejectStyleId(Request $request, string $pattern): ?Response
    {
        if (! $request->query->has('styleid')) {
            return null;
        }

        $this->logRedirect([
            'input_url' => $request->fullUrl(),
            'resolution_outcome' => 'gone',
            'status_code' => 410,
            'target_url' => null,
            'matched_pattern' => $pattern,
            'lookup_key' => 'styleid',
            'reason' => 'styleid_rejected',
        ]);

        return response('', 410);
    }

    private function respond(Request $request, LegacyRedirectResult $result): RedirectResponse|Response
    {
        $this->logRedirect([
            'input_url' => $request->fullUrl(),
            'resolution_outcome' => $result->outcome,
            'status_code' => $result->statusCode,
            'target_url' => $result->targetUrl,
        ] + $result->context);

        return match ($result->outcome) {
            'redirect' => redirect()->to($result->targetUrl ?? '/', $result->statusCode),
            'gone' => response('', 410),
            default => abort(404),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logRedirect(array $context): void
    {
        if (! config('services.legacy_redirect.logging_enabled')) {
            return;
        }

        Log::info('legacy.redirect', $context);
    }
}
