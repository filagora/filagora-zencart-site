<?php

namespace App\Policies;

use App\Models\BugReport;
use Filagora\Forums\Services\ForumAccess;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Bug reports are a moderator's tool: they open, change and remove them.
 */
class BugReportPolicy
{
    public function __construct(private readonly ForumAccess $access) {}

    public function viewAny(Authenticatable $user): bool
    {
        return $this->access->canModerate($user);
    }

    public function view(Authenticatable $user, BugReport $bugReport): bool
    {
        return $this->access->canModerate($user);
    }

    public function create(Authenticatable $user): bool
    {
        return $this->access->canModerate($user);
    }

    public function update(Authenticatable $user, BugReport $bugReport): bool
    {
        return $this->access->canModerate($user);
    }

    public function delete(Authenticatable $user, BugReport $bugReport): bool
    {
        return $this->access->canModerate($user);
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return $this->access->canModerate($user);
    }
}
