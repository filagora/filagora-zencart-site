<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\UserImpersonation;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

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
