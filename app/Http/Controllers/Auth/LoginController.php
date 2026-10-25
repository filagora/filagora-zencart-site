<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthRedirectResolver;
use App\Services\Auth\LoginAttemptService;
use App\Support\IntendedPath;
use App\Support\PendingTwoFactorLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const PENDING_TTL_MINUTES = 5;

    public function __construct(
        private readonly LoginAttemptService $loginAttemptService,
        private readonly AuthRedirectResolver $authRedirectResolver,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        // Mirrors Filament's base Login::mount(): an already-authenticated
        // visitor has nothing to do here.
        if (Auth::guard('web')->check()) {
            return redirect()->intended(route('home'));
        }

        return view('auth.login', [
            'pendingUser' => PendingTwoFactorLogin::resolve($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $this->rememberIntendedPath($request);

        $user = $this->loginAttemptService->verifyCredentials((string) $data['username'], (string) $data['password']);
        $remember = (bool) ($data['remember'] ?? false);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put(PendingTwoFactorLogin::SESSION_KEY, [
                'user_id' => $user->id,
                'remember' => $remember,
                'expires_at' => now()->addMinutes(self::PENDING_TTL_MINUTES)->timestamp,
            ]);

            return redirect()->route('login');
        }

        $this->loginAttemptService->completeLogin($user, otp: null, remember: $remember);

        return $this->authRedirectResolver->resolve(route('home'));
    }

    public function storeTwoFactor(Request $request): RedirectResponse
    {
        $pending = PendingTwoFactorLogin::resolve($request);
        abort_if($pending === null, 419);

        $data = $request->validate([
            'otp' => ['nullable', 'string'],
        ]);

        $remember = (bool) session(PendingTwoFactorLogin::SESSION_KEY.'.remember', false);

        $this->loginAttemptService->completeLogin($pending, (string) ($data['otp'] ?? ''), $remember);

        $request->session()->forget(PendingTwoFactorLogin::SESSION_KEY);

        return $this->authRedirectResolver->resolve(route('home'));
    }

    public function cancelTwoFactor(Request $request): RedirectResponse
    {
        $request->session()->forget(PendingTwoFactorLogin::SESSION_KEY);

        return redirect()->route('login');
    }

    private function rememberIntendedPath(Request $request): void
    {
        $intended = IntendedPath::sanitize($request->input('intended'));
        $intended ??= IntendedPath::sanitize($this->refererPath($request));

        if ($intended !== null && ! $this->isAuthPath($intended)) {
            $request->session()->put('url.intended', $intended);
        }
    }

    private function refererPath(Request $request): ?string
    {
        $referer = $request->headers->get('referer');
        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $path = parse_url($referer, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $query = parse_url($referer, PHP_URL_QUERY);

        return is_string($query) && $query !== '' ? "{$path}?{$query}" : $path;
    }

    private function isAuthPath(string $path): bool
    {
        foreach (['/login', '/register', '/password'] as $prefix) {
            if (Str::startsWith($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
