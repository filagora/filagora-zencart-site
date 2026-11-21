<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use Filagora\Cms\Contracts\PageUrlResolver;
use Filagora\Cms\Preview\PreviewShares;
use Filagora\Cms\Preview\PreviewUrl;
use Illuminate\Http\RedirectResponse;

/**
 * Public entry for preview links. Anyone holding a valid link is sent to the previewed address,
 * which carries the token; nothing is remembered about them and nothing is made public.
 */
class CmsPreviewShareController extends Controller
{
    public function open(string $token, PreviewShares $shares, PageUrlResolver $urls): RedirectResponse
    {
        $share = $shares->open($token);
        abort_if($share === null, 404);

        $landing = $shares->landingPage($share);
        $target = PreviewUrl::with($landing !== null ? $urls->urlFor($landing) : '/', $token);

        $response = redirect()->to($target);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
