<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Support\CmsPageViewResolver;
use App\Support\CmsStructuredDataFactory;
use Filagora\Cms\Models\CmsPage;
use Filagora\Cms\Services\CmsContent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsPageController extends Controller
{
    public function __construct(
        private readonly CmsContent $cmsContent,
        private readonly CmsPageViewResolver $cmsPageViewResolver,
        private readonly CmsStructuredDataFactory $cmsStructuredDataFactory,
    ) {}

    public function show(string $slug, Request $request): View
    {
        $preview = $request->query('preview');
        $previewToken = is_string($preview) ? $preview : null;

        $page = $this->cmsContent->publishedPageBySlug($slug, $previewToken);

        abort_if($page === null, 404);

        return $this->renderPage($page);
    }

    public function showByCustomRoute(string $customRoute, Request $request): View
    {
        $preview = $request->query('preview');
        $previewToken = is_string($preview) ? $preview : null;

        $page = $this->cmsContent->publishedPageByCustomRoute('/'.$customRoute, $previewToken);

        abort_if($page === null, 404);

        return $this->renderPage($page);
    }

    public function listByTag(string $tagSlug): View
    {
        return view('cms.tag', [
            'tagSlug' => $tagSlug,
            'pages' => $this->cmsContent->publishedPages($tagSlug),
        ]);
    }

    private function renderPage(CmsPage $page): View
    {
        return view($this->cmsPageViewResolver->viewFor($page), [
            'page' => $page,
            'structuredData' => $this->cmsStructuredDataFactory->forPage($page),
        ]);
    }
}
