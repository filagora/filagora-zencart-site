<?php

namespace Tests\Feature;

use App\Models\User;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\ZenCart\Models\Forum;
use Filagora\ZenCart\Models\Post;
use Filagora\ZenCart\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NoindexPrivateAndThinPagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function noindexPaths(): array
    {
        return [
            'dashboard login' => ['/dashboard/login'],
            'dashboard (guest redirect)' => ['/dashboard'],
            'login' => ['/login'],
            'register' => ['/register'],
            'forgot password' => ['/password/forgot'],
            'verify email notice' => ['/verify-email'],
            'search' => ['/search'],
            'members index' => ['/members'],
            'messages' => ['/messages'],
            'notifications' => ['/notifications'],
            'profile' => ['/profile'],
            'legacy search' => ['/search.php'],
        ];
    }

    #[DataProvider('noindexPaths')]
    public function test_private_and_thin_pages_send_a_noindex_header(string $path): void
    {
        $this->get($path)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_a_member_profile_sends_a_noindex_header(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('members.show', $user))
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_public_content_pages_are_not_marked_noindex(): void
    {
        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
        $this->get(route('forums.index'))->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_an_unflagged_forum_and_its_threads_stay_indexable(): void
    {
        $thread = $this->createPublicThread(noindex: false);

        $this->get(route('forums.show', $thread->forum))->assertHeaderMissing('X-Robots-Tag');
        $this->get(route('threads.show', $thread))->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_a_forum_flagged_noindex_marks_its_page_and_threads_noindex(): void
    {
        $thread = $this->createPublicThread(noindex: true);

        $this->get(route('forums.show', $thread->forum))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get(route('threads.show', $thread))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    private function createPublicThread(bool $noindex): Thread
    {
        $group = ForumGroup::query()->create(['name' => 'General', 'slug' => 'general-noindex', 'is_active' => true]);
        $forum = Forum::query()->create([
            'forum_group_id' => $group->id,
            'name' => 'General Support',
            'slug' => 'general-support-noindex',
            'visibility' => ForumVisibility::PUBLIC,
            'posting_policy' => ForumPostingPolicy::REGISTERED,
            'is_read_only' => false,
            'is_active' => true,
            'noindex' => $noindex,
        ]);
        $author = User::factory()->create();
        $thread = Thread::query()->create([
            'forum_id' => $forum->id,
            'author_id' => $author->id,
            'title' => 'Noindex test thread',
            'slug' => 'noindex-test-thread',
        ]);
        Post::query()->create(['thread_id' => $thread->id, 'author_id' => $author->id, 'body' => 'First post']);

        return $thread;
    }
}
