<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Composer\InstalledVersions;
use Filagora\Entitlements\Services\AuthorizationService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class SystemDetailsWidget extends Widget
{
    protected string $view = 'filament.widgets.system-details-widget';

    protected static ?int $sort = -2;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && app(AuthorizationService::class)->allows($user, 'operator.system.settings.manage');
    }

    /**
     * @return array{left: array<string, string>, right: array<string, string>}
     */
    public function packageVersions(): array
    {
        return [
            'left' => [
                'Filament' => $this->versionOf('filament/filament'),
                'Livewire' => $this->versionOf('livewire/livewire'),
            ],
            'right' => [
                'Scout' => $this->versionOf('laravel/scout'),
                'Queue Monitor' => $this->versionOf('croustibat/filament-jobs-monitor'),
                'Permissions' => $this->versionOf('spatie/laravel-permission'),
            ],
        ];
    }

    private function versionOf(string $package): string
    {
        return InstalledVersions::isInstalled($package)
            ? (string) InstalledVersions::getPrettyVersion($package)
            : 'not installed';
    }
}
