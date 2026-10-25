<?php

namespace App\Services\Auth;

enum LoginAttemptOutcome
{
    case Authenticated;
    case RequiresTwoFactor;
}
