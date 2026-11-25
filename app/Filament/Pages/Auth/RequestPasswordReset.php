<?php

namespace App\Filament\Pages\Auth;

use App\Jobs\SendPasswordResetLink;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Components\Attributes\Exposed;
use Illuminate\Support\Facades\Password;

/**
 * The stock page answers "We can't find a user with that email address", and takes longer for a
 * real account, either of which lets anyone test which addresses have accounts. This one queues
 * the work and gives every address the same reply.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    #[Exposed]
    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $data = $this->form->getState();

        SendPasswordResetLink::dispatch($data['email'], Filament::getCurrentOrDefaultPanel()?->getId());

        $this->getSentNotification(Password::RESET_LINK_SENT)?->send();

        $this->form->fill();
    }

    protected function getSentNotification(string $status): ?Notification
    {
        return Notification::make()
            ->title(__('auth.messages.password_reset_requested'))
            ->success();
    }
}
