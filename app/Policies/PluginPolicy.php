<?php

namespace App\Policies;

use App\Models\User;
use Filagora\Plugins\Models\Plugin;
use Filagora\Plugins\Support\Gates;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * What the plugin pages ask with `@can` or the Gate about a plugin. Moderation is the plugins
 * package's ability; the rest is who owns or may add to a plugin.
 */
class PluginPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $this->moderates($user);
    }

    public function view(Authenticatable $user, Plugin $plugin): bool
    {
        return $this->moderates($user);
    }

    public function delete(Authenticatable $user, Plugin $plugin): bool
    {
        return $this->moderates($user);
    }

    public function moderateAny(Authenticatable $user): bool
    {
        return $this->moderates($user);
    }

    public function moderate(Authenticatable $user, Plugin $plugin): bool
    {
        return $this->moderates($user);
    }

    /**
     * Any signed-in member may submit a release for any plugin, an abandoned one included, so the
     * community can keep it going. What they submit waits for review unless they may publish.
     */
    public function submitRelease(Authenticatable $user, Plugin $plugin): bool
    {
        return $user instanceof User;
    }

    public function update(Authenticatable $user, Plugin $plugin): bool
    {
        return $this->moderates($user)
            || ($user instanceof User && $plugin->owner_id !== null && (int) $plugin->owner_id === (int) $user->id);
    }

    private function moderates(Authenticatable $user): bool
    {
        return Gate::forUser($user)->allows(Gates::MODERATE);
    }
}
