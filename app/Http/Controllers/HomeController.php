<?php

namespace App\Http\Controllers;

use App\Support\CmsPageViewResolver;
use App\Support\CmsStructuredDataFactory;
use Filagora\Cms\Services\CmsContent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        private readonly CmsContent $cmsContent,
        private readonly CmsPageViewResolver $cmsPageViewResolver,
        private readonly CmsStructuredDataFactory $cmsStructuredDataFactory,
    ) {}

    public function index(Request $request): View
    {
        $preview = $request->query('preview');
        $previewToken = is_string($preview) ? $preview : null;

        $page = $this->cmsContent->publishedPageByCustomRoute('/', $previewToken);

        abort_if($page === null, 404);

        return view($this->cmsPageViewResolver->viewFor($page), [
            'page' => $page,
            'structuredData' => $this->cmsStructuredDataFactory->forPage($page),
        ]);
    }
}
