<?php

namespace Database\Seeders;

use Filagora\Cms\Enums\CmsPageStatus;
use Filagora\Cms\Models\CmsPage;
use Illuminate\Database\Seeder;

/**
 * The published CMS pages the site's routes and templates are built around, as bare shells: title,
 * slug and route, no blocks. Production already has these pages with their real content, so this is
 * for local, staging and test databases only and never runs there. A page that exists is left alone.
 */
class SitePagesSeeder extends Seeder
{
    /**
     * @var array<string, array{route: string, title: string}>
     */
    public const PAGES = [
        'home' => ['route' => '/', 'title' => 'Zen Cart Support'],
        'privacy' => ['route' => '/privacy', 'title' => 'Privacy Policy'],
        'cookie-policy' => ['route' => '/cookie-policy', 'title' => 'Cookie Policy'],
        'download' => ['route' => '/download', 'title' => 'Download Zen Cart'],
        'about' => ['route' => '/about', 'title' => 'About Zen Cart'],
        'services' => ['route' => '/services', 'title' => 'Services'],
        'services-payment-processing' => ['route' => '/services/payment-processing', 'title' => 'Payment Processing'],
        'services-business-services' => ['route' => '/services/business-services', 'title' => 'Business Services'],
        'services-custom-programming' => ['route' => '/services/custom-programming', 'title' => 'Custom Programming'],
        'compare' => ['route' => '/compare', 'title' => 'Compare Zen Cart'],
        'donate' => ['route' => '/donate', 'title' => 'Donate to Zen Cart'],
    ];

    public function run(): void
    {
        foreach (self::PAGES as $slug => $page) {
            $created = CmsPage::query()->firstOrCreate(['slug' => $slug], [
                'title' => $page['title'],
                'custom_route' => $page['route'],
                'status' => CmsPageStatus::PUBLISHED,
                'published_at' => now(),
            ]);

            if ($created->wasRecentlyCreated && $slug === 'donate') {
                $this->seedDonateBlocks($created);
            }
        }
    }

    private function seedDonateBlocks(CmsPage $page): void
    {
        $blocks = [
            [10, 'donation_hero', 'Donate Hero', [
                'eyebrow' => 'Support Zen Cart',
                'title' => 'Donate to Zen Cart',
                'body' => 'Show your appreciation for the great support and fantastic shopping cart software by making a donation today.',
                'note' => 'Zen Ventures, LLC is not a charity, but your gift can be written off as a normal business expense.',
            ]],

            [20, 'donation_method', 'PayPal', [
                'provider' => 'paypal',
                'eyebrow' => 'Flexible support',
                'title' => 'PayPal',
                'body' => 'Make a one-time donation of any amount, or choose a monthly recurring level to provide ongoing support to Zen Cart.',
                'cta_label' => 'Donate any amount via PayPal',
                'cta_url' => 'https://www.paypal.com/donate/?hosted_button_id=XFE9JURMSRAHW',
                'note' => 'Recurring amounts help smooth out funding for support, development, and maintenance.',
                'tag' => '',
                'amounts' => ['5', '10', '15', '25', '50', '100'],
            ]],

            [30, 'donation_method', 'Zelle', [
                'provider' => 'zelle',
                'eyebrow' => 'Bank-to-bank',
                'title' => 'Zelle',
                'body' => 'Send a donation directly through your bank\'s app or website using Zelle, with no account required beyond your existing bank.',
                'cta_label' => '',
                'cta_url' => '',
                'note' => 'Open your bank\'s Zelle feature, search for the tag zencart, and send your chosen amount.',
                'tag' => 'zencart',
                'amounts' => [],
            ]],

            [40, 'donation_method', 'GitHub Sponsors', [
                'provider' => 'github',
                'eyebrow' => 'Developer-friendly',
                'title' => 'GitHub Sponsors',
                'body' => 'If you have a GitHub account, you can sponsor Zen Cart directly through GitHub Sponsors with flexible one-time or recurring amounts.',
                'cta_label' => 'Sponsor Zen Cart on GitHub',
                'cta_url' => 'https://github.com/sponsors/zencart',
                'note' => 'You\'ll be taken to the GitHub Sponsors page where you can choose your contribution amount and frequency.',
                'tag' => '',
                'amounts' => [],
            ]],

            [50, 'cta_band', 'Donate CTA', [
                'title' => 'Thank You for Your Support!',
                'body' => 'Your donation helps keep Zen Cart free and open source. Every contribution goes toward development, documentation, and community support.',
                'theme' => 'warm',
                'primary_label' => 'Visit the Forums',
                'primary_url' => '/forums',
                'secondary_label' => '',
                'secondary_url' => '',
            ]],
        ];

        foreach ($blocks as [$order, $type, $name, $data]) {
            $page->blocks()->create(['type' => $type, 'name' => $name, 'sort_order' => $order, 'data' => $data]);
        }
    }
}
