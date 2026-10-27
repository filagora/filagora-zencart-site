<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\NewsletterSubscriberStatus;
use App\Notifications\NewsletterConfirmSubscriptionNotification;
use App\Services\NewsletterSubscriberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class NewsletterController extends Controller
{
    public function __construct(
        private readonly NewsletterSubscriberService $newsletterSubscriberService,
    ) {}

    public function show(): View
    {
        $user = auth()->user();

        return view('newsletter.show', [
            'newsletterSubscriber' => $user instanceof User ? $this->newsletterSubscriberService->forUser($user) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'newsletter_consent' => ['accepted'],
            'website' => ['nullable', 'prohibited'],
        ]);

        $subscriber = $this->newsletterSubscriberService->requestPublicSubscription(
            (string) $validated['email'],
            isset($validated['name']) ? (string) $validated['name'] : null,
            $request->ip(),
            (string) $request->userAgent()
        );

        if ($subscriber->status !== NewsletterSubscriberStatus::Subscribed) {
            Notification::route('mail', $subscriber->email)
                ->notify(new NewsletterConfirmSubscriptionNotification($subscriber));
        }

        return redirect()->route('newsletter.show')
            ->with('newsletter_status', 'If that email can be subscribed, a confirmation link has been sent.');
    }

    public function confirm(Request $request, NewsletterSubscriber $subscriber): View
    {
        $this->newsletterSubscriberService->confirm($subscriber);

        return view('newsletter.confirmed', [
            'subscriber' => $subscriber,
        ]);
    }

    public function unsubscribe(Request $request, NewsletterSubscriber $subscriber): View
    {
        $this->newsletterSubscriberService->unsubscribe($subscriber);

        return view('newsletter.unsubscribed', [
            'subscriber' => $subscriber,
        ]);
    }
}
