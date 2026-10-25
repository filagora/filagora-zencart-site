<?php

return [
    'labels' => [
        'username' => 'Username',
        'authentication_code' => 'Authentication Code',
        'authentication_code_or_recovery_code' => 'Enter the 6-digit code from your authenticator app, or one of your recovery codes.',
    ],
    'errors' => [
        'authentication_code_required' => 'Authentication code is required.',
        'authentication_code_invalid' => 'Invalid authentication code.',
        'two_factor_unavailable' => 'Two-factor authentication is not available for this account.',
        'two_factor_setup_not_initialized' => 'Two-factor setup is not initialized.',
        'email_not_approved_for_verification' => 'This email address is not currently approved for registration email delivery.',
        'verification_email_limit_reached' => 'Registration is temporarily unavailable due to high signup volume. Please try again later.',
        'turnstile_required' => 'Please complete the security check and try again.',
        'registration_rejected' => 'This registration could not be completed. Please contact support if you believe this is an error.',
        'privacy_policy_acknowledgement_required' => 'You must acknowledge the active privacy policy to register.',
        'invalid_two_factor_status' => 'Invalid two-factor status selection.',
        'enabled_two_factor_requires_pending_setup' => 'An enabled state requires an existing two-factor setup. Choose "Setup Pending" first.',
        'throttle' => 'Too many :bucket attempts. Please try again in :seconds seconds.',
    ],
    'messages' => [
        'two_factor_enabled' => 'Two-factor authentication enabled.',
        'two_factor_disabled' => 'Two-factor authentication disabled.',
        'two_factor_already_enabled' => 'Two-factor authentication is already enabled.',
        'two_factor_setup_started' => 'Two-factor setup started. Scan the QR code and confirm with a 6-digit code.',
        'two_factor_recovery_codes_regenerated' => 'New recovery codes generated. Your old codes no longer work.',
    ],
    'privacy_policy' => [
        'acknowledge' => 'I acknowledge the privacy policy',
        'acknowledge_version' => 'I acknowledge privacy policy version :version',
        'view_policy' => 'view policy',
    ],
];
