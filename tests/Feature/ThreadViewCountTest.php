<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class ThreadViewCountTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    private const BROWSER_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36';

    public function test_viewing_a_thread_increments_its_view_count(): void
    {
        $thread = $this->createPublicThread();

        $this->assertSame(0, $thread->fresh()->view_count);

        $this->get(route('threads.show', $thread))->assertOk();

        $this->assertSame(1, $thread->fresh()->view_count);
    }

    public function test_repeated_views_from_the_same_authenticated_user_are_deduped(): void
    {
        $thread = $this->createPublicThread();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get(route('threads.show', $thread))->assertOk();
        $this->actingAs($viewer)->get(route('threads.show', $thread))->assertOk();

        $this->assertSame(1, $thread->fresh()->view_count);
    }

    public function test_views_from_different_authenticated_users_both_count(): void
    {
        $thread = $this->createPublicThread();
        $viewerOne = User::factory()->create();
        $viewerTwo = User::factory()->create();

        $this->actingAs($viewerOne)->get(route('threads.show', $thread))->assertOk();
        $this->actingAs($viewerTwo)->get(route('threads.show', $thread))->assertOk();

        $this->assertSame(2, $thread->fresh()->view_count);
    }

    public function test_repeated_guest_requests_without_a_session_cookie_are_deduped(): void
    {
        $thread = $this->createPublicThread();

        // The test client never returns cookies, so each request arrives without a
        // session cookie and is handed a brand-new session ID — exactly what a
        // cookie-less client does in production.
        $this->guestRequest($thread, ip: '203.0.113.10')->assertOk();
        $firstSessionId = $this->app['session']->getId();

        $this->guestRequest($thread, ip: '203.0.113.10')->assertOk();
        $secondSessionId = $this->app['session']->getId();

        $this->assertNotSame(
            $firstSessionId,
            $secondSessionId,
            'The requests must not share a session, otherwise this test would pass on a session-keyed dedupe.'
        );
        $this->assertSame(1, $thread->fresh()->view_count);
    }

    public function test_guest_views_from_different_ip_addresses_both_count(): void
    {
        $thread = $this->createPublicThread();

        $this->guestRequest($thread, ip: '203.0.113.10')->assertOk();
        $this->guestRequest($thread, ip: '198.51.100.7')->assertOk();

        $this->assertSame(2, $thread->fresh()->view_count);
    }

    public function test_guest_views_from_one_ip_with_different_user_agents_both_count(): void
    {
        $thread = $this->createPublicThread();

        $this->guestRequest($thread, ip: '203.0.113.10')->assertOk();
        $this->guestRequest($thread, ip: '203.0.113.10', userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1')->assertOk();

        $this->assertSame(2, $thread->fresh()->view_count);
    }

    public function test_guest_views_from_user_agents_sharing_a_long_prefix_both_count(): void
    {
        $thread = $this->createPublicThread();

        // Modern user agents carry their distinguishing part at the very end — the
        // browser fork, or a crawler's identity. The fingerprint must hash the whole
        // string, so two visitors behind one IP are not merged by a shared prefix.
        // The prefix here is deliberately longer than any truncation would keep.
        $sharedPrefix = self::BROWSER_USER_AGENT.' Mobile/15E148 ';
        $this->assertGreaterThan(120, mb_strlen($sharedPrefix));

        $this->guestRequest($thread, ip: '203.0.113.10', userAgent: $sharedPrefix.'OPR/113.0.0.0')->assertOk();
        $this->guestRequest($thread, ip: '203.0.113.10', userAgent: $sharedPrefix.'Edg/127.0.0.0')->assertOk();

        $this->assertSame(2, $thread->fresh()->view_count);
    }

    public function test_crawler_requests_never_increment_the_view_count(): void
    {
        $crawlerUserAgents = [
            'meta-webindexer/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/web-crawlers)',
            'Mozilla/5.0 (compatible; Claude-SearchBot/1.0; +claudebot@anthropic.com)',
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
            'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)',
            'curl/8.4.0',
            'python-requests/2.31.0',
            '',
        ];

        foreach ($crawlerUserAgents as $userAgent) {
            $thread = $this->createPublicThread();

            $this->guestRequest($thread, ip: '203.0.113.10', userAgent: $userAgent)->assertOk();
            $this->guestRequest($thread, ip: '198.51.100.7', userAgent: $userAgent)->assertOk();

            $this->assertSame(
                0,
                $thread->fresh()->view_count,
                'Crawler user agent should not be counted: '.($userAgent === '' ? '(empty)' : $userAgent)
            );
        }
    }

    public function test_thread_show_page_displays_view_count(): void
    {
        $thread = $this->createPublicThread(['view_count' => 41]);

        $this->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('Views: 42');
    }

    public function test_thread_list_displays_view_count(): void
    {
        $thread = $this->createPublicThread(['view_count' => 7]);

        $this->get(route('forums.show', $thread->forum))
            ->assertOk()
            ->assertSee('Views: 7');
    }

    private function guestRequest(Thread $thread, string $ip, string $userAgent = self::BROWSER_USER_AGENT): TestResponse
    {
        return $this
            ->withServerVariables([
                'REMOTE_ADDR' => $ip,
                'HTTP_USER_AGENT' => $userAgent,
            ])
            ->get(route('threads.show', $thread));
    }

    /**
     * @param  array<string, mixed>  $threadOverrides
     */
    private function createPublicThread(array $threadOverrides = []): Thread
    {
        $author = User::factory()->create();

        $group = ForumGroup::query()->create([
            'name' => 'View Count Group',
            'slug' => 'view-count-group-'.uniqid(),
            'is_active' => true,
        ]);

        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'View Count Forum',
            'slug' => 'view-count-forum-'.uniqid(),
            'is_active' => true,
        ]);

        return Thread::query()->create(array_merge([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'View Count Thread',
            'slug' => 'view-count-thread-'.uniqid(),
        ], $threadOverrides));
    }
}
