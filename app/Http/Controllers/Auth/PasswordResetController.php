<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendPasswordResetLink;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use SensitiveParameter;

class PasswordResetController extends Controller
{
    /**
     * How long a failed reset step takes at least. Only an address with a pending token costs a
     * password hash, a few hundred milliseconds, so the broker's own 200 ms does not hide it.
     */
    private const FAILED_RESET_MICROSECONDS = 1_000_000;

    public function showRequestForm(): View
    {
        return view('auth.password.forgot');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // The default Illuminate\Auth\Notifications\ResetPassword notification
        // already points at route('password.reset') with no customization needed.
        SendPasswordResetLink::dispatch($request->string('email')->toString());

        // One reply whatever happens (unknown address, throttled, failed send):
        // never confirm or deny whether an address has an account.
        return back()->with('status', __('auth.messages.password_reset_requested'));
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.password.reset', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request, Timebox $timebox): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::default()],
        ]);

        $status = $timebox->call(function (Timebox $timebox) use ($data): string {
            $status = Password::reset(
                $data,
                function (CanResetPassword $user, #[SensitiveParameter] string $password): void {
                    /** @var User $user */
                    $user->forceFill([
                        'password' => $password,
                        'remember_token' => Str::random(60),
                    ])->save();

                    event(new PasswordResetEvent($user));
                },
            );

            if ($status === Password::PASSWORD_RESET) {
                $timebox->returnEarly();
            }

            return $status;
        }, self::FAILED_RESET_MICROSECONDS);

        if ($status !== Password::PASSWORD_RESET) {
            // An unknown address must read like a bad token, or this step lists accounts.
            $status = $status === Password::INVALID_USER ? Password::INVALID_TOKEN : $status;

            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', __($status));
    }
}
