@extends('layouts.main')

@section('title')
{{ $plugin->name }} - Plugin Library
@endsection

@section('main_class', 'bg-white')
@section('meta_description', $metaDescription ?? '')
@section('canonical', $canonicalUrl ?? '')

@section('content')
    @php
        $pluginActionButtonClass = 'inline-flex items-center rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $pluginPrimaryBtnClass = 'inline-flex items-center rounded-control border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $pluginSecondaryBtnClass = $pluginActionButtonClass;
        $pluginStatusClass = 'inline-flex items-center rounded-pill border border-[#933c1f] bg-[#f7e8df] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.03em] text-[#933c1f]';
        $pluginAuthor = $plugin->owner?->username
            ?: ($plugin->owner?->name
                ?: ($plugin->owner?->email ?: 'Unknown'));
        $isEncapsulatedPlugin = $activeReleases->contains(
            fn ($pluginRelease) => $pluginRelease->encapsulated === \Filagora\ZenCart\Enums\Encapsulated::YES
        );
        $canonicalPluginId = (int) ($plugin->legacy_vbulletin_file_id ?? $plugin->getKey());
    @endphp
    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
    @include('plugins.partials.unverified_banner')
    @include('plugins.partials.submissions_paused_banner')

    @if(session('plugin_success'))
        <div class="mb-3 rounded-surface border border-[#b9d6bd] bg-[#f1fbf2] px-3 py-2 text-sm text-[#1f5130]" role="status" aria-live="polite">{{ session('plugin_success') }}</div>
    @endif
        @if(session('plugin_error'))
        <div class="mb-3 rounded-surface border border-[#efc2c6] bg-[#fff2f3] px-3 py-2 text-sm text-[#a63a45]" role="alert" aria-live="assertive">{{ session('plugin_error') }}</div>
    @endif
    <div class="ui-small-meta mb-3">
        <a class="text-[#8f3f1f] hover:underline" href="{{ route('plugins.index') }}">Plugin Library</a>
        / <a class="text-[#8f3f1f] hover:underline" href="{{ route('plugins.type', ['slug' => $plugin->type->slug]) }}">{{ $plugin->type->name }}</a>
        / {{ $plugin->name }}
    </div>

    <header class="mb-4 border border-[#d8dce2] bg-white p-4">
        <div class="flex flex-col items-start justify-between gap-3 lg:flex-row lg:items-center">
            <div>
                <h1 class="ui-page-title">{{ $plugin->name }}</h1>
                <p class="ui-page-subtitle mt-2">{{ $plugin->type->name }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-pill border border-[#d8dce2] bg-[#f6f4ef] px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-[0.03em] text-[#5c6570]">
                        Plugin ID
                        <span class="text-[12px] text-[#0f2133]">{{ $canonicalPluginId }}</span>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-control border border-[#d8dce2] bg-white px-1.5 py-[1px] text-[10px] font-semibold normal-case tracking-normal text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                            data-copy-plugin-id
                            data-copy-value="{{ $canonicalPluginId }}"
                            aria-label="Copy plugin ID {{ $canonicalPluginId }}"
                        >Copy</button>
                    </span>
                    @if($canModerate)
                        <span class="{{ $pluginStatusClass }}">{{ $plugin->status?->label() ?? ucfirst((string) $plugin->status) }}</span>
                    @endif
                    <span class="text-xs text-[#5c6570]" role="status" aria-live="polite" data-copy-plugin-id-status></span>
                </div>
                @if($isEncapsulatedPlugin)
                    <p class="mt-1.5 text-xs text-[#5c6570]">The Plugin ID is written into your <code>manifest.php</code> <code>pluginId</code> automatically the first time a release is published.</p>
                @endif
            </div>

            <div class="inline-flex flex-wrap items-center gap-2">
                @if($latestActiveRelease)
                    <a class="{{ $pluginPrimaryBtnClass }}" href="{{ route('plugins.download', $plugin) }}">
                        @if($canModerate && $plugin->status !== \Filagora\Plugins\Enums\PluginStatus::ACTIVE)
                            Download latest for review
                        @else
                            {{ ($canDownloadPlugin ?? false) ? 'Download latest release' : 'Unlock download' }}
                        @endif
                    </a>
                @endif
                @if($plugin->github_repository_url)
                    <a class="{{ $pluginSecondaryBtnClass }}" href="{{ $plugin->github_repository_url }}" target="_blank" rel="noopener noreferrer">Repository</a>
                @endif
                @if($plugin->forumThread)
                    <a class="{{ $pluginSecondaryBtnClass }}" href="{{ route('threads.show', $plugin->forumThread) }}">Plugin support forum</a>
                @endif
                @if(!auth()->check() || auth()->user()?->hasVerifiedEmail())
                    <form method="post" action="{{ route($isSubscribed ? 'plugins.unsubscribe' : 'plugins.subscribe', $plugin) }}">
                        @csrf
                        <button type="submit" class="{{ $pluginSecondaryBtnClass }}">
                            {{ $isSubscribed ? 'Unsubscribe from updates' : 'Subscribe to updates' }}
                        </button>
                    </form>
                @endif
                @if(config('plugins.submissions_enabled', true) && $canSubmitRelease && auth()->user()?->hasVerifiedEmail())
                    <a class="{{ $pluginSecondaryBtnClass }}" href="{{ route('plugins.release.new', $plugin) }}">Add new release</a>
                @endif
                @if($canModerate)
                    <a class="{{ $pluginSecondaryBtnClass }}" href="{{ \Filagora\Plugins\Filament\Resources\Plugins\PluginResource::getUrl('edit', ['record' => $plugin]) }}">Edit Plugin</a>
                @endif
                @if(auth()->check() && auth()->user()?->hasVerifiedEmail() && auth()->id() !== $plugin->owner_id)
                    <button
                        type="button"
                        class="{{ $pluginSecondaryBtnClass }}"
                        data-report-open
                    >Report plugin</button>
                @endif
            </div>
        </div>
    </header>

    @if($plugin->description)
        <section class="mb-4 border border-[#d8dce2] bg-white p-4">
            <h2 class="ui-content-title">Overview</h2>
            <div data-description-expander>
                <p class="ui-body-copy mt-2 whitespace-pre-line" data-description-body>{{ $plugin->description }}</p>
                <button
                    type="button"
                    class="mt-2 text-sm font-semibold text-[#8f3f1f] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                    data-description-toggle
                    aria-expanded="false"
                    hidden
                >Show more</button>
            </div>
        </section>
    @endif

        @if(filled($downloadEntitlementScope ?? null) && !($canDownloadPlugin ?? false))
            <div class="border border-[#d8dce2] bg-[#f8f6f1] px-4 py-3 text-sm text-[#5c6570]">
                Downloading this plugin requires the <strong>{{ $downloadEntitlementScope }}</strong> entitlement.
                <a href="{{ route('billing.index') }}" class="font-semibold text-[#8f3f1f] hover:underline">Manage memberships</a>
            </div>
        @endif

        @if($canModerate)
            <section class="mb-4 border border-[#d8dce2] bg-white p-4">
                <h2 class="ui-content-title">Moderation Controls</h2>
                <ul class="mt-3 grid gap-3">
                    <li class="border border-[#d8dce2] bg-white p-3">
                        @php
                            $pluginStatusClass = [
                                'active' => 'border-[#b9d6bd] bg-[#f1fbf2] text-[#1f5130]',
                                'moderated' => 'border-[#bfd6eb] bg-[#edf4fb] text-[#21435f]',
                                'rejected' => 'border-[#efc2c6] bg-[#fff2f3] text-[#a63a45]',
                                'rejected_final' => 'border-[#e2c6cc] bg-[#f8edf0] text-[#7b2735]',
                            ][$plugin->status->value] ?? 'border-[#cabca8] bg-white text-[#3a4a5b]';
                        @endphp
                        <div class="mb-2 grid gap-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <strong>Plugin: {{ $plugin->name }}</strong>
                                <span class="inline-flex items-center rounded-pill border px-2.5 py-1 text-xs font-semibold {{ $pluginStatusClass }}">
                                    {{ $plugin->status->label() }}
                                </span>
                            </div>
                        </div>
                        <form method="post" action="{{ route('plugins.moderate', $plugin) }}" class="grid gap-2">
                            @csrf
                            <label for="plugin-show-moderation-note-{{ $plugin->id }}" class="sr-only">Moderation note (required for reject actions)</label>
                            <p id="plugin-show-moderation-note-help-{{ $plugin->id }}" class="sr-only">Note is required when rejecting.</p>
                            <textarea
                                id="plugin-show-moderation-note-{{ $plugin->id }}"
                                name="note"
                                rows="2"
                                class="w-full rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] placeholder:text-[#5c6570] focus:border-[#8f3f1f] focus:outline-none focus:ring-2 focus:ring-[#8f3f1f]/25"
                                placeholder="Required for reject"
                                aria-describedby="plugin-show-moderation-note-help-{{ $plugin->id }}{{ $errors->has('note') ? ' plugin-show-moderation-note-error-'.$plugin->id : '' }}"
                                @if($errors->has('note')) aria-invalid="true" @endif
                            ></textarea>
                            @if($errors->has('note'))
                                <p id="plugin-show-moderation-note-error-{{ $plugin->id }}" class="text-sm text-[#dc3545]">{{ $errors->first('note') }}</p>
                            @endif
                            <div class="flex flex-wrap gap-2">
                                @if($plugin->status !== \Filagora\Plugins\Enums\PluginStatus::ACTIVE)
                                    <button type="submit" name="action" value="approve" class="{{ $pluginSecondaryBtnClass }}">Approve Plugin</button>
                                @endif
                                <button type="submit" name="action" value="reject_permanently" class="{{ $pluginPrimaryBtnClass }}">Reject</button>
                            </div>
                        </form>
                    </li>
                </ul>
            </section>
        @endif

        <section class="border border-[#d8dce2] bg-white p-4">
            <h2 class="ui-content-title">Releases</h2>
            <ul class="mt-3 flex flex-col gap-3">
                @forelse($activeReleases as $release)
                    @php
                        $compatibleVersions = $release->zcVersions
                            ->pluck('version')
                            ->filter(static fn ($version) => is_string($version) && trim($version) !== '')
                            ->values();
                        $releaseAuthorUser = $release->uploadedBy ?? $plugin->owner;
                        $releaseAuthor = $release->uploadedBy?->username
                            ?: ($release->uploadedBy?->name
                                ?: ($release->uploadedBy?->email
                                    ?: ($plugin->owner?->username
                                        ?: ($plugin->owner?->name
                                            ?: ($plugin->owner?->email ?: 'Unknown author')))));
                        $releaseMetadata = collect([
                            number_format((int) ($release->download_count ?? 0)).' downloads',
                            ($release->zip_size ?? 0) > 0
                                ? number_format(((int) $release->zip_size) / 1024, 1).' KB ZIP'
                                : null,
                        ])->filter();
                        $encapsulationLabel = match ($release->encapsulated) {
                            \Filagora\ZenCart\Enums\Encapsulated::YES => 'Encapsulated',
                            \Filagora\ZenCart\Enums\Encapsulated::NO => 'Not Encapsulated',
                            default => 'Encapsulation unknown',
                        };
                        $encapsulationBadgeClass = match ($release->encapsulated) {
                            \Filagora\ZenCart\Enums\Encapsulated::YES => 'border-[#b9d6bd] bg-[#f1fbf2] text-[#1f5130]',
                            \Filagora\ZenCart\Enums\Encapsulated::NO => 'border-[#efc2c6] bg-[#fff2f3] text-[#a63a45]',
                            default => 'border-[#cabca8] bg-[#f5f2eb] text-[#5a6775]',
                        };
                        $releaseStatusBadgeClass = [
                            'success' => 'border-[#b9d6bd] bg-[#f1fbf2] text-[#1f5130]',
                            'warning' => 'border-[#e6cfa5] bg-[#fbf4e6] text-[#7a5a12]',
                            'danger' => 'border-[#efc2c6] bg-[#fff2f3] text-[#a63a45]',
                            'gray' => 'border-[#cabca8] bg-[#f5f2eb] text-[#5a6775]',
                        ];
                        $releaseStatusColor = $releaseStatusBadgeClass[$release->status?->color() ?? 'gray'] ?? $releaseStatusBadgeClass['gray'];
                        $releaseIsRejectedFinal = $release->status === \Filagora\Plugins\Enums\PluginStatus::REJECTED_FINAL;
                    @endphp
                    <li class="flex items-start justify-between gap-4 border border-[#d8dce2] bg-white p-3">
                        <div class="min-w-0 flex-1 flex flex-col gap-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <strong class="text-[#0f2133]">{{ $release->version }}</strong>
                                <span class="inline-flex items-center rounded-pill border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.03em] {{ $encapsulationBadgeClass }}">
                                    {{ $encapsulationLabel }}
                                </span>
                                @if($canModerate)
                                    <span class="inline-flex items-center rounded-pill border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.03em] {{ $releaseStatusColor }}">
                                        {{ $release->status?->label() ?? ucfirst((string) $release->status) }}
                                    </span>
                                @endif
                                <span class="inline-flex items-center gap-1 text-xs text-[#5c6570]">
                                    Release ID
                                    <span class="font-semibold text-[#0f2133]">#{{ $release->id }}</span>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-control border border-[#d8dce2] bg-white px-1.5 py-0.5 text-[11px] font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                                        data-copy-plugin-id
                                        data-copy-value="{{ $release->id }}"
                                        aria-label="Copy release ID {{ $release->id }}"
                                    >Copy</button>
                                </span>
                            </div>
                            <small class="text-[#5c6570]">
                                {{ \App\Support\LocalTime::format($release->created_at, 'date') }}
                                @if($compatibleVersions->isNotEmpty())
                                    · Compatible with {{ $compatibleVersions->join(', ') }}
                                @endif
                            </small>
                            <small class="text-[#5c6570]">
                                @if($releaseAuthorUser?->id)
                                    Release by <a class="text-[#8f3f1f] hover:underline" href="{{ route('members.show', ['user' => $releaseAuthorUser->id]) }}">{{ $releaseAuthor }}</a>
                                    @if($releaseMetadata->isNotEmpty())
                                        · {{ $releaseMetadata->join(' · ') }}
                                    @endif
                                    @if($viewerIsStaff && filled($releaseAuthorUser->email))
                                        <a
                                            href="mailto:{{ $releaseAuthorUser->email }}"
                                            class="ml-1 inline-flex items-center rounded-pill border border-[#cabca8] bg-white px-2 py-0.5 text-[11px] font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f]"
                                        >Email</a>
                                    @endif
                                @else
                                    Release by {{ $releaseAuthor }}
                                    @if($releaseMetadata->isNotEmpty())
                                        · {{ $releaseMetadata->join(' · ') }}
                                    @endif
                                @endif
                            </small>
                            @if(filled($release->changelog))
                                <div data-description-expander>
                                    <p class="ui-body-copy whitespace-pre-line" data-description-body>{{ $release->changelog }}</p>
                                    <button
                                        type="button"
                                        class="mt-2 text-sm font-semibold text-[#8f3f1f] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                                        data-description-toggle
                                        aria-expanded="false"
                                        hidden
                                    >Show more</button>
                                </div>
                            @endif
                            @if($release->status === \Filagora\Plugins\Enums\PluginStatus::ARCHIVED)
                                @php
                                    $archivedScan = $release->latestSecurityScan;
                                    $archivedScanned = $archivedScan && $archivedScan->status === \Filagora\PluginsSecurity\Enums\ScanStatus::COMPLETED;
                                @endphp
                                <div class="mt-1 rounded-control border border-[#e0d4bf] bg-[#faf6ee] p-3 text-sm text-[#6f6350]">
                                    <strong>Historical release — not currently security-scanned.</strong>
                                    @if($archivedScanned)
                                        Last scanned {{ \App\Support\LocalTime::format($archivedScan->finished_at ?? $release->archived_at, 'date') }}: {{ $archivedScan->severity()->label() }}.
                                    @else
                                        Never security-scanned.
                                    @endif
                                    This build is not re-checked as the plugin evolves — use at your own risk.
                                </div>
                            @endif
                            @if($canModerate && $release->isQuarantined())
                                <div class="mt-1 rounded-control border border-[#d99a9a] bg-[#fdeeee] p-3 text-sm text-[#732f2f]">
                                    <strong>Quarantined by security scan.</strong>
                                    Hidden from the public until cleared.
                                    @if($release->quarantineScan)
                                        <br><span>Scan #{{ $release->quarantineScan->id }} &middot; {{ $release->quarantineScan->severity()->label() }} &middot; scanner v{{ $release->quarantineScan->scanner_version }}</span>
                                    @endif
                                    @if(filled($release->quarantine_note))
                                        <br><span>{{ $release->quarantine_note }}</span>
                                    @endif
                                </div>
                            @endif
                            @if($canModerate)
                                @php
                                    $scan = $release->latestSecurityScan;
                                    $scanBadgeClass = [
                                        'success' => 'border-[#b9d6bd] bg-[#f1fbf2] text-[#1f5130]',
                                        'warning' => 'border-[#e6cfa5] bg-[#fbf4e6] text-[#7a5a12]',
                                        'danger' => 'border-[#efc2c6] bg-[#fff2f3] text-[#a63a45]',
                                        'gray' => 'border-[#cabca8] bg-[#f5f2eb] text-[#5a6775]',
                                    ];
                                    $scanStatusColor = $scanBadgeClass[$scan?->status?->color() ?? 'gray'] ?? $scanBadgeClass['gray'];
                                    $scanSeverityColor = $scanBadgeClass[$scan?->severity()->color() ?? 'gray'] ?? $scanBadgeClass['gray'];
                                    $scanFindings = collect($scan?->findings ?? []);
                                    $scanAnalyzers = collect($scan?->analyzer_versions ?? []);
                                @endphp
                                <details class="mt-1 rounded-control border border-[#d8dce2] bg-[#f7f9fb] p-3 text-sm text-[#304458]">
                                    <summary class="flex cursor-pointer flex-wrap items-center gap-2 font-semibold text-[#0f2133]">
                                        <span>Security scan</span>
                                        @if($scan)
                                            <span class="inline-flex items-center rounded-pill border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.03em] {{ $scanStatusColor }}">{{ $scan->status?->label() ?? 'Unknown' }}</span>
                                            <span class="inline-flex items-center rounded-pill border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.03em] {{ $scanSeverityColor }}">{{ $scan->severity()->label() }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-pill border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.03em] {{ $scanBadgeClass['gray'] }}">Not scanned</span>
                                        @endif
                                    </summary>
                                    @if($scan)
                                        <dl class="mt-3 grid gap-x-4 gap-y-1.5 sm:grid-cols-2">
                                            <div><dt class="inline font-semibold">Scanner version:</dt> <dd class="inline">v{{ $scan->scanner_version }}</dd></div>
                                            <div><dt class="inline font-semibold">Trigger:</dt> <dd class="inline">{{ $scan->triggered_by?->label() ?? '-' }}</dd></div>
                                            <div><dt class="inline font-semibold">Triggered by:</dt> <dd class="inline">{{ $scan->triggeredByUser?->email ?? 'system' }}</dd></div>
                                            <div><dt class="inline font-semibold">Queued:</dt> <dd class="inline">{{ $scan->queued_at ? \App\Support\LocalTime::format($scan->queued_at, 'datetime') : '-' }}</dd></div>
                                            <div><dt class="inline font-semibold">Started:</dt> <dd class="inline">{{ $scan->started_at ? \App\Support\LocalTime::format($scan->started_at, 'datetime') : '-' }}</dd></div>
                                            <div><dt class="inline font-semibold">Finished:</dt> <dd class="inline">{{ $scan->finished_at ? \App\Support\LocalTime::format($scan->finished_at, 'datetime') : '-' }}</dd></div>
                                        </dl>
                                        @if(filled($scan->error))
                                            <p class="mt-2 rounded-control border border-[#efc2c6] bg-[#fff2f3] p-2 text-[#a63a45]">{{ $scan->error }}</p>
                                        @endif
                                        <div class="mt-3">
                                            <p class="font-semibold text-[#0f2133]">Analyzers</p>
                                            @if($scanAnalyzers->isNotEmpty())
                                                <ul class="mt-1 list-disc pl-5">
                                                    @foreach($scanAnalyzers as $analyzerName => $analyzerMeta)
                                                        <li>{{ $analyzerName }}: v{{ $analyzerMeta['version'] ?? '?' }} ({{ $analyzerMeta['status'] ?? 'completed' }})@if(isset($analyzerMeta['note'])) &mdash; {{ $analyzerMeta['note'] }}@endif</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <p class="mt-1 text-[#5c6570]">None recorded.</p>
                                            @endif
                                        </div>
                                        <div class="mt-3">
                                            <p class="font-semibold text-[#0f2133]">Findings</p>
                                            @if($scanFindings->isNotEmpty())
                                                <ul class="mt-1 grid gap-1">
                                                    @foreach($scanFindings as $finding)
                                                        <li class="rounded-control border border-[#d8dce2] bg-white p-2">
                                                            <span class="font-semibold uppercase">[{{ $finding['severity'] ?? '?' }}]</span>
                                                            {{ $finding['analyzer'] ?? '?' }} &mdash; {{ $finding['title'] ?? $finding['rule_id'] ?? 'finding' }}
                                                            @if(isset($finding['file']))
                                                                <span class="text-[#5c6570]">in {{ $finding['file'] }}@if(isset($finding['line'])):{{ $finding['line'] }}@endif</span>
                                                            @endif
                                                            <span class="text-[#5c6570]">({{ $finding['rule_id'] ?? '?' }})</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <p class="mt-1 text-[#5c6570]">No findings.</p>
                                            @endif
                                        </div>
                                    @else
                                        <p class="mt-3 text-[#5c6570]">This release has no recorded security scan.</p>
                                    @endif
                                    <a class="mt-3 inline-flex items-center text-sm font-semibold text-[#8f3f1f] hover:underline" href="{{ \Filagora\Plugins\Filament\Resources\PluginReleases\PluginReleaseResource::getUrl('edit', ['record' => $release]) }}">Full scan history in dashboard</a>
                                </details>
                            @endif
                            @if(($canEditReleaseZcVersions ?? false) && (int) $release->id === (int) ($editableZcVersionsReleaseId ?? 0))
                                @php
                                    $selectedZcVersionIds = $release->zcVersions->pluck('id')->map(static fn ($id) => (int) $id)->all();
                                @endphp
                                <details class="mt-1 border border-[#d8dce2] bg-[#f7f9fb] p-3">
                                    <summary class="cursor-pointer text-sm font-semibold text-[#0f2133]">Edit compatible Zen Cart versions</summary>
                                    <form method="post" action="{{ route('plugins.release.zc-versions.update', ['plugin' => $plugin, 'release' => $release]) }}" class="mt-2 grid gap-2">
                                        @csrf
                                        <p class="text-xs text-[#5c6570]">Updates the compatibility shown for this release straight away, without a new upload. Recorded in the moderation audit log.</p>
                                        <fieldset class="grid gap-1">
                                            <legend class="sr-only">Compatible Zen Cart versions</legend>
                                            <div class="grid gap-1 sm:grid-cols-2">
                                                @foreach($zcVersionOptions as $zcVersion)
                                                    <label class="flex items-center gap-2 text-sm text-[#0f2133]">
                                                        <input type="checkbox" name="zc_versions[]" value="{{ $zcVersion->id }}" @checked(in_array((int) $zcVersion->id, $selectedZcVersionIds, true))>
                                                        {{ $zcVersion->version }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </fieldset>
                                        <div>
                                            <button type="submit" class="{{ $pluginSecondaryBtnClass }}">Update compatibility</button>
                                        </div>
                                    </form>
                                </details>
                            @endif
                            @if($canModerate && ! $releaseIsRejectedFinal)
                                <form method="post" action="{{ route('plugins.release.moderate', ['plugin' => $plugin, 'release' => $release]) }}" class="grid gap-2">
                                    @csrf
                                    @php
                                        $showReleaseNoteHelpId = 'plugin-show-release-note-help-'.$release->id;
                                        $showReleaseNoteErrorId = 'plugin-show-release-note-error-'.$release->id;
                                    @endphp
                                    <label for="plugin-show-release-note-{{ $release->id }}" class="sr-only">Moderation note (required for reject actions)</label>
                                    <p id="{{ $showReleaseNoteHelpId }}" class="sr-only">Note is required when rejecting.</p>
                                    <textarea
                                        id="plugin-show-release-note-{{ $release->id }}"
                                        name="note"
                                        rows="2"
                                        class="w-full rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] placeholder:text-[#5c6570] focus:border-[#8f3f1f] focus:outline-none focus:ring-2 focus:ring-[#8f3f1f]/25"
                                        placeholder="Required for reject"
                                        aria-describedby="{{ $showReleaseNoteHelpId }}{{ $errors->has('note') ? ' '.$showReleaseNoteErrorId : '' }}"
                                        @if($errors->has('note')) aria-invalid="true" @endif
                                    ></textarea>
                                    @if($errors->has('note'))
                                        <p id="{{ $showReleaseNoteErrorId }}" class="text-sm text-[#dc3545]">{{ $errors->first('note') }}</p>
                                    @endif
                                    <div class="flex flex-wrap gap-2 pt-1">
                                        @if($release->status !== \Filagora\Plugins\Enums\PluginStatus::ACTIVE)
                                            <button type="submit" name="action" value="approve" class="{{ $pluginSecondaryBtnClass }}">Approve Release</button>
                                        @endif
                                        <button type="submit" name="action" value="reject_permanently" class="{{ $pluginPrimaryBtnClass }}">Reject</button>
                                    </div>
                                </form>
                            @elseif($canModerate)
                                <p class="text-sm text-[#5c6570]">Rejected. Use the dashboard to change this release's status.</p>
                            @endif
                        </div>
                        <div class="shrink-0 self-start grid gap-2">
                            @php
                                $releaseIsArchived = $release->status === \Filagora\Plugins\Enums\PluginStatus::ARCHIVED;
                                $releasePubliclyDownloadable = $release->status === \Filagora\Plugins\Enums\PluginStatus::ACTIVE
                                    || ($releaseIsArchived && (bool) config('plugin-ops.archiving.archived_downloadable', true));
                            @endphp
                            @if($releasePubliclyDownloadable || $canModerate)
                                <a class="{{ $pluginSecondaryBtnClass }}" href="{{ route('plugins.release.download', ['plugin' => $plugin, 'release' => $release]) }}">
                                    @if($release->status === \Filagora\Plugins\Enums\PluginStatus::ACTIVE)
                                        {{ ($canDownloadPlugin ?? false) ? 'Download' : 'Unlock download' }}
                                    @elseif($releaseIsArchived)
                                        Download (historical)
                                    @else
                                        Download for review
                                    @endif
                                </a>
                            @endif
                            @if($canModerate)
                                <a class="{{ $pluginSecondaryBtnClass }}" href="{{ \Filagora\Plugins\Filament\Resources\PluginReleases\PluginReleaseResource::getUrl('edit', ['record' => $release]) }}">Edit Release</a>
                            @endif
                            @if(! $releaseIsRejectedFinal && auth()->check() && auth()->user()?->hasVerifiedEmail() && auth()->id() !== $plugin->owner_id)
                                <button
                                    type="button"
                                    class="{{ $pluginActionButtonClass }}"
                                    data-report-open
                                    data-report-release-id="{{ $release->id }}"
                                    data-report-release-version="{{ $release->version }}"
                                >Report release</button>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="border border-[#d8dce2] bg-white p-3 text-[#304458]">No releases are visible yet.</li>
                @endforelse
            </ul>
        </section>

    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <div class="border border-[#d8dce2] bg-white p-3">
            <div class="text-xs font-semibold uppercase tracking-[0.04em] text-[#5c6570]">Author</div>
            <div class="mt-1 text-base font-semibold text-[#0f2133]">
                @if($plugin->owner?->id)
                    <a class="text-[#8f3f1f] hover:underline" href="{{ route('members.show', ['user' => $plugin->owner->id]) }}">{{ $pluginAuthor }}</a>
                @else
                    {{ $pluginAuthor }}
                @endif
            </div>
        </div>
        <div class="border border-[#d8dce2] bg-white p-3">
            <div class="text-xs font-semibold uppercase tracking-[0.04em] text-[#5c6570]">Updated</div>
            <div class="mt-1 text-base font-semibold text-[#0f2133]">{{ \App\Support\LocalTime::format($latestActiveRelease?->created_at ?? $plugin->updated_at, 'date') }}</div>
        </div>
        <div class="border border-[#d8dce2] bg-white p-3">
            <div class="text-xs font-semibold uppercase tracking-[0.04em] text-[#5c6570]">Releases</div>
            <div class="mt-1 text-base font-semibold text-[#0f2133]">{{ number_format($activeReleases->count()) }}</div>
        </div>
        <div class="border border-[#d8dce2] bg-white p-3">
            <div class="text-xs font-semibold uppercase tracking-[0.04em] text-[#5c6570]">Plugin ID</div>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <span class="text-base font-semibold text-[#0f2133]">{{ $canonicalPluginId }}</span>
                <button
                    type="button"
                    class="inline-flex items-center rounded-control border border-[#d8dce2] bg-white px-2 py-1 text-xs font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                    data-copy-plugin-id
                    data-copy-value="{{ $canonicalPluginId }}"
                    aria-label="Copy plugin ID {{ $canonicalPluginId }}"
                >Copy</button>
            </div>
        </div>
    </div>

    <div class="fixed inset-0 z-[1210] flex items-center justify-center p-4" id="report-plugin-modal" hidden>
        <div class="absolute inset-0 bg-[rgba(12,10,8,0.58)]" data-report-close></div>
        <div class="relative z-[1] w-full max-w-lg border border-[#d8dce2] bg-white p-4 shadow-[0_30px_60px_rgba(0,0,0,0.32)]" role="dialog" aria-modal="true" aria-labelledby="report-plugin-modal-title" aria-describedby="report-plugin-modal-description" tabindex="-1" data-report-dialog>
            <h2 class="text-lg font-semibold text-[#0f2133]" id="report-plugin-modal-title">Report Plugin</h2>
            <p class="mt-1 text-sm text-[#5c6570]" id="report-plugin-modal-description">Tell staff why this plugin should be reviewed.</p>
            <form method="post" action="{{ route('plugins.report', $plugin) }}" id="report-plugin-modal-form" class="mt-3 grid gap-2">
                @csrf
                <input type="hidden" name="plugin_release_id" id="report-plugin-modal-release-id" value="">
                <label class="text-sm font-semibold text-[#5c6570]" for="report-plugin-modal-reason">Reason (required)</label>
                <textarea
                    id="report-plugin-modal-reason"
                    name="reason"
                    rows="4"
                    required
                    maxlength="4000"
                    class="w-full rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                ></textarea>
                <p id="report-plugin-modal-error" class="text-sm text-[#dc3545]" role="alert" hidden></p>
                <div class="mt-2 flex items-center justify-end gap-2">
                    <button type="button" class="{{ $pluginActionButtonClass }}" data-report-close>Cancel</button>
                    <button type="submit" class="{{ $pluginPrimaryBtnClass }}">Submit Report</button>
                </div>
            </form>
        </div>
    </div>

    </div>
@endsection
