<?php

namespace App\Support;

class ApiResponseMessage
{
    public static function unauthenticated(): string
    {
        return __('api.auth.unauthenticated');
    }

    public static function emailVerificationRequired(): string
    {
        return __('api.auth.email_verification_required');
    }

    public static function tokenAbilityRequired(): string
    {
        return __('api.auth.token_ability_required');
    }

    public static function tokenEntitlementRequired(): string
    {
        return __('api.auth.token_entitlement_required');
    }

    public static function accountBanned(): string
    {
        return __('api.auth.account_banned');
    }

    public static function cookieConsentWorkflowDisabled(): string
    {
        return __('api.cookie_consent.workflow_disabled');
    }
}
