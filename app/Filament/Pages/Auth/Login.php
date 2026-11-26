<?php

namespace App\Filament\Pages\Auth;

use App\Services\Auth\LoginAttemptOutcome;
use App\Services\Auth\LoginAttemptService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Models\Contracts\FilamentUser;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use SensitiveParameter;

class Login extends BaseLogin
{
    /**
     * Set once a password has been accepted for an account with two-factor sign-in. Showing the
     * code field any earlier would tell anyone which usernames have it.
     */
    #[Locked]
    public bool $requiresTwoFactor = false;

    public function mount(): void
    {
        $intended = $this->resolveIntendedPath(request());

        if ($intended !== null) {
            session()->put('url.intended', $intended);
        }

        parent::mount();
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label(__('auth.labels.username'))
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'username' => $data['username'],
            'password' => $data['password'],
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        // The code field is absent until a first submit has asked for it, and the password is
        // verified again on the submit that carries the code.
        $otp = (string) ($data['otp'] ?? '');

        $result = app(LoginAttemptService::class)->attempt(
            username: (string) $data['username'],
            password: (string) $data['password'],
            otp: $otp === '' ? null : $otp,
            remember: (bool) ($data['remember'] ?? false),
            accessGate: function (Authenticatable $attemptUser): bool {
                if (! ($attemptUser instanceof FilamentUser)) {
                    return true;
                }

                return $attemptUser->canAccessPanel(Filament::getCurrentOrDefaultPanel());
            },
            usernameField: 'data.username',
            otpField: 'data.otp',
        );

        if ($result->outcome === LoginAttemptOutcome::RequiresTwoFactor) {
            $this->requiresTwoFactor = true;

            throw ValidationException::withMessages([
                'data.otp' => __('auth.errors.authentication_code_required'),
            ]);
        }

        return app(LoginResponse::class);
    }

    protected function getOtpFormComponent(): Component
    {
        return TextInput::make('otp')
            ->label(__('auth.labels.authentication_code'))
            ->helperText(__('auth.labels.authentication_code_or_recovery_code'))
            ->minLength(6)
            ->maxLength(21)
            ->regex('/^(\d{6}|[A-Za-z0-9]{10}-[A-Za-z0-9]{10})$/')
            ->visible(fn (): bool => $this->requiresTwoFactor)
            ->autocomplete('one-time-code');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getOtpFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    private function resolveIntendedPath(Request $request): ?string
    {
        $intended = $this->sanitizeIntendedPath($request->query('intended'));
        if ($intended !== null) {
            return $intended;
        }

        $referer = $request->headers->get('referer');
        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $refererPath = parse_url($referer, PHP_URL_PATH);
        if (! is_string($refererPath) || $refererPath === '') {
            return null;
        }

        $refererQuery = parse_url($referer, PHP_URL_QUERY);
        $candidate = $refererPath;
        if (is_string($refererQuery) && $refererQuery !== '') {
            $candidate .= '?'.$refererQuery;
        }

        $candidate = $this->sanitizeIntendedPath($candidate);
        if ($candidate === null || $this->isAuthPath($refererPath)) {
            return null;
        }

        return $candidate;
    }

    private function sanitizeIntendedPath(mixed $intended): ?string
    {
        if (! is_string($intended) || $intended === '') {
            return null;
        }

        if (! Str::startsWith($intended, '/') || Str::startsWith($intended, '//')) {
            return null;
        }

        return $intended;
    }

    private function isAuthPath(string $path): bool
    {
        foreach ([
            '/dashboard/login',
            '/dashboard/register',
            '/dashboard/email-verification',
            '/dashboard/password-reset',
        ] as $prefix) {
            if (Str::startsWith($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
