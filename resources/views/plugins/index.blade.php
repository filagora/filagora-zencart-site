@extends('layouts.main')

@section('title', 'Plugin Library')
@section('main_class', 'bg-white')

@section('content')
    @php
        $pluginPrimaryBtnClass = 'inline-flex items-center justify-center rounded-control border border-[#0b3d67] bg-[#0f4c81] px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-[#0b3d67] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0f4c81]';
        $pluginSecondaryBtnClass = 'inline-flex items-center justify-center rounded-control border border-[#d8dce2] bg-white px-3.5 py-2 text-sm font-semibold text-[#2f3a46] transition hover:border-[#b8c5d3] hover:bg-[#f7f9fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0f4c81]';
        $pluginLinkClass = 'text-[#0d5fc8] hover:underline focus-visible:underline';
        $paginationLinkClass = 'inline-flex items-center rounded-control border border-[#d8dce2] px-2.5 py-1.5 text-sm font-medium text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f]';
        $paginationDisabledClass = 'inline-flex items-center rounded-control border border-[#d8dce2] px-2.5 py-1.5 text-sm font-medium text-[#9aa3ad]';
        $paginationPageClass = 'inline-flex min-w-8 items-center justify-center rounded-control border border-[#d8dce2] px-2 py-1 text-sm font-medium text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f]';
        $paginationCurrentClass = 'inline-flex min-w-8 items-center justify-center rounded-control border border-[#933c1f] bg-[#f7e8df] px-2 py-1 text-sm font-semibold text-[#933c1f]';
        $encapsulationBadgeClasses = [
            'yes' => 'border-[#b9d6bd] bg-[#f1fbf2] text-[#1f5130]',
            'no' => 'border-[#d9d2c4] bg-[#f8f5ef] text-[#5f4b2d]',
            'unknown' => 'border-[#bfd6eb] bg-[#edf4fb] text-[#21435f]',
        ];
    @endphp
    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
    @include('partials.site_guest_intro')
    @include('plugins.partials.unverified_banner')
    @include('plugins.partials.submissions_paused_banner')

    <header class="mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="ui-page-title">Plugin Library</h1>
                <p class="ui-page-subtitle mt-2">Browse plugins by category and discover recent updates.</p>
            </div>
            @if(config('plugins.submissions_enabled', true) && auth()->check() && auth()->user()?->hasVerifiedEmail())
                <form method="get" action="{{ route('plugins.submit') }}">
                    <button type="submit" class="inline-flex items-center rounded-control border border-[#8f3f1f] bg-[#8f3f1f] px-3 py-2 text-sm font-semibold text-white transition hover:border-[#7f3219] hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Add Plugin</button>
                </form>
            @endif
        </div>
    </header>

    <div class="mb-6">
        @include('plugins.partials.search_filters', [
            'zcVersionOptions' => $searchZcVersionOptions ?? [],
            'typeOptions' => $searchTypeOptions ?? collect(),
            'sortOptions' => $searchSortOptions ?? [],
            'selectedType' => $activeType?->slug ?? (string) request('type', ''),
        ])
    </div>

    @if(session('plugin_success'))
        <div class="mb-3 rounded-surface border border-[#b9d6bd] bg-[#f1fbf2] px-3 py-2 text-sm text-[#1f5130]" role="status" aria-live="polite">{{ session('plugin_success') }}</div>
    @endif
    @if(session('plugin_error'))
        <div class="mb-3 rounded-surface border border-[#efc2c6] bg-[#fff2f3] px-3 py-2 text-sm text-[#a63a45]" role="alert" aria-live="assertive">{{ session('plugin_error') }}</div>
    @endif

    @if($canModerate)
        @php
            $qBtn = 'inline-flex items-center justify-center rounded-control border px-2 py-1 text-xs font-semibold leading-4 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0f4c81]';
            $qBtnSecondary = $qBtn.' border-[#d8dce2] bg-white text-[#2f3a46] hover:border-[#b8c5d3] hover:bg-[#f7f9fc]';
            $qBtnApprove = $qBtn.' border-[#b9d6bd] bg-[#f1fbf2] text-[#1f5130] hover:bg-[#e3f5e6]';
            $qBtnReject = $qBtn.' border-[#0b3d67] bg-[#0f4c81] text-white hover:bg-[#0b3d67]';
            $qBtnDelete = $qBtn.' border-[#9f2230] bg-white text-[#9f2230] hover:bg-[#9f2230] hover:text-white';
            $qInput = 'w-full rounded-control border border-[#d8dce2] bg-white px-2 py-1 text-xs text-[#0f2133] placeholder:text-[#88919d] focus:border-[#8f3f1f] focus:outline-none focus:ring-2 focus:ring-[#8f3f1f]/25';
            $qPill = [
                'active' => 'border-[#b9d6bd] bg-[#f1fbf2] text-[#1f5130]',
                'moderated' => 'border-[#bfd6eb] bg-[#edf4fb] text-[#21435f]',
                'rejected' => 'border-[#efc2c6] bg-[#fff2f3] text-[#a63a45]',
                'rejected_final' => 'border-[#e2c6cc] bg-[#f8edf0] text-[#7b2735]',
            ];
            $qUserLabel = static fn ($u): string => $u?->username ?: ($u?->name ?: ($u?->email ?: 'Unknown author'));
        @endphp
        <details
            id="moderation-queue"
            class="mb-4 overflow-hidden border border-[#d8dce2] bg-white shadow-[0_2px_6px_rgba(15,23,42,0.04)]"
            data-has-moderation-error="{{ $errors->has('note') ? '1' : '0' }}"
            data-keep-queue-open="{{ session('plugin_keep_queue_open') ? '1' : '0' }}"
            @if($errors->has('note') || session('plugin_keep_queue_open')) open @endif
        >
            <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm text-[#243342] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f] [&::-webkit-details-marker]:hidden">
                <strong>Moderation Queue</strong>
                <small class="text-xs text-[#5c6570]">{{ $pendingPlugins->count() }} plugins · {{ $pendingReleases->count() }} releases to review</small>
            </summary>

            @if($errors->has('note'))
                <p class="border-t border-[#d8dce2] bg-[#fff2f3] px-3 py-1.5 text-xs text-[#a63a45]" role="alert">{{ $errors->first('note') }}</p>
            @endif
            <ul class="divide-y divide-[#e5e7eb] border-t border-[#d8dce2]">
                @foreach($pendingPlugins as $pendingPlugin)
                    @php
                        $itemAuthor = $pendingPlugin->owner;
                        $itemAuthorName = $qUserLabel($itemAuthor);
                        $itemCanBan = $itemAuthor && (int) $itemAuthor->id !== (int) auth()->id();
                        $itemAutoPublish = (bool) ($itemAuthor?->getAttribute('can_auto_publish') ?? false);
                        $releaseCount = (int) ($pendingPlugin->releases_count ?? 0);
                    @endphp
                    <li class="px-3 py-2">
                        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1.5">
                            <div class="min-w-0 flex-1 basis-64">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="rounded-control bg-[#eef1f5] px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#5c6570]">Plugin</span>
                                    <a class="text-sm font-semibold {{ $pluginLinkClass }}" href="{{ route('plugins.show', $pendingPlugin) }}">{{ $pendingPlugin->name }}</a>
                                    <span class="inline-flex items-center rounded-pill border px-2 py-0.5 text-[11px] font-semibold {{ $qPill[$pendingPlugin->status->value] ?? 'border-[#cabca8] bg-white text-[#3a4a5b]' }}">{{ $pendingPlugin->status->label() }}</span>
                                </div>
                                <p class="mt-0.5 text-xs text-[#5c6570]">
                                    {{ $pendingPlugin->type?->name ?? 'Unknown type' }} · {{ \App\Support\LocalTime::format($pendingPlugin->created_at, 'date') }} ·
                                    @if($itemAuthor?->id)
                                        <a class="{{ $pluginLinkClass }}" href="{{ route('members.show', ['user' => $itemAuthor->id]) }}">{{ $itemAuthorName }}</a>
                                    @else
                                        {{ $itemAuthorName }}
                                    @endif
                                    <span class="sr-only">Author: {{ $itemAuthorName }}</span>
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <form method="post" action="{{ route('plugins.moderate', $pendingPlugin) }}">
                                    @csrf
                                    <button type="submit" name="action" value="approve" class="{{ $qBtnApprove }}">Approve</button>
                                </form>
                                <button
                                    type="button"
                                    class="{{ $qBtnReject }}"
                                    data-plugin-reject-open
                                    data-action="{{ route('plugins.moderate', $pendingPlugin) }}"
                                    data-title="Reject this plugin?"
                                    data-context="“{{ $pendingPlugin->name }}” by {{ $itemAuthorName }}"
                                >Reject</button>
                                @if($releaseCount > 0)
                                    <a class="{{ $qBtnSecondary }}" href="{{ route('plugins.download', $pendingPlugin) }}">Download</a>
                                @endif
                                @if($itemAuthor?->id && ! $itemAutoPublish)
                                    <form method="post" action="{{ route('plugins.grant-auto-publish', $pendingPlugin) }}">
                                        @csrf
                                        <button type="submit" class="{{ $qBtnSecondary }}" title="Grant Auto-Publish to Author">Auto-publish</button>
                                    </form>
                                @endif
                                <button
                                    type="button"
                                    class="{{ $qBtnDelete }}"
                                    data-plugin-delete-open
                                    data-action="{{ route('plugins.destroy', $pendingPlugin) }}"
                                    data-title="Delete this plugin permanently?"
                                    data-context="“{{ $pendingPlugin->name }}” by {{ $itemAuthorName }}"
                                    data-summary="{{ trans_choice('{0} The plugin will be deleted.|{1} The plugin and its 1 release will be deleted.|[2,*] The plugin and all :count releases will be deleted.', $releaseCount, ['count' => $releaseCount]) }}"
                                    data-all-label="Also remove all plugins and releases by member “{{ $itemAuthorName }}”"
                                    data-all-hint="Every plugin they own and every release they uploaded."
                                    data-ban-label="{{ $itemCanBan ? 'Also permanently ban member “'.$itemAuthorName.'”' : '' }}"
                                    data-confirm="Delete Plugin"
                                >Delete</button>
                            </div>
                        </div>
                        @if($itemAuthor?->id && $itemAutoPublish)
                            <p class="mt-1 text-xs text-[#1f5130]">Auto-publish already enabled for this author.</p>
                        @endif
                        @if($pendingPlugin->moderatorNotes->count() > 0)
                            <details class="mt-1 text-xs">
                                <summary class="cursor-pointer font-medium text-[#304458]">Previous notes ({{ $pendingPlugin->moderatorNotes->count() }})</summary>
                                <ul class="mt-1 grid gap-1">
                                    @foreach($pendingPlugin->moderatorNotes as $note)
                                        <li class="border border-[#e5e7eb] px-2 py-1 text-[#304458]">
                                            <span class="text-[#5c6570]">{{ \App\Support\LocalTime::format($note->created_at) }} · {{ $qUserLabel($note->moderator) === 'Unknown author' ? 'System' : $qUserLabel($note->moderator) }}:</span>
                                            {{ $note->note }}
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </li>
                @endforeach

                @foreach($pendingReleases as $pendingRelease)
                    @php
                        $itemPlugin = $pendingRelease->plugin;
                        $itemAuthor = $pendingRelease->uploadedBy ?? $itemPlugin?->owner;
                        $itemAuthorName = $qUserLabel($itemAuthor);
                        $itemCanBan = $itemAuthor && (int) $itemAuthor->id !== (int) auth()->id();
                        $itemAutoPublish = (bool) ($itemAuthor?->getAttribute('can_auto_publish') ?? false);
                        $versionRaw = trim((string) $pendingRelease->version);
                        $versionLabel = \Illuminate\Support\Str::startsWith(strtolower($versionRaw), 'v') ? $versionRaw : 'v'.$versionRaw;
                        $itemNotes = $itemPlugin?->moderatorNotes ?? collect();
                    @endphp
                    <li class="px-3 py-2">
                        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1.5">
                            <div class="min-w-0 flex-1 basis-64">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="rounded-control bg-[#eef1f5] px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#5c6570]">Release</span>
                                    @if($itemPlugin)
                                        <a class="text-sm font-semibold {{ $pluginLinkClass }}" href="{{ route('plugins.show', $itemPlugin) }}">{{ $itemPlugin->name }} {{ $versionLabel }}</a>
                                    @else
                                        <span class="text-sm font-semibold">Unknown plugin {{ $versionLabel }}</span>
                                    @endif
                                    <span class="inline-flex items-center rounded-pill border px-2 py-0.5 text-[11px] font-semibold {{ $qPill[$pendingRelease->status->value] ?? 'border-[#cabca8] bg-white text-[#3a4a5b]' }}">{{ $pendingRelease->status->label() }}</span>
                                </div>
                                <p class="mt-0.5 text-xs text-[#5c6570]">
                                    {{ \App\Support\LocalTime::format($pendingRelease->created_at, 'date') }} ·
                                    @if($itemAuthor?->id)
                                        <a class="{{ $pluginLinkClass }}" href="{{ route('members.show', ['user' => $itemAuthor->id]) }}">{{ $itemAuthorName }}</a>
                                    @else
                                        {{ $itemAuthorName }}
                                    @endif
                                    <span class="sr-only">Release author: {{ $itemAuthorName }}</span>
                                </p>
                            </div>
                            @if($itemPlugin)
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <form method="post" action="{{ route('plugins.release.moderate', ['plugin' => $itemPlugin, 'release' => $pendingRelease]) }}">
                                        @csrf
                                        <button type="submit" name="action" value="approve" class="{{ $qBtnApprove }}">Approve</button>
                                    </form>
                                    <button
                                        type="button"
                                        class="{{ $qBtnReject }}"
                                        data-plugin-reject-open
                                        data-action="{{ route('plugins.release.moderate', ['plugin' => $itemPlugin, 'release' => $pendingRelease]) }}"
                                        data-title="Reject this release?"
                                        data-context="“{{ $itemPlugin->name }} {{ $versionLabel }}” by {{ $itemAuthorName }}"
                                    >Reject</button>
                                    <a class="{{ $qBtnSecondary }}" href="{{ route('plugins.release.download', ['plugin' => $itemPlugin, 'release' => $pendingRelease]) }}">Download</a>
                                    @if($itemAuthor?->id && ! $itemAutoPublish)
                                        <form method="post" action="{{ route('plugins.release.grant-auto-publish', ['plugin' => $itemPlugin, 'release' => $pendingRelease]) }}">
                                            @csrf
                                            <button type="submit" class="{{ $qBtnSecondary }}" title="Grant Auto-Publish to Release Author">Auto-publish</button>
                                        </form>
                                    @endif
                                    <button
                                        type="button"
                                        class="{{ $qBtnDelete }}"
                                        data-plugin-delete-open
                                        data-action="{{ route('plugins.release.destroy', ['plugin' => $itemPlugin, 'release' => $pendingRelease]) }}"
                                        data-title="Delete this release permanently?"
                                        data-context="“{{ $itemPlugin->name }} {{ $versionLabel }}” by {{ $itemAuthorName }}"
                                        data-summary="This release and its package file will be deleted. The plugin stays."
                                        data-all-label="Also remove all releases uploaded by member “{{ $itemAuthorName }}”"
                                        data-all-hint="Every release they uploaded, including on other plugins."
                                        data-ban-label="{{ $itemCanBan ? 'Also permanently ban member “'.$itemAuthorName.'”' : '' }}"
                                        data-confirm="Delete Release"
                                    >Delete</button>
                                </div>
                            @endif
                        </div>
                        @if($itemAuthor?->id && $itemAutoPublish)
                            <p class="mt-1 text-xs text-[#1f5130]">Auto-publish already enabled for this release author.</p>
                        @endif
                        @if($itemNotes->count() > 0)
                            <details class="mt-1 text-xs">
                                <summary class="cursor-pointer font-medium text-[#304458]">Previous notes ({{ $itemNotes->count() }})</summary>
                                <ul class="mt-1 grid gap-1">
                                    @foreach($itemNotes as $note)
                                        <li class="border border-[#e5e7eb] px-2 py-1 text-[#304458]">
                                            <span class="text-[#5c6570]">{{ \App\Support\LocalTime::format($note->created_at) }} · {{ $qUserLabel($note->moderator) === 'Unknown author' ? 'System' : $qUserLabel($note->moderator) }}:</span>
                                            {{ $note->note }}
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </li>
                @endforeach

                @if($pendingPlugins->isEmpty() && $pendingReleases->isEmpty())
                    <li class="px-3 py-2"><small class="text-xs text-[#5c6570]">Nothing to review.</small></li>
                @endif
            </ul>
        </details>
        @push('modals')
            @include('plugins.partials.delete_dialog')
            @include('plugins.partials.reject_dialog')
        @endpush
    @endif

    <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="min-w-0 space-y-4">
            <div class="border border-[#d8dce2] bg-white px-4 py-3">
                <p class="text-sm text-[#304458]">Plugins for Zen Cart contributed by the community</p>
            </div>

            @if(!$activeType)
                <div class="overflow-hidden border border-[#d8dce2] bg-white shadow-[0_2px_6px_rgba(15,23,42,0.04)]">
                <table class="min-w-full border-collapse">
                    <thead class="bg-white">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.07em] text-[#5c6570]">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.07em] text-[#5c6570]">Description</th>
                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-[0.07em] text-[#5c6570]">Count</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($typeRows as $row)
                        <tr class="border-t border-[#d8dce2] align-top">
                            <td class="px-4 py-3">
                                <a class="font-semibold text-[#304458] hover:text-[#8f3f1f] hover:underline focus-visible:text-[#8f3f1f] focus-visible:underline" href="{{ route('plugins.type', ['slug' => $row['type']['slug']]) }}">
                                    {{ $row['label'] }}
                                </a>
                            </td>
                            <td class="px-4 py-3"><p class="text-sm text-[#5c6570]">{{ $row['description'] }}</p></td>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-[#304458]">{{ number_format((int) $row['count']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
            @endif

            @if($activeType && is_array($categoryListing))
                @php
                    $categoryPage = max(1, (int) ($categoryListing['page'] ?? 1));
                    $categoryPages = max(1, (int) ($categoryListing['pages'] ?? 1));
                    $categoryWindow = 2;
                    $categoryStart = max(1, $categoryPage - $categoryWindow);
                    $categoryEnd = min($categoryPages, $categoryPage + $categoryWindow);

                    if ($categoryStart <= 3) {
                        $categoryStart = 1;
                    }
                    if ($categoryEnd >= $categoryPages - 2) {
                        $categoryEnd = $categoryPages;
                    }
                @endphp

                <section class="mt-[14px]">
                    @php
                        $currentCategorySort = $categorySort ?? 'date_desc';
                        $pluginSortIsActive = str_starts_with($currentCategorySort, 'name_');
                        $dateSortIsActive = str_starts_with($currentCategorySort, 'date_');
                        $nextPluginSort = $currentCategorySort === 'name_asc' ? 'name_desc' : 'name_asc';
                        $nextDateSort = $currentCategorySort === 'date_desc' ? 'date_asc' : 'date_desc';
                        $baseCategoryRouteParams = ['slug' => $activeType->slug];
                        $pluginSortArrow = $pluginSortIsActive ? ($currentCategorySort === 'name_asc' ? '↑' : '↓') : '↕';
                        $dateSortArrow = $dateSortIsActive ? ($currentCategorySort === 'date_asc' ? '↑' : '↓') : '↕';
                        $sortableHeaderBaseClass = 'inline-flex cursor-pointer items-center gap-1 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
                        $sortableHeaderIdleClass = 'text-[#304458] hover:text-[#8f3f1f]';
                        $sortableHeaderActiveClass = 'text-[#8f3f1f]';
                    @endphp

                    <header class="mb-4 border border-[#d8dce2] bg-white p-4">
                        <div class="flex flex-col items-start justify-between gap-3 md:flex-row md:items-center">
                            <div>
                                <a
                                    href="{{ route('plugins.index') }}"
                                    class="inline-flex items-center gap-1 text-sm font-semibold text-[#304458] transition hover:text-[#8f3f1f] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                                >
                                    <span aria-hidden="true">←</span>
                                    <span>Back to Plugin Library</span>
                                </a>
                                <h2 class="text-2xl font-semibold text-[#0f2133]">{{ $activeType->name }}</h2>
                                <p class="mt-1 text-sm text-[#5c6570]">Files in category: {{ $activeType->name }}</p>
                            </div>
                            <div class="text-sm text-[#5c6570]">
                                {{ number_format((int) ($categoryListing['total'] ?? 0)) }} plugins
                            </div>
                        </div>
                    </header>

                    @if($categoryPages > 1)
                        <nav class="mb-4 border border-[#d8dce2] bg-white p-3" aria-label="Plugin category pagination">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div class="inline-flex items-center gap-2">
                                    @if($categoryPage > 1)
                                        <a class="{{ $paginationLinkClass }}" href="{{ route('plugins.type', $baseCategoryRouteParams + ['page' => 1, 'sort' => $categorySort]) }}">First</a>
                                        <a class="{{ $paginationLinkClass }}" href="{{ route('plugins.type', $baseCategoryRouteParams + ['page' => $categoryPage - 1, 'sort' => $categorySort]) }}" rel="prev">Previous</a>
                                    @else
                                        <span class="{{ $paginationDisabledClass }}" aria-disabled="true">First</span>
                                        <span class="{{ $paginationDisabledClass }}" aria-disabled="true">Previous</span>
                                    @endif
                                </div>

                                <div class="inline-flex items-center gap-2">
                                    @if($categoryStart > 1)
                                        <a class="{{ $paginationPageClass }}" href="{{ route('plugins.type', $baseCategoryRouteParams + ['page' => 1, 'sort' => $categorySort]) }}">1</a>
                                    @endif

                                    @if($categoryStart > 2)
                                        <span class="px-1 text-[#5c6570]" aria-hidden="true">…</span>
                                    @endif

                                    @for($p = $categoryStart; $p <= $categoryEnd; $p++)
                                        @if($p === $categoryPage)
                                            <span class="{{ $paginationCurrentClass }}" aria-current="page">{{ $p }}</span>
                                        @else
                                            <a class="{{ $paginationPageClass }}" href="{{ route('plugins.type', $baseCategoryRouteParams + ['page' => $p, 'sort' => $categorySort]) }}">{{ $p }}</a>
                                        @endif
                                    @endfor

                                    @if($categoryEnd < $categoryPages - 1)
                                        <span class="px-1 text-[#5c6570]" aria-hidden="true">…</span>
                                    @endif

                                    @if($categoryEnd < $categoryPages)
                                        <a class="{{ $paginationPageClass }}" href="{{ route('plugins.type', $baseCategoryRouteParams + ['page' => $categoryPages, 'sort' => $categorySort]) }}">{{ $categoryPages }}</a>
                                    @endif
                                </div>

                                <div class="inline-flex items-center gap-2">
                                    @if($categoryPage < $categoryPages)
                                        <a class="{{ $paginationLinkClass }}" href="{{ route('plugins.type', $baseCategoryRouteParams + ['page' => $categoryPage + 1, 'sort' => $categorySort]) }}" rel="next">Next</a>
                                        <a class="{{ $paginationLinkClass }}" href="{{ route('plugins.type', $baseCategoryRouteParams + ['page' => $categoryPages, 'sort' => $categorySort]) }}">Last</a>
                                    @else
                                        <span class="{{ $paginationDisabledClass }}" aria-disabled="true">Next</span>
                                        <span class="{{ $paginationDisabledClass }}" aria-disabled="true">Last</span>
                                    @endif
                                </div>
                            </div>

                            <div class="text-sm text-[#5c6570]">
                                Page {{ $categoryPage }} of {{ $categoryPages }}
                            </div>
                        </nav>
                    @endif

                    <div class="overflow-hidden border border-[#d8dce2] bg-white shadow-[0_2px_6px_rgba(15,23,42,0.04)]">
                    <div class="grid grid-cols-[minmax(0,1fr)_130px_110px] gap-2.5 border-b border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#304458] max-sm:grid-cols-1">
                        <div>
                            <a
                                class="{{ $sortableHeaderBaseClass }} {{ $pluginSortIsActive ? $sortableHeaderActiveClass : $sortableHeaderIdleClass }}"
                                href="{{ route('plugins.type', $baseCategoryRouteParams + ['sort' => $nextPluginSort]) }}"
                                aria-label="Sort plugins by name"
                                title="Sort by plugin name"
                                data-plugin-sort="name"
                                data-plugin-sort-direction="{{ $pluginSortIsActive ? ($currentCategorySort === 'name_asc' ? 'asc' : 'desc') : 'none' }}"
                            >
                                <span>Plugin</span>
                                <span aria-hidden="true">{{ $pluginSortArrow }}</span>
                            </a>
                        </div>
                        <div class="text-right">
                            <a
                                class="{{ $sortableHeaderBaseClass }} {{ $dateSortIsActive ? $sortableHeaderActiveClass : $sortableHeaderIdleClass }}"
                                href="{{ route('plugins.type', $baseCategoryRouteParams + ['sort' => $nextDateSort]) }}"
                                aria-label="Sort plugins by date"
                                title="Sort by plugin date"
                                data-plugin-sort="date"
                                data-plugin-sort-direction="{{ $dateSortIsActive ? ($currentCategorySort === 'date_asc' ? 'asc' : 'desc') : 'none' }}"
                            >
                                <span>Date</span>
                                <span aria-hidden="true">{{ $dateSortArrow }}</span>
                            </a>
                        </div>
                        <div class="text-right">Releases</div>
                    </div>

                    @forelse($categoryListing['items'] as $item)
                        <article class="relative grid grid-cols-[minmax(0,1fr)_130px_110px] gap-2.5 border-t border-[#d8dce2] px-3 py-3 first:border-t-0 hover:bg-[#f0f2f5] max-sm:grid-cols-1 {{ $loop->even ? 'bg-[#fafbfc]' : 'bg-white' }}">
                            <div>
                                <h3 class="m-0 mb-1 text-lg text-[#2f3a46]">
                                    <a class="{{ $pluginLinkClass }} after:absolute after:inset-0 after:content-['']" href="{{ route('plugins.show', ['plugin' => $item['slug']]) }}">{{ $item['name'] }}</a>
                                </h3>
                                <div class="mb-1.5">
                                    <span class="inline-flex items-center rounded-pill border px-2.5 py-1 text-xs font-semibold {{ $encapsulationBadgeClasses[$item['encapsulated'] ?? 'unknown'] ?? $encapsulationBadgeClasses['unknown'] }}">
                                        {{ $item['encapsulated_label'] ?? 'Encapsulation unknown' }}
                                    </span>
                                </div>
                                <p class="m-0 text-[15px] leading-[1.4] text-[#3b4551]">{{ $item['description'] ?? '' }}</p>
                            </div>
                            <div class="text-right text-sm text-[#3b4551] max-sm:text-left">{{ $item['updated_at_display'] }}</div>
                            <div class="text-right text-sm text-[#3b4551] max-sm:text-left">{{ number_format((int) $item['releases_count']) }}</div>
                        </article>
                    @empty
                        <article class="grid grid-cols-[minmax(0,1fr)_130px_110px] gap-2.5 border-t border-[#d8dce2] px-3 py-3 first:border-t-0 max-sm:grid-cols-1">
                            <div>No plugins found for this category.</div>
                            <div></div>
                            <div></div>
                        </article>
                    @endforelse
                    </div>

                    @if($categoryPages > 1)
                        <nav class="mt-4 border border-[#d8dce2] bg-white p-3" aria-label="Plugin category pagination">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div class="inline-flex items-center gap-2">
                                    @if($categoryPage > 1)
                                        <a class="{{ $paginationLinkClass }}" href="{{ route('plugins.type', $baseCategoryRouteParams + ['page' => 1, 'sort' => $categorySort]) }}">First</a>
                                        <a class="{{ $paginationLinkClass }}" href="{{ route('plugins.type', $baseCategoryRouteParams + ['page' => $categoryPage - 1, 'sort' => $categorySort]) }}" rel="prev">Previous</a>
                                    @else
                                        <span class="{{ $paginationDisabledClass }}" aria-disabled="true">First</span>
                                        <span class="{{ $paginationDisabledClass }}" aria-disabled="true">Previous</span>
                                    @endif
                                </div>

                                <div class="inline-flex items-center gap-2">
                                    @if($categoryStart > 1)
                                        <a class="{{ $paginationPageClass }}" href="{{ route('plugins.type', $baseCategoryRouteParams + ['page' => 1, 'sort' => $categorySort]) }}">1</a>
                                    @endif

                                    @if($categoryStart > 2)
                                        <span class="px-1 text-[#5c6570]" aria-hidden="true">…</span>
                                    @endif

                                    @for($p = $categoryStart; $p <= $categoryEnd; $p++)
                                        @if($p === $categoryPage)
                                            <span class="{{ $paginationCurrentClass }}" aria-current="page">{{ $p }}</span>
                                        @else
                                            <a class="{{ $paginationPageClass }}" href="{{ route('plugins.type', $baseCategoryRouteParams + ['page' => $p, 'sort' => $categorySort]) }}">{{ $p }}</a>
                                        @endif
                                    @endfor

                                    @if($categoryEnd < $categoryPages - 1)
                                        <span class="px-1 text-[#5c6570]" aria-hidden="true">…</span>
                                    @endif

                                    @if($categoryEnd < $categoryPages)
                                        <a class="{{ $paginationPageClass }}" href="{{ route('plugins.type', $baseCategoryRouteParams + ['page' => $categoryPages, 'sort' => $categorySort]) }}">{{ $categoryPages }}</a>
                                    @endif
                                </div>

                                <div class="inline-flex items-center gap-2">
                                    @if($categoryPage < $categoryPages)
                                        <a class="{{ $paginationLinkClass }}" href="{{ route('plugins.type', $baseCategoryRouteParams + ['page' => $categoryPage + 1, 'sort' => $categorySort]) }}" rel="next">Next</a>
                                        <a class="{{ $paginationLinkClass }}" href="{{ route('plugins.type', $baseCategoryRouteParams + ['page' => $categoryPages, 'sort' => $categorySort]) }}">Last</a>
                                    @else
                                        <span class="{{ $paginationDisabledClass }}" aria-disabled="true">Next</span>
                                        <span class="{{ $paginationDisabledClass }}" aria-disabled="true">Last</span>
                                    @endif
                                </div>
                            </div>

                            <div class="text-sm text-[#5c6570]">
                                Page {{ $categoryPage }} of {{ $categoryPages }}
                            </div>
                        </nav>
                    @endif
                </section>
            @endif
        </div>

        <aside class="space-y-4">
            <section class="border border-[#d8dce2] bg-white p-4 shadow-[0_2px_6px_rgba(15,23,42,0.04)]">
                <h3 class="text-base font-semibold text-[#0f2133]">Latest Plugins/Addons</h3>
                <ul class="mt-3 grid gap-2">
                    @forelse($latestPlugins as $item)
                        <li class="grid gap-1 border border-[#e5e7eb] bg-white p-2">
                            <strong>
                                <a class="{{ $pluginLinkClass }}" href="{{ route('plugins.show', ['plugin' => $item['slug']]) }}">{{ $item['name'] }}</a>
                            </strong>
                            <div>
                                <span class="inline-flex items-center rounded-pill border px-2 py-0.5 text-[11px] font-semibold {{ $encapsulationBadgeClasses[$item['encapsulated'] ?? 'unknown'] ?? $encapsulationBadgeClasses['unknown'] }}">
                                    {{ $item['encapsulated_label'] ?? 'Encapsulation unknown' }}
                                </span>
                            </div>
                            <small class="text-xs text-[#5c6570]">{{ $item['sidebar_date_display'] }}</small>
                        </li>
                    @empty
                        <li class="border border-[#e5e7eb] bg-white p-2"><small class="text-xs text-[#5c6570]">No plugins found.</small></li>
                    @endforelse
                </ul>
            </section>

            <section class="border border-[#d8dce2] bg-white p-4 shadow-[0_2px_6px_rgba(15,23,42,0.04)]">
                <h3 class="text-base font-semibold text-[#0f2133]">Top Contributors</h3>
                <ul class="mt-3 grid gap-2">
                    @forelse($topContributors as $item)
                        <li class="grid gap-1">
                            @if(!empty($item['user_id']))
                                <strong>
                                    <a class="{{ $pluginLinkClass }}" href="{{ route('members.show', ['user' => $item['user_id']]) }}">
                                        {{ $item['label'] }}
                                    </a>
                                </strong>
                            @else
                                <strong>{{ $item['label'] }}</strong>
                            @endif
                            <small class="text-xs text-[#5c6570]">{{ number_format((int) $item['count']) }} contributions</small>
                        </li>
                    @empty
                        <li><small class="text-xs text-[#5c6570]">No contributors yet.</small></li>
                    @endforelse
                </ul>
            </section>
        </aside>
    </section>

    </div>
@endsection
