<?php

namespace App\Plugins;

use App\Models\User;
use Filagora\Core\Contracts\AccessChecker;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\Plugin;
use Filagora\Plugins\Models\PluginRelease;
use Filagora\Plugins\Support\Gates;
use Filagora\Plugins\Support\Models;
use Illuminate\Support\Collection;

/**
 * The plugins and releases waiting for a moderator, as the library's front page lists them. Each
 * author also carries `can_auto_publish`, which the page shows beside them and offers to grant.
 */
class PluginReviewQueue
{
    private const LIMIT = 20;

    public function __construct(private readonly AccessChecker $access) {}

    /**
     * @return array{0: Collection<int, Plugin>, 1: Collection<int, PluginRelease>}
     */
    public function forModerator(): array
    {
        $plugins = Models::class('plugin')::query()
            ->select(['id', 'name', 'slug', 'plugin_type_id', 'owner_id', 'status', 'updated_at', 'created_at'])
            ->where('status', PluginStatus::MODERATED->value)
            ->with([
                'type:id,name,slug',
                'owner:id,username,name,email',
                'moderatorNotes.moderator:id,username,name,email',
            ])
            // Drives the queue's "download for review" link: a plugin with no release yet has no
            // package to hand the moderator.
            ->withCount('releases')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get();

        $releases = Models::class('release')::query()
            ->where('status', PluginStatus::MODERATED->value)
            ->with([
                'plugin:id,name,slug,owner_id',
                'plugin.owner:id,username,name,email',
                'plugin.moderatorNotes.moderator:id,username,name,email',
                'uploadedBy:id,username,name,email',
            ])
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get(['id', 'plugin_id', 'uploaded_by_id', 'version', 'status', 'created_at']);

        $authors = collect([
            ...$plugins->pluck('owner'),
            ...$releases->pluck('plugin.owner'),
            ...$releases->pluck('uploadedBy'),
        ])->filter(fn ($author): bool => $author instanceof User)->unique('id');

        $authors->each(fn (User $author) => $author->setAttribute('can_auto_publish', $this->access->allows($author, Gates::AUTO_PUBLISH)));

        return [$plugins, $releases];
    }
}
