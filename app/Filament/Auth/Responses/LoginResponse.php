<?php

namespace App\Filament\Auth\Responses;

use App\Services\Auth\AuthRedirectResolver;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements LoginResponseContract
{
    public function __construct(
        private readonly AuthRedirectResolver $authRedirectResolver,
    ) {}

    public function toResponse($request): RedirectResponse|Redirector
    {
        return $this->authRedirectResolver->resolve(
            Filament::getUrl(),
            route('filament.dashboard.auth.email-verification.prompt', ['modal' => 1]),
        );
    }
}
