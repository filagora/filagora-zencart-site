<?php

namespace App\Jobs;

use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as PanelResetPasswordNotification;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Password;
use SensitiveParameter;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Looks the address up and emails the reset link away from the request, so the response takes
 * the same time whether or not the address has an account. With a panel ID the link goes to that
 * panel's reset page, otherwise to the public site's.
 */
class SendPasswordResetLink implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $email, public readonly ?string $panelId = null) {}

    public function handle(): void
    {
        $panel = $this->panelId === null ? null : Filament::getPanel($this->panelId);

        // A failed send is reported, not thrown: on the sync queue it would break the request
        // for real accounts only. The token stays, because a provider that was slow to answer
        // may still deliver its link; a retry works once the broker's throttle has passed.
        try {
            Password::broker($panel?->getAuthPasswordBroker())->sendResetLink(
                ['email' => $this->email],
                $panel === null ? null : function (CanResetPassword $user, #[SensitiveParameter] string $token) use ($panel): void {
                    $this->sendPanelLink($panel, $user, $token);
                },
            );
        } catch (TransportExceptionInterface|ConnectionException $exception) {
            report($exception);
        }
    }

    /**
     * What Filament's own request page does for an account it finds.
     */
    private function sendPanelLink(Panel $panel, CanResetPassword $user, #[SensitiveParameter] string $token): void
    {
        if (! $user instanceof User || ! $user->canAccessPanel($panel)) {
            return;
        }

        $notification = app(PanelResetPasswordNotification::class, ['token' => $token]);
        $notification->url = $panel->getResetPasswordUrl($token, $user);

        $user->notify($notification);

        event(new PasswordResetLinkSent($user));
    }
}
