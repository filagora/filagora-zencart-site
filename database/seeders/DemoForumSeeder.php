<?php

namespace Database\Seeders;

use App\Models\User;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Support\Models;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * A small made-up forum for a fresh local install: a few groups and forums, some members, and
 * threads with replies, so the forum pages have something to show. Everything here is invented
 * (reserved example addresses, lorem-style text). Local and test databases only.
 */
class DemoForumSeeder extends Seeder
{
    /**
     * @var array<string, list<string>> group => its forums
     */
    private const FORUMS = [
        'Using Zen Cart' => ['Installation Help', 'Upgrading', 'Templates and Layout'],
        'Development' => ['Code Contributions', 'Bug Reports'],
    ];

    private const TOPICS = [
        'How do I move my store to a new host?',
        'Which PHP version should I run?',
        'Template override not being picked up',
        'Question about the order confirmation email',
        'Contributing a fix for the cart totals',
        'Admin login redirects back to itself',
    ];

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('DemoForumSeeder only runs in a local or test environment.');
        }

        $members = collect(['alice', 'bob', 'carol', 'dave'])->map(fn (string $name): User => User::query()->firstOrCreate(
            ['email' => "{$name}@example.test"],
            ['name' => Str::title($name), 'username' => $name, 'password' => 'password', 'email_verified_at' => now()],
        ));

        $forumModel = Models::class('forum');
        $threadModel = Models::class('thread');
        $postModel = Models::class('post');
        $order = 0;

        foreach (self::FORUMS as $groupName => $forums) {
            $group = ForumGroup::query()->firstOrCreate(
                ['slug' => Str::slug($groupName)],
                ['name' => $groupName, 'is_active' => true, 'sort_order' => ++$order],
            );

            foreach ($forums as $index => $forumName) {
                $forum = $forumModel::query()->firstOrCreate(
                    ['slug' => Str::slug($forumName)],
                    [
                        'forum_group_id' => $group->id,
                        'name' => $forumName,
                        'description' => "Discuss {$forumName}.",
                        'visibility' => ForumVisibility::PUBLIC,
                        'posting_policy' => ForumPostingPolicy::PUBLIC,
                        'is_active' => true,
                        'sort_order' => $index + 1,
                    ],
                );

                if ($threadModel::query()->where('forum_id', $forum->id)->exists()) {
                    continue;
                }

                foreach (collect(self::TOPICS)->shuffle()->take(3) as $title) {
                    $author = $members->random();
                    $startedAt = now()->subDays(random_int(1, 40))->subMinutes(random_int(0, 600));

                    $thread = $threadModel::query()->create([
                        'forum_id' => $forum->id,
                        'author_id' => $author->id,
                        'title' => $title,
                        'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
                        'moderation_status' => ContentModerationStatus::APPROVED,
                        'created_at' => $startedAt,
                        'updated_at' => $startedAt,
                    ]);

                    $postModel::query()->create([
                        'thread_id' => $thread->id,
                        'author_id' => $author->id,
                        'body' => "I am stuck on this: {$title}\n\nHere is what I have tried so far, and what I expected to happen.",
                        'moderation_status' => ContentModerationStatus::APPROVED,
                        'created_at' => $startedAt,
                        'updated_at' => $startedAt,
                    ]);

                    foreach (range(1, random_int(0, 4)) as $reply) {
                        $postedAt = $startedAt->copy()->addHours($reply * random_int(1, 20));

                        $postModel::query()->create([
                            'thread_id' => $thread->id,
                            'author_id' => $members->random()->id,
                            'body' => 'Have you checked the documentation for this? It usually comes down to the settings.',
                            'moderation_status' => ContentModerationStatus::APPROVED,
                            'created_at' => $postedAt,
                            'updated_at' => $postedAt,
                        ]);
                    }
                }
            }
        }

        // The counters on forums and threads are kept right as posts change, but rebuilding them
        // makes the demo correct whatever order the rows were made in.
        Artisan::call('filagora:forums:sync-stats');
        Artisan::call('filagora:forums:sync-thread-stats');
    }
}
