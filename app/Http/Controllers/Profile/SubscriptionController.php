<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Filagora\Forums\Support\Models as ForumModels;
use Filagora\Plugins\Models\Plugin;
use Filagora\Plugins\Services\Subscriptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Stopping the threads and plugins a member follows, from their profile.
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly Subscriptions $pluginSubscriptions) {}

    public function removeThread(Request $request, int $thread): RedirectResponse
    {
        $user = $this->member($request);

        $this->threadSubscriptions($user)->where('thread_id', $thread)->get()->each->delete();

        return $this->back(__('profile.flash.subscription_removed'));
    }

    public function removeThreads(Request $request): RedirectResponse
    {
        $user = $this->member($request);

        $validated = $request->validate([
            'scope' => ['required', 'in:selected,all'],
            'thread_ids' => ['exclude_unless:scope,selected', 'required', 'array', 'min:1', 'max:100'],
            'thread_ids.*' => ['integer'],
        ]);

        $removed = 0;

        $this->threadSubscriptions($user)
            ->when($validated['scope'] === 'selected', fn ($query) => $query->whereIn('thread_id', $validated['thread_ids']))
            ->lazyById()
            ->each(function ($subscription) use (&$removed): void {
                $subscription->delete();
                $removed++;
            });

        return $this->back(trans_choice('profile.flash.subscriptions_removed', $removed, ['count' => $removed]));
    }

    public function removePlugin(Request $request, Plugin $plugin): RedirectResponse
    {
        $this->pluginSubscriptions->unsubscribe($this->member($request), $plugin);

        return $this->back(__('profile.flash.subscription_removed'));
    }

    private function threadSubscriptions(User $user): Builder
    {
        return ForumModels::class('thread_subscription')::query()->where('user_id', $user->id);
    }

    private function member(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function back(string $status): RedirectResponse
    {
        return redirect()->route('profile.show', ['tab' => 'subscriptions'])->with('profile_status', $status);
    }
}
