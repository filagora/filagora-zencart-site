<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section compact>
            <p class="text-sm text-gray-700 dark:text-gray-200">
                @if($toIndividuals)
                    {{ __('filament.pages.staff_alert_recipients.mode_individuals') }}
                @else
                    {{ __('filament.pages.staff_alert_recipients.mode_team') }}
                @endif
            </p>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('filament.pages.staff_alert_recipients.how_listed') }}</p>
        </x-filament::section>

        @foreach($categories as $category)
            <x-filament::section :heading="$category['label']" :description="$category['access']" compact>
                @if($category['people'] === [])
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ __('filament.pages.staff_alert_recipients.nobody') }}</p>
                @else
                    <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">
                        {{ trans_choice('filament.pages.staff_alert_recipients.told', $category['told'], ['count' => $category['told'], 'total' => count($category['people'])]) }}
                        @if($category['told'] === 0)
                            <span class="text-danger-600 dark:text-danger-400">{{ __('filament.pages.staff_alert_recipients.nobody_told') }}</span>
                        @endif
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-start text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                                    <th class="py-2 pe-4 text-start font-medium">{{ __('filament.pages.staff_alert_recipients.person') }}</th>
                                    <th class="py-2 pe-4 text-start font-medium">{{ __('filament.pages.staff_alert_recipients.email') }}</th>
                                    @foreach($category['channels'] as $channel)
                                        <th class="py-2 pe-4 text-start font-medium">{{ $channel === 'in_app' ? __('filament.pages.staff_alert_recipients.bell') : __('filament.pages.staff_alert_recipients.by_email') }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($category['people'] as $person)
                                    <tr class="border-b border-gray-100 dark:border-white/5">
                                        <td class="py-2 pe-4">{{ $person['name'] }}</td>
                                        <td class="py-2 pe-4 text-gray-500 dark:text-gray-400">{{ $person['email'] }}</td>
                                        @foreach($category['channels'] as $channel)
                                            <td class="py-2 pe-4">
                                                @if($person['wants'][$channel] ?? false)
                                                    <span class="text-success-600 dark:text-success-400">{{ __('filament.pages.staff_alert_recipients.yes') }}</span>
                                                @else
                                                    <span class="text-gray-400">{{ __('filament.pages.staff_alert_recipients.no') }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
