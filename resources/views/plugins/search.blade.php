@extends('layouts.main')

@section('title', 'Search Plugins')
@section('main_class', 'bg-white')

@section('content')
    @php
        $pluginPrimaryBtnClass = 'inline-flex items-center justify-center rounded-control border border-[#0b3d67] bg-[#0f4c81] px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-[#0b3d67] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0f4c81]';
        $pluginLinkClass = 'text-[#0d5fc8] hover:underline focus-visible:underline';
        $selectClass = 'rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] focus:border-[#8f3f1f] focus:outline-none focus:ring-2 focus:ring-[#8f3f1f]/25';
        $encapsulationBadgeClasses = [
            'yes' => 'border-[#b9d6bd] bg-[#f1fbf2] text-[#1f5130]',
            'no' => 'border-[#d9d2c4] bg-[#f8f5ef] text-[#5f4b2d]',
            'unknown' => 'border-[#bfd6eb] bg-[#edf4fb] text-[#21435f]',
        ];
    @endphp

    <div class="mx-auto max-w-[1100px] px-6 py-8 md:px-10">
        <header class="mb-6">
            <h1 class="ui-page-title">Search Plugins</h1>
            <p class="ui-page-subtitle mt-2">
                <a href="{{ route('plugins.index') }}" class="{{ $pluginLinkClass }}">Back to the plugin library</a>
            </p>
        </header>

        <div class="mb-6">
            @include('plugins.partials.search_filters', [
                'zcVersionOptions' => $zcVersionOptions,
                'typeOptions' => $typeOptions,
                'sortOptions' => $sortOptions,
                'selectedQ' => $query,
                'selectedZcVersion' => $zcVersion,
                'selectedType' => $activeType?->slug ?? '',
                'selectedEncapsulated' => $encapsulated,
                'selectedSort' => $sort,
            ])
        </div>

        @if($usedDatabaseFallback)
            <div class="mb-4 rounded-surface border border-[#e7d3b8] bg-[#fbf7f0] px-3 py-2 text-sm text-[#6f5a2f]" role="status">
                Search is running in a reduced mode right now &mdash; results may be less relevant than usual.
            </div>
        @endif

        @if($results === null)
            <p class="text-sm text-[#5c6570]">Enter a search term or choose a filter to find plugins.</p>
        @elseif($results->total() === 0)
            <p class="text-sm text-[#5c6570]">No plugins matched your search.</p>
        @else
            <p class="mb-3 text-sm text-[#5c6570]">{{ number_format($results->total()) }} {{ \Illuminate\Support\Str::plural('plugin', $results->total()) }} found.</p>

            <ul class="grid gap-3">
                @foreach($results as $plugin)
                    @php
                        $cardEncapsulated = $plugin->card_encapsulated ?? 'unknown';
                        $cardBadgeClass = $encapsulationBadgeClasses[$cardEncapsulated] ?? $encapsulationBadgeClasses['unknown'];
                        $latestReleaseAt = $plugin->card_latest_release_at
                            ? \Illuminate\Support\Carbon::parse((string) $plugin->card_latest_release_at)
                            : null;
                        $cardZcVersions = collect($plugin->card_zc_versions ?? [])->filter()->values();
                    @endphp
                    <li class="rounded-surface border border-[#d8dce2] bg-white p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <a href="{{ route('plugins.show', $plugin->slug) }}" class="text-lg font-semibold {{ $pluginLinkClass }}">{{ $plugin->name }}</a>
                                <p class="mt-0.5 text-xs text-[#5c6570]">
                                    {{ $plugin->type?->name ?? 'Uncategorised' }}
                                    @if($latestReleaseAt)
                                        &middot; latest release {{ $latestReleaseAt->format('d M Y') }}
                                    @endif
                                    &middot; {{ number_format((int) ($plugin->card_downloads ?? 0)) }} downloads
                                </p>
                            </div>
                            <span class="inline-flex items-center rounded-pill border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.03em] {{ $cardBadgeClass }}">
                                {{ $plugin->card_encapsulated_label ?? 'Encapsulation unknown' }}
                            </span>
                        </div>
                        @if($cardZcVersions->isNotEmpty())
                            <p class="mt-1 text-xs text-[#5c6570]">
                                Compatible with Zen Cart {{ $cardZcVersions->take(6)->join(', ') }}@if($cardZcVersions->count() > 6) &hellip;@endif
                            </p>
                        @endif
                        @if(filled($plugin->description))
                            <p class="mt-2 text-sm text-[#304458]">{{ \Illuminate\Support\Str::limit((string) $plugin->description, 260) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-5">
                {{ $results->links() }}
            </div>
        @endif
    </div>
@endsection
