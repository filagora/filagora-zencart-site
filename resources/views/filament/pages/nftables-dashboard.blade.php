<x-filament-panels::page>
    <div class="space-y-6">
        @if($statusError)
            <x-filament::section
                :heading="__('filament.pages.nftables.status_error_heading')"
                icon="heroicon-o-exclamation-triangle"
                icon-color="danger"
                compact
            >
                <p class="text-sm text-danger-700">{{ $statusError }}</p>
            </x-filament::section>
        @endif

        @if(!($status['enabled'] ?? false))
            <x-filament::section
                :heading="__('filament.pages.nftables.disabled_heading')"
                :description="__('filament.pages.nftables.disabled_body')"
                icon="heroicon-o-lock-closed"
                icon-color="warning"
            >
                @if($status['note'] ?? null)
                    <p class="text-sm text-gray-600">{{ $status['note'] }}</p>
                @endif
            </x-filament::section>
        @endif

        @if(($status['enabled'] ?? false) && (($status['tables'] ?? []) !== [] || ($status['sets'] ?? []) !== []))
            <x-filament::section
                :heading="__('filament.pages.nftables.quick_filters_heading')"
                :description="__('filament.pages.nftables.quick_filters_body')"
                compact
            >
                <div class="space-y-4">
                    @if(($status['tables'] ?? []) !== [])
                        <div class="space-y-2">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ __('filament.pages.nftables.quick_filter_tables_label') }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                @foreach(($status['tables'] ?? []) as $tableIdentifier)
                                    <x-filament::button
                                        size="sm"
                                        :color="$activeTableFilter === $tableIdentifier ? 'primary' : 'gray'"
                                        wire:click="applyTableFilter('{{ $tableIdentifier }}')"
                                        wire:key="nftables-table-filter-{{ md5($tableIdentifier) }}"
                                    >
                                        {{ $tableIdentifier }}
                                    </x-filament::button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(($status['sets'] ?? []) !== [])
                        <div class="space-y-2">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ __('filament.pages.nftables.quick_filter_sets_label') }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                @foreach(($status['sets'] ?? []) as $set)
                                    <x-filament::button
                                        size="sm"
                                        :color="$activeSetFilter === ($set['key'] ?? null) ? 'primary' : 'gray'"
                                        wire:click="applySetFilter('{{ $set['key'] }}')"
                                        wire:key="nftables-set-filter-{{ md5((string) $set['key']) }}"
                                    >
                                        {{ $set['family'] }}/{{ $set['table'] }}/{{ $set['name'] }}
                                    </x-filament::button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($activeTableFilter || $activeSetFilter)
                        <div>
                            <x-filament::button
                                size="sm"
                                color="gray"
                                wire:click="clearQuickFilters"
                            >
                                {{ __('filament.pages.nftables.clear_filters_action') }}
                            </x-filament::button>
                        </div>
                    @endif
                </div>
            </x-filament::section>
        @endif

        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
