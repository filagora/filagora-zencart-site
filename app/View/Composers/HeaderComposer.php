<?php

namespace App\View\Composers;

use App\Models\User;
use App\Services\Auth\RegistrationService;
use App\Support\PendingTwoFactorLogin;
use Filagora\Messages\Services\Mailbox;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The header's own data: the signed-in person's unread private messages and notifications, and the
 * two bits of sign-in state the always-present (hidden) login and register forms need.
 */
class HeaderComposer
{
    public function __construct(private readonly Request $request, private readonly Mailbox $mailbox) {}

    public function compose(View $view): void
    {
        $user = $this->request->user();
        $signedIn = $user instanceof User;

        $view->with('unreadPrivateMessageCount', $signedIn ? $this->mailbox->unreadCount($user) : 0);
        $view->with('unreadNotificationCount', $signedIn ? $user->unreadNotifications()->count() : 0);
        $view->with('recentNotifications', $signedIn
            ? $user->notifications()->latest()->limit(max(1, (int) config('notifications.bell_dropdown_limit', 5)))->get()
            : collect());

        // The login form is included on every page, not just /login, so a visitor part-way through
        // two-factor sign-in must get the code field straight away. The same goes for the register form.
        $view->with('pendingTwoFactorLoginUser', $this->request->hasSession() ? PendingTwoFactorLogin::resolve($this->request) : null);
        $view->with('registerTurnstileEnabled', app(RegistrationService::class)->isTurnstileEnabled());
    }
}
