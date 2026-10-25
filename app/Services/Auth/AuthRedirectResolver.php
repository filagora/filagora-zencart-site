<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\IntendedPath;
use Filagora\Compliance\Services\PolicyAcceptance;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;

/**
 * Where to send a user immediately after they authenticate (login or
 * registration): shared by Filament's LoginResponse/RegistrationResponse
 * and the public site's own login/register controllers, since the two were
 * previously near-identical copies of this same MustVerifyEmail /
 * privacy-policy-acknowledgment / intended-URL logic.
 */
class AuthRedirectResolver
{
    public function __construct(
        private readonly PolicyAcceptance $policyAcceptance,
    ) {}

    public function resolve(string $fallback, ?string $unverifiedEmailRedirect = null): RedirectResponse
    {
        $intended = IntendedPath::sanitize(session('url.intended'));
        $user = auth()->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            if ($intended !== null) {
                session()->put('url.intended', $intended);
            } else {
                session()->forget('url.intended');
            }

            return redirect()->to($unverifiedEmailRedirect ?? route('verification.notice'));
        }

        if ($user instanceof User && ! $this->policyAcceptance->hasAcknowledgedActivePolicy($user)) {
            session()->put('privacy_policy.intended', $intended ?? $fallback);
            session()->forget('url.intended');

            return redirect()->route('profile.show', ['tab' => 'privacy'])
                ->with('profile_status', __('profile.flash.privacy_acknowledgement_required'))
                ->with('privacy_ack_required', true);
        }

        session()->forget('url.intended');

        return redirect()->to($intended ?? $fallback);
    }
}
