@extends('layouts.main')

@section('title', __('forum.search.page_title'))
@section('main_class', 'bg-white')

@section('content')
    @php
        /** @var \Filagora\Forums\Search\ForumSearchCriteria $criteria */
        $decodeLegacyText = static fn (?string $value): string => \App\Support\LegacyTextDecoder::decode($value);
        $chipClass = 'inline-flex items-center gap-1.5 rounded-pill border border-[#cabca8] bg-white px-2.5 py-0.5 text-xs font-semibold text-[#304458]';
        $chipRemoveClass = 'text-sm leading-none text-[#5c6570] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $badgeClass = 'inline-flex items-center rounded-pill border border-[#933c1f] bg-[#f7e8df] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.03em] text-[#933c1f]';
        $withoutQuery = static fn (array $keys): string => request()->fullUrlWithoutQuery([...$keys, 'page']);

        $chips = array_values(array_filter([
            $activeForum ? [__('forum.search.chip_forum', ['forum' => $decodeLegacyText($activeForum->name)]), ['in', 'forum']] : null,
            $authorName !== '' && ! $authorNotFound ? [__($criteria->authorMode === 'posted' ? 'forum.search.chip_author_posted' : 'forum.search.chip_author_started', ['name' => $authorName]), ['author', 'author_mode']] : null,
            $criteria->since !== null ? [__('forum.search.since.' . $criteria->since), ['since']] : null,
            $criteria->minReplies > 0 ? [__('forum.search.chip_min_replies', ['count' => $criteria->minReplies]), ['min_replies']] : null,
            $criteria->mineUserId !== null ? [__('forum.search.chip_mine'), ['mine']] : null,
            $criteria->includeUnapproved ? [__('forum.search.chip_unapproved'), ['include_unapproved']] : null,
        ]));
        $heading = match (true) {
            $results === null => '',
            $results->total() >= \App\Http\Controllers\ForumSearchController::COUNTED_RESULTS_CAP => __('forum.search.results_heading_capped', ['count' => number_format(\App\Http\Controllers\ForumSearchController::COUNTED_RESULTS_CAP)]),
            default => trans_choice('forum.search.results_heading', $results->total(), ['count' => number_format($results->total())]),
        };
    @endphp

    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
        @include('forum.partials.header_nav')

        <div class="mt-3">
            @include('forum.partials.search_bar', [
                'searchForum' => $activeForum,
                'searchCriteria' => $criteria,
                'searchAuthorName' => $authorName,
            ])
        </div>

        <div class="mb-3 text-sm text-[#5c6570]">
            <a class="text-[#8f3f1f] hover:underline" href="{{ route('forums.index') }}">Forums</a>
            / {{ __('forum.search.page_title') }}
        </div>

        @if($searchUnavailable ?? false)
            <p class="mb-4 rounded-surface border border-[#d7b8a6] bg-[#f7e8df] px-3 py-2 text-sm text-[#6f2f16]" role="status">{{ __('forum.search.unavailable') }}</p>
        @elseif($authorNotFound)
            <p class="mb-4 rounded-surface border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]" role="alert">{{ __('forum.search.author_not_found', ['name' => $authorName]) }}</p>
        @elseif($results === null)
            <p class="rounded-surface border border-[#d8dce2] bg-[#faf8f4] px-4 py-6 text-sm text-[#5c6570]">{{ __('forum.search.prompt') }}</p>
        @else
            <header class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h1 class="text-2xl font-semibold text-[#0f2133]">
                    {{ $criteria->query !== '' ? __('forum.search.results_for', ['heading' => $heading, 'query' => $criteria->query]) : $heading }}
                </h1>
                <p class="text-sm text-[#5c6570]">{{ __('forum.search.sort_label') }}: {{ __('forum.search.sort.' . $criteria->effectiveSort()) }}</p>
            </header>

            @if($chips !== [])
                <ul class="mb-4 flex flex-wrap gap-2" aria-label="{{ __('forum.search.filters') }}">
                    @foreach($chips as [$chipLabel, $chipKeys])
                        <li class="{{ $chipClass }}">
                            {{ $chipLabel }}
                            <a href="{{ $withoutQuery($chipKeys) }}" class="{{ $chipRemoveClass }}" aria-label="{{ __('forum.search.remove_filter', ['filter' => $chipLabel]) }}">&times;</a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if($usedDatabaseFallback && $criteria->sort === 'relevance')
                <p class="mb-3 text-sm text-[#5c6570]">{{ __('forum.search.fallback_notice') }}</p>
            @endif

            @if($unapprovedThreads->isNotEmpty())
                <section class="mb-5 rounded-surface border border-[#d7b8a6] bg-[#f7e8df] p-3" aria-labelledby="forum-search-unapproved">
                    <h2 id="forum-search-unapproved" class="mb-2 text-sm font-semibold text-[#6f2f16]">{{ __('forum.search.unapproved_heading') }}</h2>
                    <ul class="grid gap-1.5 text-sm">
                        @foreach($unapprovedThreads as $pendingThread)
                            <li class="flex flex-wrap items-center gap-2">
                                <span class="{{ $badgeClass }}">{{ __('forum.search.unapproved_badge.' . match ($pendingThread->moderation_status) {
                                    \Filagora\Forums\Enums\ContentModerationStatus::PENDING => 'pending',
                                    \Filagora\Forums\Enums\ContentModerationStatus::REJECTED_FINAL => 'rejected_final',
                                    default => 'rejected',
                                }) }}</span>
                                <a class="font-semibold text-[#8f3f1f] hover:underline" href="{{ route('threads.show', $pendingThread) }}">{{ $decodeLegacyText($pendingThread->title) }}</a>
                                <span class="text-xs text-[#5c6570]">{{ $decodeLegacyText($pendingThread->forum?->name) }} · {{ \App\Support\LocalTime::format($pendingThread->created_at) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if($results->total() > 0)
                <ol class="border border-[#d8dce2]">
                    @foreach($results as $resultThread)
                        <li class="grid gap-x-4 gap-y-1 border-t border-[#e2e6ea] px-4 py-3 first:border-t-0 sm:grid-cols-[minmax(0,1fr)_auto]">
                            <div class="min-w-0">
                                <h2 class="text-base font-semibold [overflow-wrap:anywhere]">
                                    <a class="text-[#8f3f1f] hover:underline" href="{{ route('threads.show', $resultThread) }}">{{ $decodeLegacyText($resultThread->title) }}</a>
                                </h2>
                                <p class="text-xs text-[#5c6570]">
                                    {{ __('forum.search.thread_meta', [
                                        'forum' => $decodeLegacyText($resultThread->forum?->name),
                                        'author' => $resultThread->author?->username ?: ($resultThread->author?->name ?: __('forum.search.unknown_author')),
                                        'date' => \App\Support\LocalTime::format($resultThread->last_post_at ?? $resultThread->created_at),
                                    ]) }}
                                </p>
                            </div>
                            <p class="text-xs text-[#5c6570] tabular-nums sm:text-right">
                                {{ trans_choice('forum.search.thread_stats', (int) $resultThread->reply_count, ['count' => number_format((int) $resultThread->reply_count)]) }}
                            </p>
                        </li>
                    @endforeach
                </ol>

                @include('forum.partials.thread_pagination', ['threads' => $results, 'position' => 'bottom'])
            @endif

            @if($activeForum)
                <p class="mt-4 text-sm"><a class="font-semibold text-[#8f3f1f] hover:underline" href="{{ $withoutQuery(['in', 'forum']) }}">{{ __('forum.search.search_everywhere') }}</a></p>
            @endif
        @endif
    </div>
@endsection
