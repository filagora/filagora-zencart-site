<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use Filagora\Cms\Contracts\PageUrlResolver;
use Filagora\Cms\Models\CmsNavigation;
use Filagora\Cms\Models\CmsPage;
use Filagora\Cms\Preview\PreviewSession;
use Filagora\Cms\Services\NavigationDrafts;
use Filagora\Cms\Services\PageDrafts;
use Filagora\Cms\Support\Abilities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Staff-only controls for the preview session: add a draft menu or page to it,
 * take one out, or leave it.
 */
class CmsPreviewSessionController extends Controller
{
    public function startNavigation(Request $request, CmsNavigation $navigation, PreviewSession $preview): RedirectResponse
    {
        abort_unless($this->mayPreview($request), 403);
        abort_if($navigation->draft()->doesntExist(), 404, 'This menu has no draft to preview.');

        $preview->addNavigation($navigation);

        return redirect()->to('/');
    }

    /**
     * Adds the page to the preview and opens it at its normal public URL (the
     * draft's own slug or route if the draft changes it), so links and menus
     * lead on to other previewed items.
     */
    public function startPage(Request $request, CmsPage $page, PreviewSession $preview, PageDrafts $drafts, PageUrlResolver $urls): RedirectResponse
    {
        abort_unless($this->mayPreview($request), 403);
        abort_if($page->draft()->doesntExist(), 404, 'This page has no draft to preview.');

        $preview->addPage($page);

        return redirect()->to($urls->urlFor($drafts->previewPage($page)));
    }

    /**
     * Makes another draft variant of a previewed menu or page the working draft.
     * This changes the draft the editor shows, so it needs edit access, not just preview.
     */
    public function switchVariant(
        Request $request,
        PreviewSession $preview,
        NavigationDrafts $navigationDrafts,
        PageDrafts $pageDrafts,
        PageUrlResolver $urls,
    ): RedirectResponse {
        $validated = $request->validate([
            'type' => ['required', 'in:menu,page'],
            'key' => ['required', 'string', 'max:255'],
            'variant' => ['required', 'string', 'max:100'],
        ]);

        if ($validated['type'] === 'menu') {
            $navigation = CmsNavigation::query()->where('name', $validated['key'])->firstOrFail();
            abort_unless(Gate::forUser($request->user())->allows(Abilities::MANAGE), 403);
            $navigationDrafts->switchVariant($navigation, $validated['variant'], $request->user());
        } else {
            $page = CmsPage::query()->findOrFail((int) $validated['key']);
            abort_unless(Gate::forUser($request->user())->allows(Abilities::MANAGE), 403);
            $pageDrafts->switchVariant($page, $validated['variant'], $request->user());

            // A variant may change the page's slug or route, so follow it rather than the old URL.
            return redirect()->to($urls->urlFor($pageDrafts->previewPage($page)));
        }

        return redirect()->to($this->safeReturnPath($request));
    }

    public function remove(Request $request, PreviewSession $preview): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:menu,page'],
            'key' => ['required', 'string', 'max:255'],
        ]);

        $preview->remove($validated['type'], $validated['key']);

        return redirect()->to($this->safeReturnPath($request));
    }

    public function stop(Request $request, PreviewSession $preview): RedirectResponse
    {
        $preview->clear();

        return redirect()->to($this->safeReturnPath($request));
    }

    /**
     * Only ever go back to a path on this site.
     */
    private function safeReturnPath(Request $request): string
    {
        $return = (string) $request->input('return', '/');

        return str_starts_with($return, '/') && ! str_starts_with($return, '//') ? $return : '/';
    }

    private function mayPreview(Request $request): bool
    {
        $gate = Gate::forUser($request->user());

        return $gate->allows(Abilities::MANAGE) || $gate->allows(Abilities::PREVIEW);
    }
}
