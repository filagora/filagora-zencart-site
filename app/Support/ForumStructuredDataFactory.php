<?php

namespace App\Support;

use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\Thread;

class ForumStructuredDataFactory
{
    public function __construct(private readonly StructuredDataPublisher $publisher) {}

    /**
     * JSON-LD for a thread page, or null when the thread must not advertise
     * itself publicly. Only approved threads in active, publicly readable
     * forums qualify, and only the first page: that is the page that actually
     * shows the opening post the author and date describe. $openingText is the
     * plain-text start of that post (see ThreadOpeningText). Returns the posting
     * plus its breadcrumb trail, as separate JSON-LD documents.
     *
     * @return list<array<string, mixed>>|null
     */
    public function forThread(Thread $thread, int $page, string $openingText = ''): ?array
    {
        $forum = $thread->forum;

        if ($page !== 1
            || $forum === null
            || ! $forum->is_active
            || $forum->visibility !== ForumVisibility::PUBLIC
            || ! $thread->isApproved()) {
            return null;
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'DiscussionForumPosting',
            'headline' => trim(LegacyTextDecoder::decode((string) $thread->title)),
            'url' => route('threads.show', $thread),
        ];

        if ($thread->created_at !== null) {
            $data['datePublished'] = $thread->created_at->toIso8601String();
        }

        $authorName = trim((string) ($thread->author?->username ?: $thread->author?->name ?: ''));
        if ($authorName !== '') {
            $data['author'] = ['@type' => 'Person', 'name' => $authorName];
        }

        if ($openingText !== '') {
            $data['text'] = $openingText;
        }

        $data['publisher'] = $this->publisher->organization();

        $title = $data['headline'];

        return [
            $data,
            $this->publisher->breadcrumbs([
                ['name' => 'Forums', 'url' => route('forums.index')],
                ['name' => trim(LegacyTextDecoder::decode((string) $forum->name)), 'url' => route('forums.show', $forum)],
                ['name' => $title, 'url' => $data['url']],
            ]),
        ];
    }
}
