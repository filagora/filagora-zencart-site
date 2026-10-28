<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filagora\Entitlements\Filament\RelationManagers\UserEntitlementRequestsRelationManager;
use Filagora\Entitlements\Filament\RelationManagers\UserEntitlementsRelationManager;
use Filagora\Entitlements\Support\Abilities;
use Filagora\Memberships\Filament\RelationManagers\UserMembershipsRelationManager;
use Filagora\Notifications\Filament\RelationManagers\UserNotificationPreferencesRelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * The site's own user screen. Filagora ships no Users resource; a host builds one and
 * attaches the entitlement relation managers to it, as here.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'username';

    protected static ?int $navigationSort = 10;

    /**
     * Staff who can manage entitlements can manage users on this site.
     */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        return Gate::allows(Abilities::MANAGE) ? Response::allow() : Response::deny();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('members');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UserEntitlementsRelationManager::class,
            UserEntitlementRequestsRelationManager::class,
            UserMembershipsRelationManager::class,
            UserNotificationPreferencesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
