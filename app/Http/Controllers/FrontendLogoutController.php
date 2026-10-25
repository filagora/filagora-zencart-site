<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class FrontendLogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $redirectTo = (string) $request->input('redirect_to', '');
        if ($this->isSafeInternalPath($redirectTo)) {
            return redirect()->to($redirectTo);
        }

        $previousPath = parse_url(URL::previous(), PHP_URL_PATH);
        if (is_string($previousPath) && str_starts_with($previousPath, '/dashboard')) {
            return redirect()->route('home');
        }

        return redirect()->back(fallback: route('home'));
    }

    private function isSafeInternalPath(string $path): bool
    {
        if ($path === '' || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return false;
        }

        return (string) parse_url($path, PHP_URL_PATH) !== '';
    }
}
