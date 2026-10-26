@extends('layouts.main')

@section('title')
Search
@endsection

@section('main_class', 'bg-[#dddddf]')

@section('content')
    @php
        $decodeLegacyText = static fn (?string $value): string => \App\Support\LegacyTextDecoder::decode($value);
    @endphp

    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
        @include('partials.site_guest_intro')

        <header class="mb-5">
            <h1 class="text-3xl font-semibold text-[#0f2133]">Search</h1>
            @if($query !== '')
                @if($searchWasSkipped ?? false)
                    <p class="mt-1 text-sm text-[#5c6570]">Enter at least {{ (int) ($minimumQueryLength ?? 2) }} characters to search.</p>
                @else
                    <p class="mt-1 text-sm text-[#5c6570]">
                        Results for "{{ $query }}" · {{ number_format((int) $totalResults) }} results
                    </p>
                    @if(!empty($sourceCounts))
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a
                                href="{{ route('search.index', ['q' => $query]) }}"
                                class="rounded-pill border px-3 py-1 text-xs font-semibold transition {{ is_null($selectedType ?? null) ? 'border-[#8f3f1f] bg-[#8f3f1f] text-white' : 'border-[#cabca8] text-[#5c6570] hover:border-[#8f3f1f] hover:text-[#8f3f1f]' }}"
                            >
                                All ({{ number_format((int) array_sum($sourceCounts)) }})
                            </a>
                            @foreach(\App\Search\SearchResultType::cases() as $type)
                                @php($typeCount = $sourceCounts[$type->sourceLabel()] ?? 0)
                                @if($typeCount > 0 || ($selectedType ?? null) === $type)
                                    <a
                                        href="{{ route('search.index', ['q' => $query, 'type' => $type->value]) }}"
                                        class="rounded-pill border px-3 py-1 text-xs font-semibold transition {{ ($selectedType ?? null) === $type ? 'border-[#8f3f1f] bg-[#8f3f1f] text-white' : 'border-[#cabca8] text-[#5c6570] hover:border-[#8f3f1f] hover:text-[#8f3f1f]' }}"
                                    >
                                        {{ $type->label() }} ({{ number_format($typeCount) }})
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                @endif
            @else
                <p class="mt-1 text-sm text-[#5c6570]">Enter a search term to find content across forums, plugins, and CMS pages.</p>
            @endif
        </header>

        @if($query !== '' && !($searchWasSkipped ?? false) && $totalResults === 0)
            <section class="mb-4 rounded-surface border border-[#cabca8] bg-[#ececed] p-4">
                <div class="text-sm text-[#5c6570]">No results found.</div>
            </section>
        @endif

        @foreach($sections as $section)
            @if(!empty($section['items']))
                <section class="mb-4 rounded-surface border border-[#cabca8] bg-[#ececed]">
                    <header class="border-b border-[#d8d1c8] px-4 py-3">
                        <h2 class="text-xl font-semibold text-[#0f2133]">{{ $section['title'] }}</h2>
                    </header>
                    <div class="divide-y divide-[#d8d1c8]">
                        @foreach($section['items'] as $item)
                            <article class="grid gap-2 px-4 py-3 md:grid-cols-[1fr_auto] md:items-start">
                                <div class="min-w-0">
                                    <h3 class="text-base font-semibold text-[#0f2133]">
                                        <a class="hover:text-[#8f3f1f]" href="{{ $item['url'] }}">{{ $decodeLegacyText((string) $item['title']) }}</a>
                                    </h3>
                                    @if(($item['encapsulated_badge'] ?? null) || !empty($item['supported_versions'] ?? []))
                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                            @if(($item['encapsulated_badge'] ?? null))
                                                <span class="inline-flex items-center rounded-pill border border-[#1f6f5a] bg-[#e7f6f0] px-2 py-0.5 font-semibold uppercase tracking-[0.03em] text-[#1f6f5a]">
                                                    {{ $item['encapsulated_badge'] }}
                                                </span>
                                            @endif
                                            @if(!empty($item['supported_versions'] ?? []))
                                                <span class="text-[#5c6570]">
                                                    ZC versions: {{ implode(', ', $item['supported_versions']) }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                    @if(!empty($item['excerpt'] ?? ''))
                                        <p class="mt-1 text-sm text-[#0f2133]">{{ $decodeLegacyText((string) $item['excerpt']) }}</p>
                                    @endif
                                    @if(!empty($item['meta'] ?? []))
                                        <p class="mt-1 text-sm text-[#5c6570]">{{-- no whitespace between tokens: keep the loop on one line --}}@foreach($item['meta'] as $token)@if(($token['url'] ?? null))<a class="text-[#5c6570] hover:text-[#8f3f1f] hover:underline" href="{{ $token['url'] }}">{{ $decodeLegacyText((string) $token['text']) }}</a>@else{{ $decodeLegacyText((string) $token['text']) }}@endif@endforeach</p>
                                    @elseif(($item['note'] ?? '') !== '')
                                        <p class="mt-1 text-sm text-[#5c6570]">{{ $decodeLegacyText((string) $item['note']) }}</p>
                                    @endif
                                </div>
                                <div class="text-xs font-semibold uppercase tracking-[0.03em] text-[#5c6570]">Source: {{ $section['source'] }}</div>
                            </article>
                        @endforeach
                    </div>
                    @if($section['source'] === \App\Search\SearchResultType::PluginRelease->sourceLabel() && ($query ?? '') !== '')
                        <footer class="border-t border-[#d8d1c8] px-4 py-3 text-sm">
                            <a class="font-semibold text-[#8f3f1f] hover:underline" href="{{ route('plugins.search', ['q' => $query]) }}">
                                Search all plugins for &ldquo;{{ $query }}&rdquo; &rarr;
                            </a>
                        </footer>
                    @endif
                    @if($section['source'] === \App\Search\SearchResultType::Thread->sourceLabel() && ($query ?? '') !== '')
                        <footer class="border-t border-[#d8d1c8] px-4 py-3 text-sm">
                            <a class="font-semibold text-[#8f3f1f] hover:underline" href="{{ route('forums.search', ['q' => $query]) }}">
                                Search all threads for &ldquo;{{ $query }}&rdquo;, with filters &rarr;
                            </a>
                        </footer>
                    @endif
                </section>
            @endif
        @endforeach
    </div>
@endsection
