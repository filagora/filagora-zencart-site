<?php

use App\Notifications\BugReportUpdatedNotification;
use App\Notifications\EntitlementRequestMailNotification;
use App\Notifications\NewsletterConfirmSubscriptionNotification;
use App\Notifications\PostReportSubmittedNotification;
use App\Notifications\PrivacyMailNotification;
use Filagora\Messages\Notifications\MessageReceivedNotification;
use Filament\Auth\Notifications\VerifyEmail as FilamentVerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;

/*
 * Overrides of the package's email log settings. A config key set here
 * replaces the package's value outright, so `never_store` repeats the
 * package defaults (the password reset and verification mail).
 */
return [
    'bodies' => [
        'enabled' => (bool) env('EMAIL_LOG_BODIES', false),
        'never_store' => [
            ResetPassword::class,
            VerifyEmail::class,
            FilamentVerifyEmail::class,
            NewsletterConfirmSubscriptionNotification::class,
            PrivacyMailNotification::class,
            MessageReceivedNotification::class,
            PostReportSubmittedNotification::class,
            EntitlementRequestMailNotification::class,
            BugReportUpdatedNotification::class,
        ],
    ],
];
