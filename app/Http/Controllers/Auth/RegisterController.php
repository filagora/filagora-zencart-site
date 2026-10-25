<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Services\Auth\AuthRedirectResolver;
use App\Services\Auth\RegistrationService;
use App\Support\IntendedPath;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(
        private readonly RegistrationService $registrationService,
        private readonly AuthRedirectResolver $authRedirectResolver,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = Auth::guard('web')->user();

        // Mirrors App\Filament\Pages\Auth\Register::mount(): a signed-in but
        // still-unverified account is abandoned (not blocking) rather than
        // trapping the visitor on a verify-your-email screen if they meant
        // to start over with a different address.
        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } elseif ($user !== null) {
            return redirect()->intended(route('home'));
        }

        return view('auth.register', [
            'turnstileEnabled' => $this->registrationService->isTurnstileEnabled(),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $intended = IntendedPath::sanitize($request->input('intended'));
        if ($intended !== null) {
            $request->session()->put('url.intended', $intended);
        }

        $data = $request->validated();

        $user = $this->registrationService->register(
            data: $data,
            turnstileToken: $data['turnstile_token'] ?? null,
        );

        $this->registrationService->sendEmailVerificationNotification($user);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return $this->authRedirectResolver->resolve(route('home'));
    }
}
