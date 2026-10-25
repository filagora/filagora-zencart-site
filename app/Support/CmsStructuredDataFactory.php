<?php

namespace App\Support;

use Filagora\Cms\Contracts\PageUrlResolver;
use Filagora\Cms\Models\CmsPage;

class CmsStructuredDataFactory
{
    public function __construct(
        private readonly CmsPageViewResolver $viewResolver,
        private readonly StructuredDataPublisher $publisher,
        private readonly PageUrlResolver $pageUrl,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forPage(CmsPage $page): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => $this->typeFor($page),
            'headline' => $this->headlineFor($page),
            'description' => $this->descriptionFor($page),
            'url' => $this->pageUrl->urlFor($page),
        ];

        if ($page->published_at !== null) {
            $data['datePublished'] = $page->published_at->toIso8601String();
        }

        if ($page->updated_at !== null) {
            $data['dateModified'] = $page->updated_at->toIso8601String();
        }

        $data['publisher'] = $this->publisher->organization();

        return $data;
    }

    /**
     * Only plain editorial CMS pages (the generic page view, no custom route)
     * are an Article. Home, and every page with its own template or route
     * (donate, privacy, download, services...), is a WebPage: labelling
     * utility pages as articles weakens the markup.
     */
    private function typeFor(CmsPage $page): string
    {
        $isPlainEditorialPage = trim((string) ($page->custom_route ?? '')) === ''
            && $this->viewResolver->viewFor($page) === 'cms.page';

        return $isPlainEditorialPage ? 'Article' : 'WebPage';
    }

    private function headlineFor(CmsPage $page): string
    {
        return trim((string) ($page->seo_title ?: $page->title));
    }

    private function descriptionFor(CmsPage $page): string
    {
        return trim((string) ($page->seo_description ?: $page->excerpt ?: $page->title));
    }
}
