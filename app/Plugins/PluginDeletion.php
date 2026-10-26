<?php

namespace App\Plugins;

use App\Actions\BanUserAction;
use App\Models\User;
use Filagora\Plugins\Models\Plugin;
use Filagora\Plugins\Models\PluginRelease;
use Filagora\Plugins\Support\Models;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently removes a plugin (with every release) or a single release from the moderation
 * queue. Optionally sweeps everything else the same author owns or uploaded, and bans them for
 * good.
 *
 * Rows are deleted through Eloquent, one at a time, so the version-check push, search-index and
 * cache observers all fire (a database cascade would skip them). Zip files are removed only after
 * the transaction commits.
 */
class PluginDeletion
{
    public function __construct(private readonly BanUserAction $banUser) {}

    /**
     * @return array{plugins:int, releases:int, banned:bool}
     */
    public function deletePlugin(Plugin $plugin, User $moderator, ?string $reason, bool $removeAllByAuthor, bool $banAuthor, ?string $banReason = null): array
    {
        $author = $plugin->owner;
        $sweep = $removeAllByAuthor && $this->isOtherUser($author, $moderator);

        $plugins = collect([$plugin]);
        $releases = collect();

        if ($sweep) {
            $plugins = $plugins->merge(Models::class('plugin')::query()->where('owner_id', $author->id)->get())->unique('id');
            $releases = Models::class('release')::query()->where('uploaded_by_id', $author->id)->get();
        }

        return $this->remove($plugins, $releases, $moderator, $author, $reason, $banAuthor, $banReason);
    }

    /**
     * @return array{plugins:int, releases:int, banned:bool}
     */
    public function deleteRelease(PluginRelease $release, User $moderator, ?string $reason, bool $removeAllByAuthor, bool $banAuthor, ?string $banReason = null): array
    {
        $author = $release->uploadedBy ?? $release->plugin?->owner;
        $sweep = $removeAllByAuthor && $this->isOtherUser($author, $moderator);

        $releases = collect([$release]);

        if ($sweep) {
            $releases = $releases->merge(Models::class('release')::query()->where('uploaded_by_id', $author->id)->get())->unique('id');
        }

        return $this->remove(collect(), $releases, $moderator, $author, $reason, $banAuthor, $banReason);
    }

    /**
     * @param  Collection<int, Plugin>  $plugins
     * @param  Collection<int, PluginRelease>  $releases
     * @return array{plugins:int, releases:int, banned:bool}
     */
    private function remove(Collection $plugins, Collection $releases, User $moderator, ?User $author, ?string $reason, bool $banAuthor, ?string $banReason): array
    {
        $releases = $releases
            ->merge(Models::class('release')::query()->whereIn('plugin_id', $plugins->pluck('id')->all())->get())
            ->unique('id');

        $zipPaths = $releases->pluck('zip_path')->filter()->unique()->values()->all();

        DB::transaction(function () use ($plugins, $releases): void {
            foreach ($releases as $release) {
                $release->delete();
            }

            foreach ($plugins as $plugin) {
                $plugin->delete();
            }
        });

        $disk = Storage::disk((string) config('filagora-plugins.storage.disk', 'public'));

        foreach ($zipPaths as $path) {
            $disk->delete($path);
        }

        Log::notice('Plugin content permanently deleted by moderator.', [
            'moderator_id' => $moderator->id,
            'author_id' => $author?->id,
            'plugins' => $plugins->map(fn (Plugin $plugin): array => ['id' => $plugin->id, 'name' => $plugin->name])->all(),
            'release_ids' => $releases->pluck('id')->all(),
            'reason' => trim((string) $reason) ?: null,
        ]);

        $banned = false;

        if ($banAuthor && $this->isOtherUser($author, $moderator)) {
            $this->banUser->execute($author, $banReason, 'permanent');
            $banned = true;
        }

        return ['plugins' => $plugins->count(), 'releases' => $releases->count(), 'banned' => $banned];
    }

    private function isOtherUser(?User $author, User $moderator): bool
    {
        return $author instanceof User && ! $author->is($moderator);
    }
}
