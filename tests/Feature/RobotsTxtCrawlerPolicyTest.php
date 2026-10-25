<?php

namespace Tests\Feature;

use Tests\TestCase;

class RobotsTxtCrawlerPolicyTest extends TestCase
{
    public function test_default_rules_apply_the_shared_disallow_list_and_crawl_delay(): void
    {
        $robotsTxt = $this->robotsTxt();

        $this->assertStringContainsString(
            "User-agent: *\nDisallow: /*/blackhole/\nDisallow: /images/\nDisallow: /image.php\nDisallow: /archived_contributions/\nDisallow: *redlink*\nDisallow: /search\nDisallow: /members/\nDisallow: /members$\nDisallow: /*?auth=\nDisallow: /plugins/*/download\nCrawl-delay: 15",
            $robotsTxt
        );
    }

    /**
     * Every /members route sits behind frontend.verified, so a crawler only
     * ever gets a redirect to login - measured at 3,876 redirects against 33
     * real pages in one day, each redirect costing a framework boot. The
     * trailing-slash form plus "$" avoids also blocking any path that merely
     * starts with "members" (a "/memberships" page, say).
     */
    public function test_login_gated_member_pages_are_disallowed_without_blocking_lookalike_paths(): void
    {
        $robotsTxt = $this->robotsTxt();

        $this->assertSame(3, substr_count($robotsTxt, "Disallow: /members/\nDisallow: /members\$\n"));
        $this->assertStringNotContainsString("Disallow: /members\n", $robotsTxt);
    }

    /**
     * From a Googlebot log sample: login-modal URLs (/?auth=login&intended=...,
     * where /members/N and other gated links redirect to) were the second-largest
     * request group, and plugin release zips were being downloaded by the crawler.
     * Neither has any search value.
     */
    public function test_login_modal_urls_and_plugin_downloads_are_disallowed_for_the_default_crawlers(): void
    {
        $this->assertSame(3, substr_count($this->robotsTxt(), "Disallow: /*?auth=\nDisallow: /plugins/*/download\n"));
    }

    public function test_robots_txt_points_crawlers_at_the_sitemap(): void
    {
        $this->assertMatchesRegularExpression(
            '#^Sitemap: https://www\.zen-cart\.com/sitemap\.xml$#m',
            $this->robotsTxt()
        );
    }

    /**
     * AI indexing/crawling bots: throttled and restricted to a small
     * allowlist, each with its own crawl-delay.
     */
    public function test_ai_indexing_crawlers_are_throttled_and_restricted_to_the_allowlist(): void
    {
        $robotsTxt = $this->robotsTxt();

        foreach (['ClaudeBot' => 25, 'Claude-SearchBot' => 10, 'GPTBot' => 25, 'OAI-SearchBot' => 10] as $agent => $crawlDelay) {
            $this->assertStringContainsString(
                "User-agent: {$agent}\nCrawl-delay: {$crawlDelay}\nDisallow: /\nAllow: /\$\nAllow: /sitemap.xml\nAllow: /threads/\nAllow: /pages/\nAllow: /showthread.php\nAllow: /content.php",
                $robotsTxt
            );
        }
    }

    /**
     * AI "on-demand" user agents (a person's live request, not a background
     * crawl) get no crawl-delay and additionally get /search allowlisted,
     * unlike the indexing crawlers above.
     */
    public function test_ai_on_demand_user_agents_are_allowlisted_without_a_crawl_delay(): void
    {
        $robotsTxt = $this->robotsTxt();

        foreach (['Claude-User', 'ChatGPT-User'] as $agent) {
            $this->assertStringContainsString(
                "User-agent: {$agent}\nDisallow: /\nAllow: /\$\nAllow: /sitemap.xml\nAllow: /threads/\nAllow: /pages/\nAllow: /search\nAllow: /showthread.php\nAllow: /content.php",
                $robotsTxt
            );
            $this->assertStringNotContainsString("User-agent: {$agent}\nCrawl-delay:", $robotsTxt);
        }
    }

    public function test_legacy_search_engines_keep_the_shared_crawl_delay_without_a_full_block(): void
    {
        $robotsTxt = $this->robotsTxt();

        foreach (['Slurp', 'MSNBot'] as $agent) {
            $this->assertStringContainsString(
                "User-agent: {$agent}\nDisallow: /images/\nDisallow: /search\nDisallow: /members/\nDisallow: /members$\nDisallow: /*?auth=\nDisallow: /plugins/*/download\nDisallow: /archived_contributions/\nDisallow: *redlink*\nCrawl-delay: 15",
                $robotsTxt
            );
        }
    }

    public function test_unallowlisted_bots_are_fully_disallowed(): void
    {
        $robotsTxt = $this->robotsTxt();

        foreach (['Yandex', 'Baiduspider', 'AhrefsBot', 'SemrushBot', 'Amazonbot', 'Amzn-SearchBot', 'PetalBot', 'SleepBot'] as $agent) {
            $this->assertStringContainsString("User-agent: {$agent}\nDisallow: /", $robotsTxt);
        }

        $this->assertStringContainsString("User-Agent: Neevabot\nDisallow: /", $robotsTxt);
    }

    private function robotsTxt(): string
    {
        $robotsTxt = file_get_contents(public_path('robots.txt'));
        $this->assertNotFalse($robotsTxt);

        return $robotsTxt;
    }
}
