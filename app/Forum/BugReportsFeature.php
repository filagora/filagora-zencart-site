<?php

namespace App\Forum;

use App\Models\User;
use Filagora\ForumBugReports\Services\BugReports;
use Filagora\ForumBugReports\Services\ResolvedBugThreadVisibility;
use Filagora\Forums\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * What the forum pages ask of bug reports. They are an optional package: without it these answer as
 * a site with no bug reports would, so the pages need no checks of their own.
 */
class BugReportsFeature
{
    public static function installed(): bool
    {
        return class_exists(BugReports::class);
    }

    public function hideResolvedAvailableFor(?User $user): bool
    {
        return self::installed() && app(ResolvedBugThreadVisibility::class)->availableFor($user);
    }

    public function hideResolvedFor(Request $request, ?User $user): bool
    {
        return self::installed() && app(ResolvedBugThreadVisibility::class)->hiddenFor($request, $user);
    }

    /**
     * @return Collection<int, mixed>
     */
    public function forThread(Thread $thread): Collection
    {
        return self::installed() ? app(BugReports::class)->forThread($thread)->toBase() : collect();
    }

    /**
     * @param  iterable<int>  $threadIds
     * @return Collection<int, mixed>
     */
    public function latestStatusByThread(iterable $threadIds): Collection
    {
        return collect(self::installed() ? app(BugReports::class)->latestStatusByThread($threadIds) : []);
    }
}
