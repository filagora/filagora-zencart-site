<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

/**
 * Setting up, confirming and switching off two-factor sign-in, and the recovery codes.
 */
class TwoFactorController extends Controller
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function start(Request $request): RedirectResponse
    {
        $user = $this->member($request);

        if ($user->hasTwoFactorEnabled()) {
            return $this->back(__('auth.messages.two_factor_already_enabled'));
        }

        $user->setTwoFactorPendingSecret($this->google2fa->generateSecretKey(32));
        $user->save();

        return $this->back(__('auth.messages.two_factor_setup_started'));
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $this->member($request);

        $validated = $request->validateWithBag('twoFactor', ['code' => ['required', 'digits:6']]);

        $pending = $user->getTwoFactorPendingSecret();

        if (! is_string($pending) || $pending === '') {
            return redirect()->route('profile.show', ['tab' => 'two-factor'])
                ->withErrors(['code' => __('auth.errors.two_factor_setup_not_initialized')], 'twoFactor');
        }

        if (! $this->google2fa->verifyKey($pending, $validated['code'], 2)) {
            return redirect()->route('profile.show', ['tab' => 'two-factor'])
                ->withErrors(['code' => __('auth.errors.authentication_code_invalid')], 'twoFactor');
        }

        $user->enableTwoFactor($pending);
        $codes = $user->generateRecoveryCodes();
        $user->save();

        return $this->back(__('auth.messages.two_factor_enabled'))->with('new_recovery_codes', $codes);
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $this->member($request);

        $user->disableTwoFactor();
        $user->save();

        return $this->back(__('auth.messages.two_factor_disabled'));
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $this->member($request);

        if (! $user->hasTwoFactorEnabled()) {
            return $this->back(__('auth.errors.two_factor_unavailable'));
        }

        $codes = $user->generateRecoveryCodes();
        $user->save();

        return $this->back(__('auth.messages.two_factor_recovery_codes_regenerated'))->with('new_recovery_codes', $codes);
    }

    private function member(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function back(string $status): RedirectResponse
    {
        return redirect()->route('profile.show', ['tab' => 'two-factor'])->with('profile_status', $status);
    }
}
