<?php

namespace App\Services;

use App\Models\RegistrationEmailSendCounter;
use Illuminate\Validation\ValidationException;

class RegistrationEmailApprovalService
{
    public function assertApprovedForVerification(string $email, string $errorKey = 'data.email'): void
    {
        if ($this->isApprovedForVerification($email)) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => $this->deniedMessage(),
        ]);
    }

    public function assertUnderDailySendLimit(string $errorKey = 'data.email'): void
    {
        if ($this->isUnderDailySendLimit()) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => $this->limitReachedMessage(),
        ]);
    }

    public function isUnderDailySendLimit(): bool
    {
        if (! (bool) config('registration.verification_send_limit.enabled', true)) {
            return true;
        }

        return $this->dailySendCount() < $this->dailySendLimit();
    }

    public function recordVerificationEmailSent(): void
    {
        if (! (bool) config('registration.verification_send_limit.enabled', true)) {
            return;
        }

        RegistrationEmailSendCounter::recordSendOn($this->currentSendDate());
    }

    public function limitReachedMessage(): string
    {
        return __('auth.errors.verification_email_limit_reached');
    }

    public function isApprovedForVerification(string $email): bool
    {
        if (! (bool) config('registration.email_allowlist.enabled', false)) {
            return true;
        }

        $email = $this->normalizeEmail($email);
        if ($email === '') {
            return false;
        }

        if (in_array($email, $this->allowedAddresses(), true)) {
            return true;
        }

        $domain = $this->domainFromEmail($email);
        if ($domain === null) {
            return false;
        }

        foreach ($this->allowedDomains() as $allowedDomain) {
            if ($allowedDomain === $domain) {
                return true;
            }

            if (str_starts_with($allowedDomain, '*.') && str_ends_with($domain, substr($allowedDomain, 1))) {
                return true;
            }
        }

        return false;
    }

    public function deniedMessage(): string
    {
        return __('auth.errors.email_not_approved_for_verification');
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function domainFromEmail(string $email): ?string
    {
        $atPosition = strrpos($email, '@');

        if ($atPosition === false) {
            return null;
        }

        $domain = substr($email, $atPosition + 1);

        return $domain === '' ? null : $domain;
    }

    /**
     * @return list<string>
     */
    private function allowedAddresses(): array
    {
        $addresses = config('registration.email_allowlist.addresses', []);

        if (! is_array($addresses)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $address): string => strtolower(trim((string) $address)),
            $addresses
        )));
    }

    /**
     * @return list<string>
     */
    private function allowedDomains(): array
    {
        $domains = config('registration.email_allowlist.domains', []);

        if (! is_array($domains)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $domain): string => strtolower(ltrim(trim((string) $domain), '@')),
            $domains
        )));
    }

    private function dailySendCount(): int
    {
        return RegistrationEmailSendCounter::sentCountFor($this->currentSendDate());
    }

    private function dailySendLimit(): int
    {
        return (int) config('registration.verification_send_limit.daily_max', 300);
    }

    private function currentSendDate(): string
    {
        return now()->toDateString();
    }
}
