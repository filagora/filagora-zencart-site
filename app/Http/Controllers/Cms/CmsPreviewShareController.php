<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use Filagora\Cms\Contracts\PageUrlResolver;
use Filagora\Cms\Preview\PreviewSession;
use Filagora\Cms\Preview\PreviewShares;
use Illuminate\Http\RedirectResponse;

/**
 * Public entry and exit for shared preview links. Anyone holding a valid link
 * sees the pinned drafts in their own browser session; nothing is made public.
 */
class CmsPreviewShareController extends Controller
{
    public function open(
        string $token,
        PreviewSession $preview,
        PreviewShares $shares,
        PageUrlResolver $urls,
    ): RedirectResponse {
        $share = $shares->open($token, $preview);
        abort_if($share === null, 404);

        $landing = $shares->landingPage($share);

        return $this->uncacheable(redirect()->to($landing !== null ? $urls->urlFor($landing) : '/'));
    }

    public function exit(PreviewSession $preview): RedirectResponse
    {
        $preview->clear();

        return $this->uncacheable(redirect()->to('/'));
    }

    private function uncacheable(RedirectResponse $response): RedirectResponse
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
