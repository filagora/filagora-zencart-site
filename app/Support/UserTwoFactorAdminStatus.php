<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class UserTwoFactorAdminStatus
{
    public const DISABLED = 'disabled';

    public const PENDING = 'pending';

    public const ENABLED = 'enabled';

    public function current(User $user): string
    {
        if ($user->hasTwoFactorEnabled()) {
            return self::ENABLED;
        }

        if ($user->getTwoFactorPendingSecret() !== null) {
            return self::PENDING;
        }

        return self::DISABLED;
    }

    public function apply(User $user, string $requestedStatus, Google2FA $google2fa): void
    {
        $currentStatus = $this->current($user);

        if ($requestedStatus === $currentStatus) {
            return;
        }

        if (! in_array($requestedStatus, [self::DISABLED, self::PENDING, self::ENABLED], true)) {
            throw ValidationException::withMessages([
                'data.two_factor_status' => __('auth.errors.invalid_two_factor_status'),
            ]);
        }

        if ($requestedStatus === self::DISABLED) {
            $user->disableTwoFactor();
            $user->save();

            return;
        }

        if ($requestedStatus === self::PENDING) {
            $user->disableTwoFactor();
            $user->setTwoFactorPendingSecret($google2fa->generateSecretKey(32));
            $user->save();

            return;
        }

        $pendingSecret = $user->getTwoFactorPendingSecret();

        if (! is_string($pendingSecret) || $pendingSecret === '') {
            throw ValidationException::withMessages([
                'data.two_factor_status' => __('auth.errors.enabled_two_factor_requires_pending_setup'),
            ]);
        }

        $user->enableTwoFactor($pendingSecret);
        $user->save();
    }
}
