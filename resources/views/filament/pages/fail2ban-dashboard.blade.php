<x-filament-panels::page>
    <div class="space-y-6">
        @if($statusError)
            <x-filament::section
                :heading="__('filament.pages.fail2ban.status_error_heading')"
                icon="heroicon-o-exclamation-triangle"
                icon-color="danger"
                compact
            >
                <p class="text-sm text-danger-700">{{ $statusError }}</p>
            </x-filament::section>
        @endif

        @if(!($status['enabled'] ?? false))
            <x-filament::section
                :heading="__('filament.pages.fail2ban.disabled_heading')"
                :description="__('filament.pages.fail2ban.disabled_body')"
                icon="heroicon-o-lock-closed"
                icon-color="warning"
            >
                @if($status['note'] ?? null)
                    <p class="text-sm text-gray-600">{{ $status['note'] }}</p>
                @endif
            </x-filament::section>
        @endif

        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
