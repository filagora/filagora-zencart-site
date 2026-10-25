<?php

namespace App\Support;

use Filagora\Forums\Models\Post;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Support\Models;

class ThreadOpeningText
{
    public const LIMIT = 300;

    /**
     * Plain-text start of the thread's opening post, or '' when it is not
     * available. The first approved, non-deleted post only counts when the
     * thread's author wrote it: if the real opening post is gone, the next
     * post is somebody else's words and must not be presented as the thread's
     * text.
     */
    public function for(Thread $thread): string
    {
        $post = Models::class('post')::query()
            ->where('thread_id', $thread->id)
            ->where('is_deleted', false)
            ->approved()
            ->orderBy('created_at')
            ->orderBy('id')
            ->first(['id', 'author_id', 'body']);

        if (! $post instanceof Post || (int) $post->author_id !== (int) $thread->author_id) {
            return '';
        }

        return MetaDescription::fromPlainText(app(FormattedContentRenderer::class)->plainText((string) $post->body), self::LIMIT);
    }
}
