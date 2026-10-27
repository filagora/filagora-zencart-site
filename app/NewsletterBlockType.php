<?php

namespace App;

enum NewsletterBlockType: string
{
    case Header = 'header';
    case Footer = 'footer';
    case Text = 'text';
    case Image = 'image';
    case Button = 'button';

    public function label(): string
    {
        return match ($this) {
            self::Header => 'Header',
            self::Footer => 'Footer',
            self::Text => 'Text section',
            self::Image => 'Image',
            self::Button => 'Button',
        };
    }

    public function isSystemManaged(): bool
    {
        return in_array($this, [self::Header, self::Footer], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function selectableOptions(): array
    {
        return collect(self::cases())
            ->reject(static fn (self $type): bool => $type->isSystemManaged())
            ->mapWithKeys(static fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
