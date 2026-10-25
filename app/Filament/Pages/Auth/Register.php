<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\Auth\RegistrationService;
use App\Support\LegalPages;
use Filagora\Compliance\Services\PolicyAcceptance;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class Register extends BaseRegister
{
    protected Width|string|null $maxWidth = Width::FiveExtraLarge;

    public function mount(): void
    {
        $user = Filament::auth()->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            Filament::auth()->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        parent::mount();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'sm' => 2,
            ])
            ->components([
                $this->getUsernameFormComponent(),
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getTurnstileTokenFormComponent(),
                $this->getTurnstileWidgetFormComponent(),
                $this->getPrivacyPolicyAcknowledgeComponent(),
            ]);
    }

    protected function getUsernameFormComponent(): Component
    {
        return TextInput::make('username')
            ->label(__('auth.labels.username'))
            ->required()
            ->minLength(3)
            ->maxLength(32)
            ->regex('/^[A-Za-z0-9_.-]+$/')
            ->unique($this->getUserModel(), 'username')
            ->autofocus();
    }

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('name')
            ->label(__('filament-panels::auth/pages/register.form.name.label'))
            ->required()
            ->maxLength(255);
    }

    protected function getPrivacyPolicyAcknowledgeComponent(): Component
    {
        return Checkbox::make('accept_privacy_policy')
            ->label(function (): HtmlString {
                $policyVersion = app(PolicyAcceptance::class)->activePolicyVersion()?->version;
                $label = $policyVersion === null
                    ? __('auth.privacy_policy.acknowledge')
                    : __('auth.privacy_policy.acknowledge_version', ['version' => $policyVersion]);

                $policyUrl = app(LegalPages::class)->privacyPageUrl();

                return new HtmlString($label.' (<a href="'.$policyUrl.'" target="_blank" rel="noopener noreferrer">'.__('auth.privacy_policy.view_policy').'</a>)');
            })
            ->visible(fn (): bool => app(PolicyAcceptance::class)->isEnforced() && app(PolicyAcceptance::class)->activePolicyVersion() !== null)
            ->required(fn (): bool => app(PolicyAcceptance::class)->isEnforced() && app(PolicyAcceptance::class)->activePolicyVersion() !== null)
            ->validationMessages([
                'required' => __('auth.errors.privacy_policy_acknowledgement_required'),
            ])
            ->columnSpanFull()
            ->dehydrated();
    }

    protected function getTurnstileTokenFormComponent(): Component
    {
        return Hidden::make('turnstile_token')
            ->dehydrated(true)
            ->visible(fn (): bool => app(RegistrationService::class)->isTurnstileEnabled());
    }

    protected function getTurnstileWidgetFormComponent(): Component
    {
        return ViewField::make('turnstile_widget')
            ->view('filament.forms.components.turnstile')
            ->viewData([
                'enabled' => app(RegistrationService::class)->isTurnstileEnabled(),
                'siteKey' => (string) config('services.turnstile.site_key', ''),
                'statePath' => 'data.turnstile_token',
            ])
            ->dehydrated(false)
            ->columnSpanFull()
            ->visible(fn (): bool => app(RegistrationService::class)->isTurnstileEnabled());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(array $data): Model
    {
        try {
            return app(RegistrationService::class)->register(
                data: $data,
                turnstileToken: $data['turnstile_token'] ?? null,
                emailField: 'data.email',
                privacyPolicyField: 'data.accept_privacy_policy',
                turnstileField: 'data.turnstile_token',
            );
        } catch (ValidationException $e) {
            // Turnstile tokens are one-time-use: the widget needs to reset
            // and fetch a fresh one before the user can retry.
            if (array_key_exists('data.turnstile_token', $e->errors())) {
                $this->dispatch('turnstile:reset', statePath: 'data.turnstile_token');
            }

            throw $e;
        }
    }

    protected function sendEmailVerificationNotification(Model $user): void
    {
        /** @var User $user */
        app(RegistrationService::class)->sendEmailVerificationNotification($user, 'data.email');
    }
}
