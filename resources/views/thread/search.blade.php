@extends('layouts.main')

@section('title', __('forum.search.thread_page_title', ['thread' => \App\Support\LegacyTextDecoder::decode((string) $thread->title)]))
@section('main_class', 'bg-white')

@section('content')
    @php
        /** @var \Filagora\Forums\Search\ForumSearchCriteria $criteria */
        $decodeLegacyText = static fn (?string $value): string => \App\Support\LegacyTextDecoder::decode($value);
        $decodedThreadTitle = $decodeLegacyText($thread->title);
        $badgeClass = 'inline-flex items-center rounded-pill border border-[#933c1f] bg-[#f7e8df] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.03em] text-[#933c1f]';
    @endphp

    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
        @include('forum.partials.header_nav')

        <div class="mt-3">
            @include('forum.partials.search_bar', [
                'searchThread' => $thread,
                'searchForum' => $thread->forum,
                'searchCriteria' => $criteria,
                'searchAuthorName' => $authorName,
            ])
        </div>

        <div class="mb-3 text-sm text-[#5c6570] [overflow-wrap:anywhere]">
            <a class="text-[#8f3f1f] hover:underline" href="{{ route('forums.index') }}">Forums</a>
            / <a class="text-[#8f3f1f] hover:underline" href="{{ route('forums.show', $thread->forum) }}">{{ $decodeLegacyText($thread->forum?->name) }}</a>
            / <a class="text-[#8f3f1f] hover:underline" href="{{ route('threads.show', $thread) }}">{{ $decodedThreadTitle }}</a>
            / {{ __('forum.search.submit') }}
        </div>

        @if($authorNotFound)
            <p class="mb-4 rounded-surface border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]" role="alert">{{ __('forum.search.author_not_found', ['name' => $authorName]) }}</p>
        @elseif($results === null)
            <p class="rounded-surface border border-[#d8dce2] bg-[#faf8f4] px-4 py-6 text-sm text-[#5c6570]">{{ __('forum.search.thread_prompt') }}</p>
        @else
            @php
                $heading = trans_choice('forum.search.thread_results_heading', $results->total(), ['count' => number_format($results->total())]);
            @endphp
            <header class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h1 class="text-2xl font-semibold text-[#0f2133]">
                    {{ $criteria->query !== '' ? __('forum.search.results_for', ['heading' => $heading, 'query' => $criteria->query]) : $heading }}
                </h1>
                <p class="text-sm">
                    <span class="text-[#5c6570]">{{ __('forum.search.sort_label') }}: {{ __('forum.search.post_sort.' . $criteria->postSort) }}</span> ·
                    <a class="font-semibold text-[#8f3f1f] hover:underline" href="{{ route('threads.show', $thread) }}">{{ __('forum.search.back_to_thread') }}</a>
                    @if($criteria->query !== '')
                        · <a class="font-semibold text-[#8f3f1f] hover:underline" href="{{ route('forums.search', ['q' => $criteria->query]) }}">{{ __('forum.search.search_everywhere') }}</a>
                    @endif
                </p>
            </header>

            @if($results->total() > 0)
                <ol class="border border-[#d8dce2]">
                    @foreach($results as $matchedPost)
                        @php
                            $postUrl = route('threads.show', array_filter([
                                'thread' => $thread,
                                'page' => $matchedPost->search_page > 1 ? $matchedPost->search_page : null,
                            ])) . '#post-' . $matchedPost->id;
                        @endphp
                        <li class="grid gap-x-4 gap-y-1 border-t border-[#e2e6ea] px-4 py-3 first:border-t-0 sm:grid-cols-[minmax(0,1fr)_auto]">
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-[#5c6570]">
                                <a class="text-sm font-semibold text-[#8f3f1f] hover:underline" href="{{ $postUrl }}">{{ __('forum.search.post_number', ['number' => $matchedPost->search_post_number]) }}</a>
                                <span>{{ $matchedPost->author?->username ?: ($matchedPost->author?->name ?: __('forum.search.unknown_author')) }} · {{ \App\Support\LocalTime::format($matchedPost->created_at) }}</span>
                                @if($matchedPost->is_deleted || $matchedPost->moderation_status !== \Filagora\Forums\Enums\ContentModerationStatus::APPROVED)
                                    <span class="{{ $badgeClass }}">{{ __('forum.search.hidden_post_badge') }}</span>
                                @endif
                            </p>
                            <a class="text-xs font-semibold text-[#8f3f1f] hover:underline sm:text-right" href="{{ $postUrl }}">{{ __('forum.search.go_to_post') }} &rarr;</a>
                            <p class="text-sm text-[#304458] sm:col-span-2 [overflow-wrap:anywhere]">{{ \App\Support\SearchSnippet::highlight(app(\App\Support\FormattedContentRenderer::class)->plainText($matchedPost->body), $criteria->query) }}</p>
                        </li>
                    @endforeach
                </ol>

                @include('forum.partials.thread_pagination', ['threads' => $results, 'position' => 'bottom'])
            @endif
        @endif
    </div>
@endsection
