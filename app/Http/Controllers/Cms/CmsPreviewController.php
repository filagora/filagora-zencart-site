<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Support\CmsPageViewResolver;
use App\Support\CmsStructuredDataFactory;
use Filagora\Cms\Models\CmsBlock;
use Filagora\Cms\Models\CmsPage;
use Filagora\Cms\Services\PageDrafts;
use Filagora\Cms\Support\Abilities;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Staff-only rendering of unpublished CMS drafts on the real site templates.
 */
class CmsPreviewController extends Controller
{
    public function page(
        Request $request,
        CmsPage $page,
        PageDrafts $drafts,
        CmsPageViewResolver $viewResolver,
        CmsStructuredDataFactory $structuredData,
    ): Response {
        abort_unless($this->mayPreview($request), 403);

        $preview = $drafts->previewPage($page);
        $hasDraft = $page->draft()->exists();

        $html = view($viewResolver->viewFor($preview), [
            'page' => $preview,
            'structuredData' => $structuredData->forPage($preview),
        ])->render();

        return $this->respond($html, $hasDraft
            ? 'Preview: unpublished changes to this page. Visitors still see the published version.'
            : 'Preview: this page has no unpublished changes.');
    }

    public function block(Request $request, CmsBlock $block): Response
    {
        abort_unless($this->mayPreview($request), 403);

        $staged = $block->draftCopy()->first();

        $html = view('cms.preview-block', [
            'block' => $staged ?? $block,
        ])->render();

        return $this->respond($html, $staged !== null
            ? 'Preview: unpublished changes to this block.'
            : 'Preview: this block has no unpublished changes.');
    }

    private function respond(string $html, string $banner): Response
    {
        $bar = '<div style="position:sticky;top:0;z-index:99999;background:#7c2d12;color:#fff;'
            .'padding:8px 16px;font:600 14px/1.4 system-ui,sans-serif;text-align:center">'
            .e($banner).'</div>';

        $html = preg_replace('/<body[^>]*>/i', '$0'.$bar, $html, 1) ?? $html;

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function mayPreview(Request $request): bool
    {
        $gate = Gate::forUser($request->user());

        return $gate->allows(Abilities::MANAGE) || $gate->allows(Abilities::PREVIEW);
    }
}
