<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NewsletterSubscriberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A member subscribing to or leaving the newsletter from their profile.
 */
class NewsletterConsentController extends Controller
{
    public function __construct(private readonly NewsletterSubscriberService $subscribers) {}

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validateWithBag('newsletter', [
            'newsletter_subscribed' => ['required', 'boolean'],
            'newsletter_consent' => ['exclude_unless:newsletter_subscribed,1', 'required', 'accepted'],
        ]);

        if ((bool) $validated['newsletter_subscribed']) {
            $this->subscribers->subscribeFromProfile($user, $request->ip(), (string) $request->userAgent());

            return redirect()->back()->with('profile_status', __('profile.flash.newsletter_subscribed'));
        }

        $this->subscribers->unsubscribeFromProfile($user);

        return redirect()->back()->with('profile_status', __('profile.flash.newsletter_unsubscribed'));
    }
}
