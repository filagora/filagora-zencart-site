<?php

namespace App\Filament\Resources\NewsletterSubscribers\Schemas;

use App\NewsletterSubscriberStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class NewsletterSubscriberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload()
                    ->label('Linked user'),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                TextInput::make('name')
                    ->maxLength(255),
                Select::make('status')
                    ->options(NewsletterSubscriberStatus::options())
                    ->disabled()
                    ->helperText('Use the table actions to subscribe or unsubscribe so consent and suppression timestamps stay consistent.'),
                TextInput::make('source')
                    ->required()
                    ->maxLength(64)
                    ->default('admin'),
                TextInput::make('mailchimp_id')
                    ->label('Mailchimp ID')
                    ->maxLength(255),
                DateTimePicker::make('subscribed_at')
                    ->seconds(false),
                DateTimePicker::make('confirmed_at')
                    ->seconds(false),
                DateTimePicker::make('unsubscribed_at')
                    ->seconds(false),
                DateTimePicker::make('bounced_at')
                    ->seconds(false),
                DateTimePicker::make('complained_at')
                    ->seconds(false),
                TextInput::make('unsubscribe_reason')
                    ->maxLength(255),
                Textarea::make('consent_text')
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('consent_version')
                    ->maxLength(64),
                TextInput::make('consent_ip')
                    ->label('Consent IP')
                    ->maxLength(45),
                Textarea::make('consent_user_agent')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('metadata')
                    ->disabled()
                    ->formatStateUsing(static fn (mixed $state): ?string => $state === null ? null : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                    ->dehydrated(false)
                    ->rows(8)
                    ->columnSpanFull(),
            ]);
    }
}
