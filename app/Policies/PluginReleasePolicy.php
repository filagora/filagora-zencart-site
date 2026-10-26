<?php

namespace App\Policies;

use App\Models\User;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Models\PluginRelease;
use Filagora\Plugins\Support\Gates;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * What the plugin pages ask about a release.
 */
class PluginReleasePolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $this->moderates($user);
    }

    public function view(Authenticatable $user, PluginRelease $release): bool
    {
        return $this->moderates($user);
    }

    public function update(Authenticatable $user, PluginRelease $release): bool
    {
        return $this->moderates($user);
    }

    public function delete(Authenticatable $user, PluginRelease $release): bool
    {
        return $this->moderates($user);
    }

    public function moderate(Authenticatable $user, PluginRelease $release): bool
    {
        return $this->moderates($user);
    }

    /**
     * Edit a release's Zen Cart versions in place, without uploading a new release: moderators
     * may, and the plugin's owner or the release's uploader may, but only on the plugin's newest
     * active release, so history cannot be rewritten.
     */
    public function updateZcVersions(Authenticatable $user, PluginRelease $release): bool
    {
        if ($this->moderates($user)) {
            return true;
        }

        if (! $user instanceof User || $release->status !== PluginStatus::ACTIVE) {
            return false;
        }

        $plugin = $release->plugin;

        if ($plugin === null) {
            return false;
        }

        $isOwner = $plugin->owner_id !== null && (int) $plugin->owner_id === (int) $user->id;
        $isUploader = $release->uploaded_by_id !== null && (int) $release->uploaded_by_id === (int) $user->id;

        if (! $isOwner && ! $isUploader) {
            return false;
        }

        return (int) ($plugin->latestActiveRelease?->id ?? 0) === (int) $release->id;
    }

    private function moderates(Authenticatable $user): bool
    {
        return Gate::forUser($user)->allows(Gates::MODERATE);
    }
}
