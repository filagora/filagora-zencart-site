<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\Auth\RegistrationService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Pages\EmailVerification\EmailVerificationPrompt as BaseEmailVerificationPrompt;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Validation\ValidationException;

class EmailVerificationPrompt extends BaseEmailVerificationPrompt
{
    protected function sendEmailVerificationNotification(MustVerifyEmail $user): void
    {
        /** @var User $user */
        app(RegistrationService::class)->sendEmailVerificationNotification($user, 'resend');
    }

    public function resendNotificationAction(): Action
    {
        return Action::make('resendNotification')
            ->link()
            ->label(__('filament-panels::auth/pages/email-verification/email-verification-prompt.actions.resend_notification.label').'.')
            ->size('sm')
            ->action(function (): void {
                try {
                    $this->rateLimit(2);
                } catch (TooManyRequestsException $exception) {
                    $this->getRateLimitedNotification($exception)?->send();

                    return;
                }

                /** @var User $user */
                $user = $this->getVerifiable();

                try {
                    app(RegistrationService::class)->resendEmailVerification($user, 'resend');
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title($e->errors()['resend'][0] ?? $e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('filament-panels::auth/pages/email-verification/email-verification-prompt.notifications.notification_resent.title'))
                    ->success()
                    ->send();
            });
    }
}
