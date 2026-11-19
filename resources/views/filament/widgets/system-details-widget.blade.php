<x-filament-widgets::widget>
    <x-filament::section>
        <h2 class="fi-account-widget-heading">Server Software-Version Details</h2>
        <div style="display: flex !important; ">
            <div style="flex:1">
                <div>PHP v{{ PHP_VERSION }}</div>
                <div>Laravel v{{ Illuminate\Foundation\Application::VERSION }}</div>
                @foreach ($this->packageVersions()['left'] as $name => $version)
                    <div>{{ $name }} {{ $version }}</div>
                @endforeach
            </div>

            <div style="flex:1">
                @foreach ($this->packageVersions()['right'] as $name => $version)
                    <div>{{ $name }} {{ $version }}</div>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
