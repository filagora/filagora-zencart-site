<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\Auth\LoginAttemptService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Models\Contracts\FilamentUser;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SensitiveParameter;

class Login extends BaseLogin
{
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
            ->live(onBlur: true)
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

        // otp is always passed as a string (never null) here: this page's OTP
        // field is already shown reactively (see getOtpFormComponent() below)
        // as soon as a 2FA-enabled username is typed, so by submit time the
        // service should go straight to validating the code rather than
        // returning its "requires two factor" outcome - that two-step
        // handshake is only for the public site's non-reactive login form.
        app(LoginAttemptService::class)->attempt(
            username: (string) $data['username'],
            password: (string) $data['password'],
            otp: (string) ($data['otp'] ?? ''),
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
            ->visible(fn (Get $get): bool => $this->userRequiresTwoFactor((string) $get('username')))
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

    private function userRequiresTwoFactor(string $username): bool
    {
        $normalized = trim($username);
        if ($normalized === '') {
            return false;
        }

        $user = User::query()
            ->where('username', $normalized)
            ->first();

        return $user instanceof User && $user->hasTwoFactorEnabled();
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
