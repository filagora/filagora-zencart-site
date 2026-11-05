<?php

namespace Tests\Feature;

use App\Notifications\BugReportUpdatedNotification;
use App\Notifications\EntitlementRequestMailNotification;
use App\Notifications\NewsletterConfirmSubscriptionNotification;
use App\Notifications\PostReportSubmittedNotification;
use App\Notifications\PrivacyMailNotification;
use Filagora\Messages\Notifications\MessageReceivedNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Tests\TestCase;

class EmailLogBodiesConfigTest extends TestCase
{
    public function test_bodies_are_off_unless_switched_on(): void
    {
        $this->assertFalse(config('filagora-logs.bodies.enabled'));
    }

    public function test_every_never_store_entry_is_a_real_class(): void
    {
        $classes = config('filagora-logs.bodies.never_store');

        foreach ($classes as $class) {
            $this->assertTrue(class_exists($class), "{$class} does not exist, so its mail would be stored.");
        }
    }

    public function test_sensitive_mail_is_never_stored(): void
    {
        $classes = config('filagora-logs.bodies.never_store');

        foreach ([
            ResetPassword::class,
            VerifyEmail::class,
            PrivacyMailNotification::class,
            MessageReceivedNotification::class,
            PostReportSubmittedNotification::class,
            NewsletterConfirmSubscriptionNotification::class,
            EntitlementRequestMailNotification::class,
            BugReportUpdatedNotification::class,
        ] as $sensitive) {
            $this->assertContains($sensitive, $classes);
        }
    }
}
