<?php

namespace Tests\Feature;

use App\Http\Controllers\ForumController;
use App\Models\User;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsSitePages;
use Tests\TestCase;

class ThreadStructuredDataTest extends TestCase
{
    use RefreshDatabase, SeedsSitePages;

    public function test_a_public_approved_thread_emits_discussion_forum_posting_data_on_its_first_page(): void
    {
        $author = User::factory()->create(['username' => 'thread_starter']);
        $thread = $this->createThread(author: $author, title: 'How do I fix the cart?');

        $response = $this->get(route('threads.show', $thread))->assertOk();
        $data = $this->structuredData($response->getContent());

        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('DiscussionForumPosting', $data['@type']);
        $this->assertSame('How do I fix the cart?', $data['headline']);
        $this->assertSame(route('threads.show', $thread), $data['url']);
        $this->assertSame($thread->created_at->toIso8601String(), $data['datePublished']);
        $this->assertSame(['@type' => 'Person', 'name' => 'thread_starter'], $data['author']);
        $this->assertArrayNotHasKey('email', $data['author']);
        $this->assertSame('Organization', $data['publisher']['@type']);
        $this->assertSame(route('home'), $data['publisher']['url']);
        $this->assertSame('Opening post', $data['text']);
    }

    public function test_the_posting_text_is_the_start_of_the_opening_post_as_plain_text(): void
    {
        $thread = $this->createThread();
        Post::query()->where('thread_id', $thread->id)->update([
            'body' => "**Bold** start with a [link](https://example.test/x)\n\n".str_repeat('long ', 200),
        ]);

        $data = $this->structuredData($this->get(route('threads.show', $thread))->assertOk()->getContent());

        $this->assertStringStartsWith('Bold start with a link', $data['text']);
        $this->assertStringNotContainsString('**', $data['text']);
        $this->assertLessThanOrEqual(303, mb_strlen($data['text']));
        $this->assertStringEndsWith('...', $data['text']);
    }

    public function test_the_posting_text_is_left_out_when_the_opening_post_is_not_available(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $thread = $this->createThread(author: $author);
        Post::query()->where('thread_id', $thread->id)->update(['is_deleted' => true]);
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $other->id,
            'body' => 'A later reply by somebody else',
            'moderation_status' => ContentModerationStatus::APPROVED,
        ]);

        $data = $this->structuredData($this->get(route('threads.show', $thread))->assertOk()->getContent());

        $this->assertArrayNotHasKey('text', $data);
        $this->assertSame('DiscussionForumPosting', $data['@type']);
    }

    public function test_a_thread_page_also_emits_a_breadcrumb_trail_through_its_forum(): void
    {
        $thread = $this->createThread(title: 'Where am I?');

        $crumbs = $this->structuredData($this->get(route('threads.show', $thread))->assertOk()->getContent(), 'BreadcrumbList');

        $this->assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Forums', 'item' => route('forums.index')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Support', 'item' => route('forums.show', $thread->forum)],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'Where am I?', 'item' => route('threads.show', $thread)],
        ], $crumbs['itemListElement']);
    }

    public function test_later_pages_of_a_thread_do_not_repeat_the_structured_data(): void
    {
        $author = User::factory()->create();
        $thread = $this->createThread(author: $author);

        for ($i = 0; $i < ForumController::THREAD_POSTS_PER_PAGE + 1; $i++) {
            Post::query()->create([
                'thread_id' => $thread->id,
                'author_id' => $author->id,
                'body' => 'Reply number '.$i,
                'moderation_status' => ContentModerationStatus::APPROVED,
            ]);
        }

        $this->get(route('threads.show', $thread))
            ->assertOk()
            ->assertSee('application/ld+json', false);

        $this->get(route('threads.show', ['thread' => $thread, 'page' => 2]))
            ->assertOk()
            ->assertDontSee('application/ld+json', false);
    }

    public function test_threads_that_are_not_public_and_approved_emit_no_structured_data(): void
    {
        $viewer = User::factory()->create();

        $registeredOnly = $this->createThread(visibility: ForumVisibility::REGISTERED);
        $this->actingAs($viewer)
            ->get(route('threads.show', $registeredOnly))
            ->assertOk()
            ->assertDontSee('application/ld+json', false);

        $pending = $this->createThread(status: ContentModerationStatus::PENDING);
        $this->actingAs($this->staffViewer())
            ->get(route('threads.show', $pending))
            ->assertOk()
            ->assertDontSee('application/ld+json', false);

        $inactive = $this->createThread(forumActive: false);
        $this->actingAs($this->staffViewer())
            ->get(route('threads.show', $inactive))
            ->assertDontSee('application/ld+json', false);
    }

    public function test_a_thread_title_cannot_break_out_of_the_script_tag(): void
    {
        $thread = $this->createThread(title: 'Cart </script><script>alert(1)</script> & more');

        $html = $this->get(route('threads.show', $thread))->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        $this->assertSame('Cart </script><script>alert(1)</script> & more', $this->structuredData($html)['headline']);
        $this->assertSame('Cart </script><script>alert(1)</script> & more', $this->structuredData($html, 'BreadcrumbList')['itemListElement'][2]['name']);
    }

    public function test_forum_listing_pages_do_not_emit_structured_data(): void
    {
        $thread = $this->createThread();

        $this->get(route('forums.index'))->assertOk()->assertDontSee('application/ld+json', false);
        $this->get(route('forums.show', $thread->forum))->assertOk()->assertDontSee('application/ld+json', false);
    }

    /**
     * Every JSON-LD document on the page, keyed by its @type.
     *
     * @return array<string, array<string, mixed>>
     */
    private function documents(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $documents = [];
        foreach ($matches[1] as $json) {
            $decoded = json_decode($json, true);
            $this->assertIsArray($decoded);
            $documents[$decoded['@type']] = $decoded;
        }

        return $documents;
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredData(string $html, string $type = 'DiscussionForumPosting'): array
    {
        $documents = $this->documents($html);
        $this->assertCount(2, $documents, 'Expected the main document and a breadcrumb list.');
        $this->assertArrayHasKey($type, $documents);

        return $documents[$type];
    }

    private function staffViewer(): User
    {
        $staff = User::factory()->create();
        $this->makeForumModerator($staff);
        $this->grantCanonicalOperatorEntitlements($staff, 'forum_moderator');

        return $staff;
    }

    private function createThread(
        ?User $author = null,
        string $title = 'A Public Thread',
        ForumVisibility $visibility = ForumVisibility::PUBLIC,
        ContentModerationStatus $status = ContentModerationStatus::APPROVED,
        bool $forumActive = true,
    ): Thread {
        $author ??= User::factory()->create();
        $group = ForumGroup::query()->create(['name' => 'G', 'slug' => 'g-'.Str::lower(Str::random(6)), 'is_active' => true]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'Support',
            'slug' => 'support-'.Str::lower(Str::random(6)),
            'visibility' => $visibility,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => $forumActive,
        ]);
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => $title,
            'slug' => 'thread-'.Str::lower(Str::random(8)),
            'moderation_status' => $status,
        ]);
        Post::query()->create([
            'thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => 'Opening post',
            'moderation_status' => $status,
        ]);

        return $thread;
    }
}
