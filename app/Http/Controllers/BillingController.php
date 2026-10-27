<?php

namespace App\Http\Controllers;

use App\Models\User;
use Filagora\Memberships\Exceptions\SubscriptionRefused;
use Filagora\Memberships\Models\MembershipPlan;
use Filagora\Memberships\Models\UserMembership;
use Filagora\Memberships\Services\Memberships;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Starting and cancelling a membership from the profile's billing tab, and where PayPal sends the
 * member back to. The plans, the provider and the rules are the memberships package's.
 */
class BillingController extends Controller
{
    public function __construct(private readonly Memberships $memberships) {}

    public function index(Request $request): RedirectResponse
    {
        $this->member($request);

        return $this->toBilling();
    }

    public function subscribe(Request $request, MembershipPlan $plan): RedirectResponse
    {
        $user = $this->member($request);
        $currency = trim((string) $request->input('currency', ''));

        try {
            $result = $this->memberships->subscribe($user, $plan, $currency !== '' ? $currency : null, route('billing.checkout.success'), route('billing.checkout.cancel'));
        } catch (SubscriptionRefused $refused) {
            return $this->toBilling($refused->getMessage());
        }

        return $result->requiresRedirect()
            ? redirect()->away((string) $result->redirectUrl)
            : $this->toBilling(__('billing.flash.membership_started'));
    }

    public function cancel(Request $request, UserMembership $membership): RedirectResponse
    {
        try {
            $cancelled = $this->memberships->cancel($this->member($request), $membership);
        } catch (SubscriptionRefused $refused) {
            return $this->toBilling($refused->getMessage());
        }

        return $this->toBilling(($cancelled->status?->value ?? $cancelled->status) === 'canceling'
            ? __('billing.messages.membership_canceling')
            : __('billing.messages.membership_canceled'));
    }

    public function checkoutSuccess(): RedirectResponse
    {
        return $this->toBilling(__('billing.flash.checkout_completed'));
    }

    public function checkoutCancel(): RedirectResponse
    {
        return $this->toBilling(__('billing.flash.checkout_canceled'));
    }

    private function member(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function toBilling(?string $status = null): RedirectResponse
    {
        $redirect = redirect()->route('profile.show', ['tab' => 'billing']);

        return $status !== null ? $redirect->with('billing_status', $status) : $redirect;
    }
}
