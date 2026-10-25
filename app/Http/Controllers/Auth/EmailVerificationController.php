<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\RegistrationService;
use App\Support\IntendedPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        $intended = IntendedPath::sanitize($request->query('intended'));
        if ($intended !== null) {
            $request->session()->put('url.intended', $intended);
        }

        return view('auth.verify_email');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        abort_unless($user instanceof User, 403);

        if (! $user->hasVerifiedEmail()) {
            $this->registrationService->resendEmailVerification($user, 'resend');
        }

        return back()->with('status', 'A new verification link has been sent to your email address.');
    }
}
