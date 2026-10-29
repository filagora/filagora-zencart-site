<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Actions\BanUserAction;
use App\Models\User;
use App\Support\UserOperatorAccessAssignment;
use App\TimeFormat;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $components = [
            Grid::make([
                'default' => 1,
                'xl' => 2,
            ])
                ->schema([
                    Grid::make(1)
                        ->schema([
                            self::identitySection(),
                            self::profileSection(),
                        ]),
                    Grid::make(1)
                        ->schema([
                            self::accountSection(),
                            self::superAdminSection(),
                            self::operatorAccessSection(),
                            self::moderationSection(),
                        ]),
                ]),
        ];

        return $schema
            ->columns(1)
            ->components($components);
    }

    public static function identitySection(): Section
    {
        return Section::make('Identity')
            ->description('Core sign-in and public-facing account details.')
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('username')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('timezone')
                    ->maxLength(255)
                    ->placeholder('Europe/London'),
                Select::make('time_format')
                    ->options(collect(TimeFormat::cases())->mapWithKeys(
                        fn (TimeFormat $case): array => [$case->value => $case->label()]
                    ))
                    ->default(TimeFormat::TWELVE_HOUR->value)
                    ->required(),
                DateTimePicker::make('email_verified_at')
                    ->label('Email Verified At'),
                DateTimePicker::make('membership_expires_at')
                    ->label('Membership Expires At'),
            ])
            ->columns(2);
    }

    public static function accountSection(): Section
    {
        return Section::make('Account')
            ->description('Messaging and subscription preferences used across the forum.')
            ->schema([
                Toggle::make('allow_private_messages')
                    ->label('Private Messages')
                    ->required()
                    ->default(true),
                Select::make('thread_subscription_email_frequency')
                    ->label('Thread Email Frequency')
                    ->options(['instant' => 'instant', 'daily' => 'daily', 'weekly' => 'weekly'])
                    ->required()
                    ->default('instant'),
                Select::make('two_factor_status')
                    ->label('Two Factor Status')
                    ->options([
                        'disabled' => 'Disabled',
                        'pending' => 'Setup Pending',
                        'enabled' => 'Enabled',
                    ])
                    ->required()
                    ->helperText('Use "Setup Pending" to start a fresh authenticator setup. "Enabled" can only be kept or promoted from an existing pending setup.')
                    ->native(false),
                DateTimePicker::make('two_factor_enabled_at')
                    ->label('Two Factor Enabled At')
                    ->disabled()
                    ->dehydrated(false),
            ])
            ->columns(1);
    }

    public static function operatorAccessSection(): Section
    {
        return Section::make('Direct Operator Access')
            ->description('Manage direct operator entitlement grants for this account. Effective access from legacy compatibility sources still appears in the entitlement summary above during migration.')
            ->schema([
                Select::make('operator_entitlements')
                    ->label('Direct Operator Entitlements')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(fn (): array => UserOperatorAccessAssignment::optionsFor(auth()->user()))
                    ->disabled(fn (?User $record): bool => self::isSelf($record))
                    ->helperText(fn (?User $record): string => self::isSelf($record)
                        ? 'You cannot change your own operator access.'
                        : 'Operator access is now managed as direct entitlement grants rather than assigned roles.')
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    public static function superAdminSection(): Section
    {
        $manageable = fn (): bool => method_exists(User::class, 'hasRole')
            && auth()->user() instanceof User
            && UserOperatorAccessAssignment::canManageSuperAdmin(auth()->user());

        return Section::make('Super Admin')
            ->description('Assign or remove full administrative access for this account without exposing generic role management.')
            ->schema([
                Toggle::make('is_super_admin')
                    ->label('Super Admin Access')
                    ->default(false)
                    ->dehydrated(false)
                    ->disabled(fn (?User $record): bool => self::isSelf($record))
                    ->helperText(fn (?User $record): string => self::isSelf($record)
                        ? 'You cannot change your own super admin access.'
                        : 'Super admins bypass explicit operator grants and can access all operator-managed surfaces.')
                    ->visible($manageable),
            ])
            ->columns(1)
            ->visible($manageable);
    }

    public static function moderationSection(): Section
    {
        return Section::make('Moderation')
            ->description('Ban status and staff-facing moderation notes.')
            ->schema([
                Toggle::make('always_moderate_forum_content')
                    ->label('Always Moderate Forum Content')
                    ->required()
                    ->default(false)
                    ->helperText('Every new thread and reply from this user requires staff approval.'),
                Toggle::make('moderate_first_forum_post')
                    ->label('Moderate First Forum Post')
                    ->required()
                    ->default(false)
                    ->helperText('The user’s first thread or reply requires approval, even in unmoderated forums.'),
                Toggle::make('is_banned')
                    ->disabled(fn (?User $record): bool => self::isSelf($record))
                    ->required()
                    ->default(false)
                    ->live()
                    ->afterStateUpdated(function (bool $state, Get $get, Set $set): void {
                        if (! $state) {
                            return;
                        }
                        if (blank($get('banned_at'))) {
                            $set('banned_at', now());
                        }
                        $duration = (string) ($get('ban_duration') ?: '30d');
                        if ($duration !== 'custom') {
                            $set('banned_until', app(BanUserAction::class)->resolveUntil($duration, null));
                        }
                    }),
                ToggleButtons::make('ban_duration')
                    ->label('Duration')
                    ->inline()
                    ->dehydrated(false)
                    ->live()
                    ->options([
                        '1d' => '1 day',
                        '7d' => '7 days',
                        '30d' => '30 days',
                        '90d' => '90 days',
                        '1y' => '1 year',
                        'permanent' => 'Permanent',
                        'custom' => 'Custom…',
                    ])
                    ->default('30d')
                    ->visible(fn (Get $get): bool => (bool) $get('is_banned'))
                    ->afterStateHydrated(function (ToggleButtons $component, ?User $record): void {
                        $component->state(
                            $record && $record->is_banned
                                ? app(BanUserAction::class)->presetFor($record->banned_until)
                                : '30d'
                        );
                    })
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        if (! $get('is_banned') || $state === null || $state === 'custom') {
                            return;
                        }
                        $set('banned_until', app(BanUserAction::class)->resolveUntil($state, null));
                    }),
                DateTimePicker::make('banned_at')
                    ->label('Banned since')
                    ->helperText('Set automatically when the ban is switched on.'),
                DateTimePicker::make('banned_until')
                    ->label('Ban ends')
                    ->helperText('Leave empty for an indefinite ban.')
                    ->dehydratedWhenHidden()
                    ->visible(fn (Get $get): bool => (bool) $get('is_banned') && $get('ban_duration') === 'custom'),
                Textarea::make('ban_reason')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function profileSection(): Section
    {
        return Section::make('Profile')
            ->description('Optional public profile fields carried over from the forum.')
            ->schema([
                Textarea::make('signature')
                    ->label('Signature (Markdown)')
                    ->helperText('Use Markdown formatting. Legacy signatures are converted when this form opens.')
                    ->rows(4)
                    ->columnSpanFull(),
                Textarea::make('biography')
                    ->rows(5)
                    ->columnSpanFull(),
                TextInput::make('user_title')
                    ->maxLength(255),
                TextInput::make('location')
                    ->maxLength(255),
                TextInput::make('occupation')
                    ->maxLength(255),
                Textarea::make('interests')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    private static function isSelf(?User $record): bool
    {
        $actor = auth()->user();

        return $record instanceof User && $actor instanceof User && $actor->is($record);
    }
}
