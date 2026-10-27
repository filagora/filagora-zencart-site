<?php

namespace App\Filament\Resources\NewsletterSubscribers\Schemas;

use App\NewsletterSubscriberStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NewsletterSubscriberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Subscriber')
                    ->schema([
                        TextEntry::make('email')
                            ->copyable(),
                        TextEntry::make('name')
                            ->placeholder('-'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(static fn (NewsletterSubscriberStatus|string|null $state): string => $state instanceof NewsletterSubscriberStatus ? $state->label() : ucfirst((string) $state))
                            ->color(static fn (NewsletterSubscriberStatus|string|null $state): string => $state instanceof NewsletterSubscriberStatus ? $state->color() : 'gray'),
                        TextEntry::make('source')
                            ->badge(),
                        TextEntry::make('user.email')
                            ->label('Linked user')
                            ->placeholder('-'),
                        TextEntry::make('mailchimp_id')
                            ->label('Mailchimp ID')
                            ->placeholder('-')
                            ->copyable(),
                    ])
                    ->columns(3),
                Section::make('Consent and lifecycle')
                    ->schema([
                        TextEntry::make('subscribed_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('confirmed_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('unsubscribed_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('bounced_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('complained_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('unsubscribe_reason')
                            ->placeholder('-'),
                        TextEntry::make('consent_version')
                            ->placeholder('-'),
                        TextEntry::make('consent_ip')
                            ->label('Consent IP')
                            ->placeholder('-'),
                        TextEntry::make('consent_user_agent')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('consent_text')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
                Section::make('Import metadata')
                    ->schema([
                        TextEntry::make('metadata')
                            ->formatStateUsing(static fn (mixed $state): ?string => $state === null ? null : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
