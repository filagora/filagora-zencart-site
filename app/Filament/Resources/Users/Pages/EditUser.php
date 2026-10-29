<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\UserOperatorAccessManager;
use App\Support\UserImpersonation;
use App\Support\UserOperatorAccessAssignment;
use App\Support\UserTwoFactorAdminStatus;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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
     * Verification is not mass-assignable, so it is set here rather than through fill().
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $verifiedAt = array_key_exists('email_verified_at', $data) ? $data['email_verified_at'] : $record->email_verified_at;
        unset($data['email_verified_at']);

        $record->forceFill(['email_verified_at' => $verifiedAt])->fill($data)->save();

        return $record;
    }

    protected function afterSave(): void
    {
        $requestedEntitlements = array_values(array_filter(
            is_array($this->data['operator_entitlements'] ?? null) ? $this->data['operator_entitlements'] : [],
            static fn (mixed $entitlement): bool => is_string($entitlement) && $entitlement !== ''
        ));
        $actor = auth()->user() instanceof User ? auth()->user() : null;

        app(UserOperatorAccessManager::class)->sync($this->record, $requestedEntitlements, $actor);

        $this->syncSuperAdminRole();

        app(UserTwoFactorAdminStatus::class)->apply(
            $this->record,
            (string) ($this->data['two_factor_status'] ?? UserTwoFactorAdminStatus::DISABLED),
            app(Google2FA::class)
        );
    }

    /**
     * Needs a roles package on the user model (the live site's super admins are a role).
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
