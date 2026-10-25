<?php

namespace App\Support;

use Filagora\Cms\Models\CmsPage;

class CmsPageViewResolver
{
    public function viewFor(CmsPage $page): string
    {
        $customRoute = trim((string) ($page->custom_route ?? ''));
        $slug = trim((string) $page->slug);

        return match (true) {
            $customRoute === '/', $slug === 'home' => 'cms.templates.home',
            $customRoute === '/privacy', $slug === 'privacy' => 'cms.templates.privacy',
            $customRoute === '/compare', $slug === 'compare' => 'cms.templates.compare',
            $customRoute === '/download', $slug === 'download' => 'cms.templates.download',
            $customRoute === '/services', $slug === 'services' => 'cms.templates.services',
            str_starts_with($customRoute, '/services/') => 'cms.templates.service-detail',
            $customRoute === '/about', $slug === 'about' => 'cms.templates.about',
            $customRoute === '/donate', $slug === 'donate' => 'cms.templates.donate',
            default => 'cms.page',
        };
    }
}
