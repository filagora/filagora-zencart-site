<?php

namespace App\Models\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * What the site's user account does beyond the packages: the ban state, the old forum software's
 * password bridge, two-factor sign-in, and the avatar. Brought across from the site this one
 * replaces without change of behaviour; the legacy password check in particular must keep
 * accepting the hashes already in the table.
 */
trait HasSiteAccount
{
    /**
     * @var array<string, bool>
     */
    private static array $localAvatarPathPresence = [];

    public function isCurrentlyBanned(): bool
    {
        if (! $this->is_banned) {
            return false;
        }

        if ($this->banned_until === null) {
            return true;
        }

        return $this->banned_until->isFuture();
    }

    public function hasLegacyPasswordBridge(): bool
    {
        return is_string($this->legacy_password_hash)
            && $this->legacy_password_hash !== '';
    }

    public function verifyLegacyPassword(string $plainPassword): bool
    {
        if (! $this->hasLegacyPasswordBridge()) {
            return false;
        }

        $algorithm = strtolower(trim((string) ($this->legacy_password_algo ?? 'vb3_md5_double')));
        $salt = (string) ($this->legacy_password_salt ?? '');
        // Some SQL dump imports retain escaped double-quotes in salt values.
        // Normalize before verification so existing imported rows can still authenticate.
        $salt = str_replace('\\"', '"', $salt);
        $expected = strtolower((string) $this->legacy_password_hash);

        if (! in_array($algorithm, ['vb3_md5_double', 'vbulletin_md5', 'vbulletin'], true)) {
            return false;
        }

        foreach ($this->legacyPasswordCandidates($plainPassword) as $candidatePassword) {
            $computed = md5(md5($candidatePassword).$salt);
            if (hash_equals($expected, strtolower($computed))) {
                return true;
            }
        }

        return false;
    }

    public function upgradeLegacyPassword(string $plainPassword): void
    {
        $this->password = Hash::make($plainPassword);
        $this->legacy_password_hash = null;
        $this->legacy_password_salt = null;
        $this->legacy_password_algo = null;
        $this->legacy_password_imported_at = null;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function gravatarUrl(int $size = 96): string
    {
        $normalizedEmail = strtolower(trim((string) $this->email));
        $hash = md5($normalizedEmail);
        $safeSize = max(16, min(512, $size));

        return sprintf(
            'https://www.gravatar.com/avatar/%s?s=%d&d=%s&r=%s',
            $hash,
            $safeSize,
            rawurlencode('identicon'),
            'g'
        );
    }

    public function avatarUrl(int $size = 96): string
    {
        $safeSize = max(16, min(512, $size));

        if (is_string($this->avatar_path) && $this->avatar_path !== '') {
            if (filter_var($this->avatar_path, FILTER_VALIDATE_URL)) {
                return $this->avatar_path;
            }

            if ($this->localAvatarPathExists($this->avatar_path)) {
                return route('users.avatar.show', [
                    'user' => $this,
                    'filename' => basename($this->avatar_path),
                    's' => $safeSize,
                ]);
            }

            return $this->gravatarUrl($safeSize);
        }

        return $this->gravatarUrl($safeSize);
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->avatarUrl();
    }

    private function localAvatarPathExists(string $path): bool
    {
        if (! array_key_exists($path, self::$localAvatarPathPresence)) {
            self::$localAvatarPathPresence[$path] = Storage::disk('public')->exists($path);
        }

        return self::$localAvatarPathPresence[$path];
    }

    /**
     * @return list<string>
     */
    private function legacyPasswordCandidates(string $plainPassword): array
    {
        $candidates = [];
        $addCandidate = static function (array &$bucket, string $value): void {
            $bucket[md5($value)] = $value;
        };

        $addCandidate($candidates, $plainPassword);

        if (class_exists(\Normalizer::class)) {
            $nfc = \Normalizer::normalize($plainPassword, \Normalizer::FORM_C);
            if (is_string($nfc) && $nfc !== '') {
                $addCandidate($candidates, $nfc);
            }
        }

        foreach (['ISO-8859-1', 'Windows-1252'] as $encoding) {
            $converted = @iconv('UTF-8', $encoding.'//IGNORE', $plainPassword);
            if ($converted !== false && $converted !== '') {
                $addCandidate($candidates, $converted);
            }
        }

        return array_values($candidates);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled_at !== null && $this->getTwoFactorSecret() !== null;
    }

    public function getTwoFactorSecret(): ?string
    {
        return $this->decryptSecret((string) ($this->two_factor_secret ?? ''));
    }

    public function getTwoFactorPendingSecret(): ?string
    {
        return $this->decryptSecret((string) ($this->two_factor_pending_secret ?? ''));
    }

    public function setTwoFactorPendingSecret(?string $secret): void
    {
        $this->two_factor_pending_secret = $secret !== null ? encrypt($secret) : null;
    }

    public function enableTwoFactor(string $secret): void
    {
        $this->two_factor_secret = encrypt($secret);
        $this->two_factor_pending_secret = null;
        $this->two_factor_enabled_at = now();
    }

    public function disableTwoFactor(): void
    {
        $this->two_factor_secret = null;
        $this->two_factor_pending_secret = null;
        $this->two_factor_enabled_at = null;
        $this->two_factor_recovery_codes = null;
    }

    /**
     * Generates a fresh set of one-time recovery codes, replacing any existing
     * ones, and sets them on the model (caller is responsible for saving).
     *
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        $codes = collect(range(1, 8))
            ->map(static fn (): string => Str::random(10).'-'.Str::random(10))
            ->all();

        $this->two_factor_recovery_codes = $codes;

        return $codes;
    }

    public function recoveryCodesRemainingCount(): int
    {
        return count($this->two_factor_recovery_codes ?? []);
    }

    /**
     * Consumes a recovery code if it's valid and unused. Caller is
     * responsible for saving the model afterward so the code can't be
     * reused. Codes are matched case-sensitively, same as the random
     * alphanumeric charset they're generated from.
     */
    public function redeemRecoveryCode(string $code): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        $codes = $this->two_factor_recovery_codes ?? [];
        if (! in_array($code, $codes, true)) {
            return false;
        }

        $this->two_factor_recovery_codes = array_values(array_diff($codes, [$code]));

        return true;
    }

    private function decryptSecret(string $payload): ?string
    {
        if ($payload === '') {
            return null;
        }

        try {
            $decrypted = decrypt($payload);
        } catch (\Throwable) {
            return null;
        }

        return is_string($decrypted) && $decrypted !== '' ? $decrypted : null;
    }

    public function allowsPrivateMessages(): bool
    {
        return (bool) ($this->allow_private_messages ?? true);
    }

    public function getJoinedAtAttribute(): Carbon|CarbonImmutable|null
    {
        return $this->legacy_registered_at ?? $this->created_at;
    }
}
