<?php

namespace App\Forum;

use Filagora\Forums\Contracts\PostUrlResolver;
use Filagora\Forums\Models\Post;

/**
 * Where the dashboard's "Read" action on a post report sends staff: the thread page that holds the
 * post, anchored at it, in a new tab.
 */
class ReportedPostUrl implements PostUrlResolver
{
    public function __construct(private readonly PostLinks $links) {}

    public function url(Post $post): ?string
    {
        $thread = $post->thread;

        return $thread === null ? null : $this->links->urlFor($thread, $post);
    }
}
