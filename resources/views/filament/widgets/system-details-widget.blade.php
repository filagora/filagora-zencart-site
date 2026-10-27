<x-filament-widgets::widget>
    <x-filament::section>
        <h2 class="fi-account-widget-heading">Server Software-Version Details</h2>
        <div style="display: flex !important; ">
            <div style="flex:1">
                <div>PHP v{{ PHP_VERSION }}</div>
                <div>Laravel v{{ Illuminate\Foundation\Application::VERSION }}</div>
                <div>Filament {{ \Composer\InstalledVersions::getPrettyVersion('filament/filament') }}</div>
                <div>Livewire {{ \Composer\InstalledVersions::getPrettyVersion('livewire/livewire') }}</div>
            </div>

            <div style="flex:1">
                <div>Scout {{ \Composer\InstalledVersions::getPrettyVersion('laravel/scout') }}</div>
                <div>Queue Monitor {{ \Composer\InstalledVersions::getPrettyVersion('croustibat/filament-jobs-monitor') }}</div>
                <div>Permissions {{ \Composer\InstalledVersions::getPrettyVersion('spatie/laravel-permission') }}</div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
