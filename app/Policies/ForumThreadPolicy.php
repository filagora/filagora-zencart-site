<?php

namespace App\Policies;

use Filagora\Forums\Models\Thread;
use Filagora\Forums\Services\ForumAccess;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * What the forum views ask with `@can` about a thread: only moderators change one.
 */
class ForumThreadPolicy
{
    public function __construct(private readonly ForumAccess $access) {}

    public function update(?Authenticatable $user, Thread $thread): bool
    {
        return $this->access->canModerate($user);
    }
}
