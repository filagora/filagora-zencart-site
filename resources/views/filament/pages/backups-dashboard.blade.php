<x-filament-panels::page>
    <div class="space-y-6">
        @if($showLastBackupFailure)
            <x-filament::section
                :heading="__('filament.pages.backups.last_failure_heading')"
                icon="heroicon-o-exclamation-triangle"
                icon-color="danger"
                compact
            >
                <div class="space-y-2">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ __('filament.pages.backups.last_failure_body', ['time' => $lastBackupFailureFinishedAt]) }}
                    </p>
                    @if($lastBackupFailureMessage)
                        <pre class="max-h-48 overflow-y-auto whitespace-pre-wrap break-words rounded-md bg-gray-950 p-3 text-xs text-gray-100">{{ $lastBackupFailureMessage }}</pre>
                    @endif
                    <x-filament::button color="gray" size="sm" wire:click="dismissLastBackupFailure">
                        {{ __('filament.pages.backups.last_failure_dismiss') }}
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

        @if($statusError)
            <x-filament::section
                :heading="__('filament.pages.backups.status_error_heading')"
                icon="heroicon-o-exclamation-triangle"
                icon-color="danger"
                compact
            >
                <p class="text-sm text-danger-700">{{ $statusError }}</p>
            </x-filament::section>
        @endif

        @if(!$configured)
            <x-filament::section
                :heading="__('filament.pages.backups.not_configured_heading')"
                :description="__('filament.pages.backups.not_configured_body')"
                icon="heroicon-o-lock-closed"
                icon-color="warning"
            />
        @endif

        @php($settings = $this->backupSettings())
        <x-filament::section
            :heading="__('filament.pages.backups.settings_heading')"
            :description="__('filament.pages.backups.settings_body')"
            collapsible
            collapsed
        >
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.04em] text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_storage') }}</p>
                    <dl class="mt-2 space-y-1 text-sm">
                        <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_disk') }}</dt><dd class="font-mono text-gray-900 dark:text-white">{{ $settings['disk'] }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_bucket') }}</dt><dd class="font-mono text-gray-900 dark:text-white">{{ $settings['bucket'] ?: __('filament.pages.backups.settings_not_set') }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_region') }}</dt><dd class="font-mono text-gray-900 dark:text-white">{{ $settings['region'] ?: __('filament.pages.backups.settings_not_set') }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_backup_name') }}</dt><dd class="font-mono text-gray-900 dark:text-white">{{ $settings['backup_name'] }}</dd></div>
                    </dl>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.04em] text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_schedule') }}</p>
                    <dl class="mt-2 space-y-1 text-sm">
                        <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_schedule_enabled') }}</dt><dd class="text-gray-900 dark:text-white">{{ $settings['schedule_enabled'] ? __('filament.pages.backups.settings_yes') : __('filament.pages.backups.settings_no') }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_daily_at') }}</dt><dd class="font-mono text-gray-900 dark:text-white">{{ $settings['daily_at'] }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_clean_at') }}</dt><dd class="font-mono text-gray-900 dark:text-white">{{ $settings['clean_at'] }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_alert_emails') }}</dt><dd class="text-right text-gray-900 dark:text-white">{{ count($settings['alert_emails']) > 0 ? implode(', ', $settings['alert_emails']) : __('filament.pages.backups.settings_not_set') }}</dd></div>
                    </dl>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.04em] text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_retention') }}</p>
                    <dl class="mt-2 space-y-1 text-sm">
                        <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_keep_all') }}</dt><dd class="text-gray-900 dark:text-white">{{ $settings['retention']['keep_all_days'] }} {{ __('filament.pages.backups.settings_days') }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_keep_daily') }}</dt><dd class="text-gray-900 dark:text-white">{{ $settings['retention']['keep_daily_days'] }} {{ __('filament.pages.backups.settings_days') }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_keep_weekly') }}</dt><dd class="text-gray-900 dark:text-white">{{ $settings['retention']['keep_weekly_weeks'] }} {{ __('filament.pages.backups.settings_weeks') }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_keep_monthly') }}</dt><dd class="text-gray-900 dark:text-white">{{ $settings['retention']['keep_monthly_months'] }} {{ __('filament.pages.backups.settings_months') }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_keep_yearly') }}</dt><dd class="text-gray-900 dark:text-white">{{ $settings['retention']['keep_yearly_years'] }} {{ __('filament.pages.backups.settings_years') }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-gray-500 dark:text-gray-400">{{ __('filament.pages.backups.settings_max_storage') }}</dt><dd class="text-gray-900 dark:text-white">{{ $settings['retention']['max_storage_mb'] ? number_format($settings['retention']['max_storage_mb']).' MB' : __('filament.pages.backups.settings_unlimited') }}</dd></div>
                    </dl>
                </div>
            </div>
        </x-filament::section>

        @if($configured)
            <x-filament::section
                :heading="__('filament.pages.backups.list_heading')"
                :description="__('filament.pages.backups.list_body')"
            >
                @if(count($backups) === 0)
                    <p class="text-sm text-gray-600">{{ __('filament.pages.backups.no_backups') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-white/10">
                                    <th class="py-2 pr-4 font-medium text-gray-600 dark:text-gray-300">{{ __('filament.pages.backups.filename_label') }}</th>
                                    <th class="py-2 pr-4 font-medium text-gray-600 dark:text-gray-300">{{ __('filament.pages.backups.size_label') }}</th>
                                    <th class="py-2 font-medium text-gray-600 dark:text-gray-300">{{ __('filament.pages.backups.last_modified_label') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($backups as $backup)
                                    <tr class="border-b border-gray-100 dark:border-white/5">
                                        <td class="py-2 pr-4 font-mono text-xs text-gray-900 dark:text-white">{{ $backup['filename'] }}</td>
                                        <td class="py-2 pr-4 text-gray-600 dark:text-gray-300">{{ number_format($backup['size'] / 1024 / 1024, 2) }} MB</td>
                                        <td class="py-2 text-gray-600 dark:text-gray-300">{{ \App\Support\LocalTime::format(\Illuminate\Support\Carbon::createFromTimestamp($backup['last_modified'])) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif

        <x-filament::modal
            id="backup-progress"
            :heading="__('filament.pages.backups.progress_heading')"
            width="2xl"
            :close-by-clicking-away="false"
        >
            <div wire:poll.3s.visible="pollBackupProgress" class="space-y-3">
                <p class="text-sm font-semibold">
                    @if($backupProgressStatus === 'running')
                        <span class="text-warning-600">{{ __('filament.pages.backups.progress_running') }}</span>
                    @elseif($backupProgressStatus === 'success')
                        <span class="text-success-600">{{ __('filament.pages.backups.progress_success') }}</span>
                    @elseif($backupProgressStatus === 'failed')
                        <span class="text-danger-600">{{ __('filament.pages.backups.progress_failed') }}</span>
                    @else
                        {{ __('filament.pages.backups.progress_idle') }}
                    @endif
                </p>

                @if($backupProgressStatus === 'running' && $backupUploadedBytes > 0)
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ __('filament.pages.backups.progress_uploaded', ['amount' => \App\Jobs\RunDatabaseBackupJob::formatBytes($backupUploadedBytes)]) }}
                    </p>
                @endif

                <pre class="max-h-96 overflow-y-auto whitespace-pre-wrap break-words rounded-md bg-gray-950 p-3 text-xs text-gray-100">{{ $backupProgressLog !== '' ? $backupProgressLog : __('filament.pages.backups.progress_waiting') }}</pre>
            </div>

            <x-slot name="footer">
                <x-filament::button color="gray" wire:click="closeProgressModal" x-on:click="$dispatch('close-modal', { id: 'backup-progress' })">
                    {{ __('filament.pages.backups.progress_close') }}
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    </div>
</x-filament-panels::page>
