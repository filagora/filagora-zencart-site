<?php

namespace App\Http\Controllers;

use App\Models\User;
use Filagora\Plugins\Enums\PluginStatus;
use Filagora\Plugins\Exceptions\ModerationRefused;
use Filagora\Plugins\Models\Plugin;
use Filagora\Plugins\Services\Reports;
use Filagora\Plugins\Services\Subscriptions;
use Filagora\Plugins\Support\Models;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * What a signed-in member does with a public plugin: follow it, stop following it, report it.
 */
class PluginCommunityController extends Controller
{
    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly Reports $reports,
    ) {}

    public function subscribe(Request $request, Plugin $plugin): RedirectResponse
    {
        $user = $this->memberFor($request, $plugin);

        if (! $user instanceof User) {
            return $this->toLogin($request);
        }

        $this->subscriptions->subscribe($user, $plugin);

        return redirect()->route('plugins.show', $plugin)->with('plugin_success', __('plugin.flash.subscribed'));
    }

    public function unsubscribe(Request $request, Plugin $plugin): RedirectResponse
    {
        $user = $this->memberFor($request, $plugin);

        if (! $user instanceof User) {
            return $this->toLogin($request);
        }

        $this->subscriptions->unsubscribe($user, $plugin);

        if ($request->input('redirect_to') === 'profile' && Route::has('profile.show')) {
            return redirect()->route('profile.show', ['tab' => 'subscriptions'])->with('profile_status', __('plugin.flash.subscription_removed'));
        }

        return redirect()->route('plugins.show', $plugin)->with('plugin_success', __('plugin.flash.unsubscribed'));
    }

    public function report(Request $request, Plugin $plugin): RedirectResponse
    {
        $user = $this->memberFor($request, $plugin);

        if (! $user instanceof User) {
            return $this->toLogin($request);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:4000'],
            'plugin_release_id' => ['nullable', 'integer'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $release = null;

        if (($validated['plugin_release_id'] ?? null) !== null) {
            $release = Models::class('release')::query()->find((int) $validated['plugin_release_id']);
            abort_unless($release !== null && $release->plugin_id === $plugin->id, 404);
        }

        try {
            $this->reports->report($user, $plugin, $validated['reason'], $release);
        } catch (ModerationRefused $refused) {
            return redirect()->route('plugins.show', $plugin)->with('plugin_error', __('plugin.reports.already_reported'));
        }

        return redirect()->route('plugins.show', $plugin)->with('plugin_success', __('plugin.reports.submitted'));
    }

    private function memberFor(Request $request, Plugin $plugin): ?User
    {
        abort_unless($plugin->status === PluginStatus::ACTIVE && $plugin->type?->is_active, 404);

        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    private function toLogin(Request $request): RedirectResponse
    {
        return redirect()->route('home', ['auth' => 'login', 'intended' => $request->getRequestUri()]);
    }
}
