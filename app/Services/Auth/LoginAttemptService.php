<?php

namespace App\Services\Auth;

use App\Models\User;
use Closure;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use SensitiveParameter;

/**
 * The credential/ban/2FA verification core of both App\Filament\Pages\Auth\Login
 * (the /dashboard admin login) and the public site's own login form - kept as one
 * implementation so the two never drift. Rate limiting is a caller/middleware
 * concern (see ThrottleAuthEndpoints), not this service's.
 *
 * Split into verifyCredentials()/completeLogin() (attempt() is a convenience
 * wrapper over both, for a single-request login) so a caller whose UI does 2FA
 * as a genuine second step - the public login form - can verify the password
 * once, then complete the login later from just a user id, without ever
 * storing or re-transmitting the plaintext password.
 */
class LoginAttemptService
{
    /**
     * @param  string  $usernameField  Validation error key for a credentials
     *                                 failure - Filament's Livewire form needs "data.username", the
     *                                 public site's plain form just "username".
     */
    public function verifyCredentials(
        string $username,
        #[SensitiveParameter] string $password,
        string $usernameField = 'username',
    ): User {
        $credentials = ['username' => $username, 'password' => $password];

        $authGuard = Auth::guard('web');
        /** @var SessionGuard $authGuard */
        $authProvider = $authGuard->getProvider(); /** @phpstan-ignore-line */
        $user = $authProvider->retrieveByCredentials($credentials);

        $credentialsAreValid = $user && $authProvider->validateCredentials($user, $credentials);

        if (! $credentialsAreValid) {
            $user = $this->attemptLegacyPasswordBridge($credentials);

            if (! $user instanceof User) {
                $this->fireFailedEvent($authGuard, $user, $credentials);
                $this->throwFailureValidationException($usernameField);
            }
        }

        if ($user->isCurrentlyBanned()) {
            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException($usernameField);
        }

        return $user;
    }

    /**
     * @param  Closure(Authenticatable): bool|null  $accessGate  Optional extra gate
     *                                                           evaluated once 2FA passes, before the session is established
     *                                                           (e.g. Filament's canAccessPanel() check).
     * @param  string  $otpField  Validation error key for a 2FA-code failure
     *                            ("data.otp" for Filament's Livewire form, "otp" for the public form).
     */
    public function completeLogin(
        User $user,
        ?string $otp,
        bool $remember,
        ?Closure $accessGate = null,
        string $otpField = 'otp',
    ): void {
        $this->assertTwoFactorCodeIsValid($user, $otp, $otpField);

        if ($accessGate !== null && ! $accessGate($user)) {
            // Unreachable today: User::canAccessPanel() unconditionally returns
            // true. Kept so a future real gate still fails closed and audits
            // the same way a bad password does.
            $this->fireFailedEvent(Auth::guard('web'), $user, ['username' => (string) $user->username, 'password' => '']);
            $this->throwFailureValidationException('username');
        }

        Auth::guard('web')->login($user, $remember);
        session()->regenerate();
    }

    /**
     * Single-request login: verify credentials and (if applicable) the 2FA
     * code in one call. Used by Filament's page, whose OTP field is already
     * shown reactively before submit. The public site's two-step form calls
     * verifyCredentials() and completeLogin() separately instead.
     *
     * @param  Closure(Authenticatable): bool|null  $accessGate
     */
    public function attempt(
        string $username,
        #[SensitiveParameter] string $password,
        ?string $otp,
        bool $remember,
        ?Closure $accessGate = null,
        string $usernameField = 'username',
        string $otpField = 'otp',
    ): LoginAttemptResult {
        $user = $this->verifyCredentials($username, $password, $usernameField);

        if ($user->hasTwoFactorEnabled() && $otp === null) {
            return new LoginAttemptResult(LoginAttemptOutcome::RequiresTwoFactor, $user);
        }

        $this->completeLogin($user, $otp, $remember, $accessGate, $otpField);

        return new LoginAttemptResult(LoginAttemptOutcome::Authenticated, $user);
    }

    /**
     * @param  array{username:string,password:string}  $credentials
     */
    private function attemptLegacyPasswordBridge(#[SensitiveParameter] array $credentials): ?User
    {
        $username = trim($credentials['username']);
        $password = $credentials['password'];

        if ($username === '' || $password === '') {
            return null;
        }

        $user = User::query()->where('username', $username)->first();

        if (! $user instanceof User || ! $user->verifyLegacyPassword($password)) {
            return null;
        }

        $user->upgradeLegacyPassword($password);
        $user->save();

        return $user;
    }

    /**
     * @param  array{username:string,password:string}  $credentials
     */
    private function fireFailedEvent(Guard $guard, ?Authenticatable $user, #[SensitiveParameter] array $credentials): void
    {
        event(app(Failed::class, [
            'guard' => property_exists($guard, 'name') ? $guard->name : '',
            'user' => $user,
            'credentials' => $credentials,
        ]));
    }

    private function throwFailureValidationException(string $usernameField): never
    {
        throw ValidationException::withMessages([
            $usernameField => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    private function assertTwoFactorCodeIsValid(User $user, ?string $code, string $otpField): void
    {
        if (! $user->hasTwoFactorEnabled()) {
            return;
        }

        $code = trim((string) $code);

        if ($code === '') {
            throw ValidationException::withMessages([
                $otpField => __('auth.errors.authentication_code_required'),
            ]);
        }

        if ($this->isRecoveryCodeFormat($code)) {
            if (! $user->redeemRecoveryCode($code)) {
                throw ValidationException::withMessages([
                    $otpField => __('auth.errors.authentication_code_invalid'),
                ]);
            }

            $user->save();

            return;
        }

        $secret = $user->getTwoFactorSecret();
        if (! is_string($secret) || $secret === '') {
            throw ValidationException::withMessages([
                $otpField => __('auth.errors.two_factor_unavailable'),
            ]);
        }

        $isValid = app(Google2FA::class)->verifyKey($secret, $code, 2);
        if (! $isValid) {
            throw ValidationException::withMessages([
                $otpField => __('auth.errors.authentication_code_invalid'),
            ]);
        }
    }

    /**
     * Recovery codes are generated as two 10-character alphanumeric segments
     * joined by a hyphen (see User::generateRecoveryCodes()) - distinct
     * enough from a 6-digit TOTP code that both can share the same input.
     */
    private function isRecoveryCodeFormat(string $code): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{10}-[A-Za-z0-9]{10}$/', $code);
    }
}
