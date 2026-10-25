<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\RegistrationEmailApprovalService;
use App\Services\SpamDetectionService;
use App\Support\SpamCheckResult;
use Filagora\Compliance\Services\PolicyAcceptance;
use Filagora\Core\Settings\SettingsRegistry;
use Filagora\Entitlements\Models\UserEntitlementGrant;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * The registration business logic behind both App\Filament\Pages\Auth\Register
 * (the /dashboard admin registration) and the public site's own form - kept as
 * one implementation so the two never drift. Rate limiting is a caller/
 * middleware concern (see ThrottleAuthEndpoints), not this service's.
 */
class RegistrationService
{
    public function __construct(
        private readonly SpamDetectionService $spamDetectionService,
        private readonly RegistrationEmailApprovalService $registrationEmailApprovalService,
        private readonly PolicyAcceptance $policyAcceptance,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * $data['password'] may be plain or already-hashed (User::password casts
     * as 'hashed', which only hashes a value that isn't already a hash) - both
     * Filament's own form (which pre-hashes) and the public form (which
     * doesn't) can call this the same way.
     *
     * @param  array{username:string,name:string,email:string,password:string,accept_privacy_policy?:bool}  $data
     */
    public function register(
        #[SensitiveParameter] array $data,
        ?string $turnstileToken,
        string $emailField = 'email',
        string $privacyPolicyField = 'accept_privacy_policy',
        string $turnstileField = 'turnstile_token',
    ): User {
        if ($this->isTurnstileEnabled()) {
            $token = trim((string) $turnstileToken);
            if ($token === '' || ! $this->verifyTurnstileToken($token)) {
                throw ValidationException::withMessages([
                    $turnstileField => __('auth.errors.turnstile_required'),
                ]);
            }
        }

        $this->registrationEmailApprovalService->assertUnderDailySendLimit($emailField);
        $this->registrationEmailApprovalService->assertApprovedForVerification($data['email'], $emailField);

        $spamCheck = $this->assessSpam($data);

        if ($spamCheck->isSpam && (bool) config('spam.actions.reject_spam_registrations', true)) {
            throw ValidationException::withMessages([
                $emailField => __('auth.errors.registration_rejected'),
            ]);
        }

        $acceptedPrivacyPolicy = (bool) ($data['accept_privacy_policy'] ?? false);

        if ($this->privacyPolicyAcknowledgementRequired() && ! $acceptedPrivacyPolicy) {
            throw ValidationException::withMessages([
                $privacyPolicyField => __('auth.errors.privacy_policy_acknowledgement_required'),
            ]);
        }

        $user = User::query()->create([
            'username' => $data['username'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        UserEntitlementGrant::query()->create([
            'user_id' => $user->getKey(),
            'entitlement_key' => (string) config('filagora-zencart.notifications.email_entitlement', 'notifications.email.enabled'),
            'grant_type' => 'registration',
            'source_type' => 'registration',
            'starts_at' => now(),
            'reason' => 'Granted by default at registration.',
        ]);

        if ($this->settings->boolean('moderate_first_post_for_new_users', false)) {
            $user->forceFill(['moderate_first_forum_post' => true])->save();
        }

        if (
            ($spamCheck->isSuspicious || $spamCheck->isSpam)
            && (bool) config('spam.actions.moderate_suspicious_registrations', true)
        ) {
            $user->forceFill(['always_moderate_forum_content' => true])->save();
        }

        if ($acceptedPrivacyPolicy) {
            $this->policyAcceptance->accept($user, request()->ip(), request()->userAgent());
        }

        return $user;
    }

    public function sendEmailVerificationNotification(User $user, string $emailField = 'email'): void
    {
        $this->registrationEmailApprovalService->assertApprovedForVerification((string) $user->email, $emailField);

        if (! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail()) {
            return;
        }

        $notification = app(VerifyEmail::class);
        $notification->url = Filament::getVerifyEmailUrl($user);

        $user->notifyNow($notification);

        $this->registrationEmailApprovalService->recordVerificationEmailSent();
    }

    /**
     * Extracted from App\Filament\Pages\Auth\EmailVerificationPrompt's resend
     * action: a per-user rate limit (independent of any per-request/IP
     * throttle a caller applies) plus the same daily-send-limit/approval
     * checks and notification send as sendEmailVerificationNotification().
     */
    public function resendEmailVerification(User $user, string $field = 'resend'): void
    {
        $rateLimitKey = 'email-verification-resend:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($rateLimitKey, maxAttempts: 2)) {
            throw ValidationException::withMessages([
                $field => __('auth.errors.throttle', [
                    'bucket' => 'verification resend',
                    'seconds' => RateLimiter::availableIn($rateLimitKey),
                ]),
            ]);
        }

        RateLimiter::hit($rateLimitKey);

        $this->registrationEmailApprovalService->assertUnderDailySendLimit($field);
        $this->sendEmailVerificationNotification($user, $field);
    }

    public function isTurnstileEnabled(): bool
    {
        if (! app()->isProduction() && ! (bool) config('services.turnstile.enforce_in_non_production', false)) {
            return false;
        }

        return (bool) config('services.turnstile.enabled')
            && is_string(config('services.turnstile.site_key'))
            && config('services.turnstile.site_key') !== ''
            && is_string(config('services.turnstile.secret_key'))
            && config('services.turnstile.secret_key') !== '';
    }

    /**
     * @param  array{username:string,email:string}  $data
     */
    private function assessSpam(array $data): SpamCheckResult
    {
        return $this->spamDetectionService->assessRegistration([
            'username' => $data['username'],
            'email' => $data['email'],
            'ipaddress' => request()->ip(),
        ]);
    }

    private function privacyPolicyAcknowledgementRequired(): bool
    {
        return $this->policyAcceptance->isEnforced()
            && $this->policyAcceptance->activePolicyVersion() !== null;
    }

    private function verifyTurnstileToken(string $token): bool
    {
        $response = Http::asForm()
            ->timeout(8)
            ->post((string) config('services.turnstile.verify_url'), [
                'secret' => (string) config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => request()->ip(),
            ]);

        if (! $response->ok()) {
            Log::warning('turnstile_verification_http_failure', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        }

        $success = (bool) data_get($response->json(), 'success', false);

        if (! $success) {
            Log::warning('turnstile_verification_failed', [
                'error_codes' => data_get($response->json(), 'error-codes', []),
            ]);
        }

        return $success;
    }
}
