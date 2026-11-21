<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use Filagora\Cms\Contracts\PageUrlResolver;
use Filagora\Cms\Models\CmsNavigation;
use Filagora\Cms\Models\CmsPage;
use Filagora\Cms\Preview\PreviewShares;
use Filagora\Cms\Preview\PreviewUrl;
use Filagora\Cms\Services\PageDrafts;
use Filagora\Cms\Support\Abilities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Staff-only entry points for previewing a draft. A preview is carried by the address: these make
 * (or reuse) a link to the working draft and send the staff member to the tokened address, so the
 * same address without the token is the live page.
 */
class CmsPreviewSessionController extends Controller
{
    public function startNavigation(Request $request, CmsNavigation $navigation, PreviewShares $shares): RedirectResponse
    {
        abort_unless($this->mayPreview($request), 403);
        abort_if($navigation->draft()->doesntExist(), 404, 'This menu has no draft to preview.');

        $share = $shares->workingDraft($request->user(), navigations: [(string) $navigation->name]);

        return redirect()->to(PreviewUrl::with('/', $share->getRawOriginal('token')));
    }

    /**
     * Opens the page at its normal public URL (the draft's own slug or route if the draft changes
     * it) with the preview token, so links and menus lead on to other previewed items.
     */
    public function startPage(Request $request, CmsPage $page, PreviewShares $shares, PageDrafts $drafts, PageUrlResolver $urls): RedirectResponse
    {
        abort_unless($this->mayPreview($request), 403);
        abort_if($page->draft()->doesntExist(), 404, 'This page has no draft to preview.');

        $share = $shares->workingDraft($request->user(), pages: [(int) $page->getKey()]);

        return redirect()->to(PreviewUrl::with($urls->urlFor($drafts->previewPage($page)), $share->getRawOriginal('token')));
    }

    private function mayPreview(Request $request): bool
    {
        $gate = Gate::forUser($request->user());

        return $gate->allows(Abilities::MANAGE) || $gate->allows(Abilities::PREVIEW);
    }
}
