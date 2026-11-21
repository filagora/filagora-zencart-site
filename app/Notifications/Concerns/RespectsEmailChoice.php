<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Services\Preferences;

/**
 * Settles whether a mail to a member goes out: they want the category on email, and their address
 * is not suppressed (a required category skips both questions, in `Preferences`). Mail to anyone
 * who is not a member, such as the shared staff address, is not a choice and always goes.
 */
trait RespectsEmailChoice
{
    /**
     * @return list<string>
     */
    protected function mailWhenWanted(object $notifiable, ?string $category): array
    {
        if ($category === null || ! $notifiable instanceof User) {
            return ['mail'];
        }

        return app(Preferences::class)->canDeliver($notifiable, $category, NotificationChannel::EMAIL) ? ['mail'] : [];
    }
}
