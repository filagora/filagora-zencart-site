<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Auth\RegistrationService;
use App\Services\NewsletterSubscriberService;
use App\Services\UserOperatorAccessManager;
use App\Support\UserImpersonation;
use App\Support\UserOperatorAccessAssignment;
use App\Support\UserTwoFactorAdminStatus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Only the keys this editor may manage are offered, so only those are filled in; a grant the
        // editor cannot see stays as it is when they save.
        $data['operator_entitlements'] = array_values(array_intersect(
            app(UserOperatorAccessManager::class)->selectedOperatorEntitlements($this->record),
            UserOperatorAccessAssignment::visibleEntitlementKeys(auth()->user() instanceof User ? auth()->user() : null),
        ));
        $data['is_super_admin'] = method_exists($this->record, 'hasRole') && $this->record->hasRole('super_admin');
        $data['two_factor_status'] = app(UserTwoFactorAdminStatus::class)->current($this->record);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['signature_format'] = filled($data['signature'] ?? null) ? 'markdown' : null;

        if (($data['is_banned'] ?? false) && blank($data['banned_at'] ?? null)) {
            $data['banned_at'] = now();
        }

        if (! ($data['is_banned'] ?? false)) {
            $data['banned_at'] = null;
            $data['banned_until'] = null;
        }

        unset($data['ban_duration'], $data['operator_entitlements'], $data['is_super_admin'], $data['two_factor_status']);

        return $data;
    }

    /**
     * Verification is not mass-assignable, so it is set here rather than through fill(). A changed
     * address is unverified again unless the editor set the verified date in the same save, and a
     * confirmation mail goes to the new address, as when members change their own.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $oldEmail = (string) $record->email;
        $emailChanged = isset($data['email']) && ! hash_equals(mb_strtolower($oldEmail), mb_strtolower((string) $data['email']));
        $verifiedAt = array_key_exists('email_verified_at', $data) ? $data['email_verified_at'] : $record->email_verified_at;
        $verifiedDateEdited = $verifiedAt !== $record->email_verified_at && (string) $verifiedAt !== (string) $record->email_verified_at;
        unset($data['email_verified_at']);

        if ($emailChanged && ! $verifiedDateEdited) {
            $verifiedAt = null;
        }

        $record->forceFill(['email_verified_at' => $verifiedAt])->fill($data)->save();

        if ($emailChanged) {
            // The newsletter list keeps the old address with its consent; it must not follow the account.
            app(NewsletterSubscriberService::class)->unlinkUserForEmailChange($record, $oldEmail);

            if ($verifiedAt === null) {
                $this->sendVerificationToNewAddress($record);
            }
        }

        return $record;
    }

    private function sendVerificationToNewAddress(Model $record): void
    {
        try {
            app(RegistrationService::class)->sendEmailVerificationNotification($record);
        } catch (ValidationException $exception) {
            Notification::make()
                ->warning()
                ->title('The address was changed but no confirmation email was sent.')
                ->body(collect($exception->errors())->flatten()->first())
                ->send();
        }
    }

    protected function afterSave(): void
    {
        $requestedEntitlements = array_values(array_filter(
            is_array($this->data['operator_entitlements'] ?? null) ? $this->data['operator_entitlements'] : [],
            static fn (mixed $entitlement): bool => is_string($entitlement) && $entitlement !== ''
        ));
        $actor = auth()->user() instanceof User ? auth()->user() : null;

        // Nobody changes their own operator access or super admin role here: a slip would lock out
        // the one person who can put it right.
        if (! $this->isEditingSelf()) {
            app(UserOperatorAccessManager::class)->sync($this->record, $requestedEntitlements, $actor);
            $this->syncSuperAdminRole();
        }

        app(UserTwoFactorAdminStatus::class)->apply(
            $this->record,
            (string) ($this->data['two_factor_status'] ?? UserTwoFactorAdminStatus::DISABLED),
            app(Google2FA::class)
        );
    }

    private function isEditingSelf(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->is($this->record);
    }

    /**
     * The super admin role, read and written through the user model's roles.
     */
    protected function syncSuperAdminRole(): void
    {
        $actor = auth()->user();

        if (! method_exists($this->record, 'hasRole') || ! $actor instanceof User || ! UserOperatorAccessAssignment::canManageSuperAdmin($actor)) {
            return;
        }

        $shouldBeSuperAdmin = (bool) ($this->data['is_super_admin'] ?? false);
        $isSuperAdmin = $this->record->hasRole('super_admin');

        if ($shouldBeSuperAdmin && ! $isSuperAdmin) {
            $this->record->assignRole('super_admin');
        } elseif (! $shouldBeSuperAdmin && $isSuperAdmin) {
            $this->record->removeRole('super_admin');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('actAsUser')
                ->label('Act As User')
                ->icon('heroicon-o-user-circle')
                ->color('warning')
                ->visible(function (): bool {
                    $actor = auth()->user();

                    return $actor instanceof User
                        && Gate::forUser($actor)->allows('impersonate', $this->record);
                })
                ->requiresConfirmation()
                ->action(function (): void {
                    $actor = auth()->user();

                    abort_unless($actor instanceof User, 403);
                    Gate::forUser($actor)->authorize('impersonate', $this->record);

                    app(UserImpersonation::class)->start(request(), $actor, $this->record);

                    $this->redirect(route('home'), navigate: true);
                }),
        ];
    }
}
