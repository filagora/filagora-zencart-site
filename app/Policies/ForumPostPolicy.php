<?php

namespace App\Policies;

use Filagora\Forums\Models\Post;
use Filagora\Forums\Services\ForumAccess;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * What the forum views ask with `@can` about a post. The answers are the forums package's
 * `ForumAccess`: moderators moderate, and a post is edited by a moderator or by its author within
 * the edit window.
 */
class ForumPostPolicy
{
    public function __construct(private readonly ForumAccess $access) {}

    public function viewAny(?Authenticatable $user): bool
    {
        return $this->access->canModerate($user);
    }

    public function moderate(?Authenticatable $user, Post $post): bool
    {
        return $this->access->canModerate($user);
    }

    public function update(?Authenticatable $user, Post $post): bool
    {
        return $this->access->canEditPost($post, $user);
    }
}
