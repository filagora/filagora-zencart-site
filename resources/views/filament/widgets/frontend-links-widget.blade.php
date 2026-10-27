<x-filament-widgets::widget>
    <x-filament::section>
        <h2 class="fi-account-widget-heading">View the site</h2>
        <div class="mt-3 flex flex-wrap gap-3">
            <x-filament::button
                tag="a"
                href="{{ route('home') }}"
                target="_blank"
                color="gray"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedHome"
            >Home</x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ route('forums.index') }}"
                target="_blank"
                color="gray"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedChatBubbleLeftRight"
            >Forum</x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ route('plugins.index') }}"
                target="_blank"
                color="gray"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedPuzzlePiece"
            >Plugins</x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
