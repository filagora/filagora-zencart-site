<?php

namespace App\Filament\Auth\Responses;

use App\Services\Auth\AuthRedirectResolver;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class RegistrationResponse implements RegistrationResponseContract
{
    public function __construct(
        private readonly AuthRedirectResolver $authRedirectResolver,
    ) {}

    public function toResponse($request): RedirectResponse|Redirector
    {
        return $this->authRedirectResolver->resolve(
            route('home'),
            route('verification.notice', ['modal' => 1]),
        );
    }
}
