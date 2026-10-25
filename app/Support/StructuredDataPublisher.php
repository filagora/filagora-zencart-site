<?php

namespace App\Support;

class StructuredDataPublisher
{
    /**
     * The site as a schema.org Organization: the same name, home URL and logo
     * the site header already shows, so nothing here is invented.
     *
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        return [
            '@type' => 'Organization',
            'name' => (string) config('app.name'),
            'url' => route('home'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => asset('assets/images/logo/zen-cart-logo.png'),
            ],
        ];
    }

    /**
     * A BreadcrumbList for a page's trail, outermost first.
     *
     * @param  list<array{name: string, url: string}>  $trail
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $trail): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($trail)
                ->values()
                ->map(fn (array $crumb, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $crumb['name'],
                    'item' => $crumb['url'],
                ])
                ->all(),
        ];
    }
}
