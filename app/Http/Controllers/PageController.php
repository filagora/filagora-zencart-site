<?php

namespace App\Http\Controllers;

use App\Support\CmsPageViewResolver;
use App\Support\CmsStructuredDataFactory;
use Filagora\Cms\Services\CmsContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewResponse;

class PageController extends Controller
{
    public function __construct(
        private readonly CmsContent $cmsContent,
        private readonly CmsPageViewResolver $cmsPageViewResolver,
        private readonly CmsStructuredDataFactory $cmsStructuredDataFactory,
    ) {}

    /**
     * $page defaults to '' deliberately: a request like /%2f (decodes to a
     * bare slash) can make Symfony's router match this route while capturing
     * zero parameters at all (confirmed via tinker - not even a null `page`
     * key). Illuminate\Routing\ResolvesRouteDependencies::resolveMethodDependencies()
     * only splices in a value for an unbound scalar parameter when it has a
     * default to fall back on; without one it's silently dropped, and the
     * resulting argument-count mismatch throws an ArgumentCountError (500)
     * instead of handling the request normally. An empty $page resolves
     * '/' as the custom route, same as a bare "/" would.
     */
    public function show(Request $request, string $page = ''): ViewResponse
    {
        $customRoute = '/'.$page;
        $preview = $request->query('preview');
        $previewToken = is_string($preview) ? $preview : null;

        $cmsPage = $this->cmsContent->publishedPageByCustomRoute($customRoute, $previewToken);

        if ($cmsPage !== null) {
            return view($this->cmsPageViewResolver->viewFor($cmsPage), [
                'page' => $cmsPage,
                'structuredData' => $this->cmsStructuredDataFactory->forPage($cmsPage),
            ]);
        }

        $viewName = 'pages.'.str_replace('/', '.', $page);

        abort_unless(View::exists($viewName), 404);

        return view($viewName);
    }
}
