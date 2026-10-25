<?php

namespace App\Services\Auth;

use App\Models\User;

final class LoginAttemptResult
{
    public function __construct(
        public readonly LoginAttemptOutcome $outcome,
        public readonly User $user,
    ) {}
}
