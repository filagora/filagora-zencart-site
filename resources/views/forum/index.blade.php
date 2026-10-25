@extends('layouts.main')

@section('title', 'Forums')
@section('main_class', 'bg-white')

@section('content')
    @php
        $decodeLegacyText = static fn (?string $value): string => \App\Support\LegacyTextDecoder::decode($value);
        $renderLegacyDescription = static fn (?string $value): string => \App\Support\LegacyTextDecoder::renderDescription($value);
    @endphp

    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
    @include('partials.site_guest_intro')
    @include('forum.partials.unverified_banner')
    @include('forum.partials.header_nav')

    <header class="mb-5">
        <h1 class="ui-page-title">Support Forums</h1>
        <p class="ui-page-subtitle mt-2">Browse all active categories and latest discussions.</p>
    </header>

    <div class="mb-6">
        @include('forum.partials.search_bar')
    </div>

    @include('forum.partials.moderation_queue')

    @foreach($groupRows as $row)
        @php
            $group = $row['group'];
            $forums = $row['forums'];
        @endphp
        <section class="mb-5 overflow-hidden border border-[#d8dce2] bg-white">
            <header class="ui-surface-panel border-b border-[#d8dce2] px-4 py-3">
                <div>
                    <h2 class="ui-content-title text-[1.3rem]">{{ $decodeLegacyText($group->name) }}</h2>
                    @if($group->description)
                        <p class="ui-small-meta mt-1">{!! $renderLegacyDescription($group->description) !!}</p>
                    @endif
                </div>
            </header>

            <div class="divide-y divide-[#d8dce2] bg-white">
                @foreach($forums as $forum)
                    @php
                        $meta = $forumMeta[(int) $forum->id] ?? ['threadCount' => 0, 'postCount' => 0, 'lastPost' => null];
                        $lastPost = $meta['lastPost'] ?? null;
                    @endphp
                    <article class="grid gap-3 px-4 py-3 lg:grid-cols-[1.5fr_auto_1fr] lg:items-start">
                        <div class="min-w-0">
                            <h3 class="ui-card-title">
                                <a class="hover:text-[#8f3f1f]" href="{{ route('forums.show', $forum) }}">{{ $decodeLegacyText($forum->name) }}</a>
                            </h3>
                            @if($forum->description)
                                <p class="ui-small-meta mt-1">{!! $renderLegacyDescription($forum->description) !!}</p>
                            @endif
                        </div>
                        <div class="inline-flex gap-3 text-sm text-[#5c6570] lg:flex-col lg:gap-1">
                            <div>Threads: {{ number_format((int) $meta['threadCount']) }}</div>
                            <div>Posts: {{ number_format((int) $meta['postCount']) }}</div>
                        </div>
                        <div class="ui-small-meta text-sm">
                            @if(is_array($lastPost))
                                <p class="ui-card-title">
                                    <a class="hover:text-[#8f3f1f]" href="{{ route('threads.show', $lastPost['threadId']) }}#post-{{ $lastPost['postId'] }}">{{ $decodeLegacyText((string) $lastPost['title']) }}</a>
                                </p>
                                <p class="mt-1">
                                    by
                                    @if(!empty($lastPost['authorId']))
                                        <a class="text-[#8f3f1f] hover:underline" href="{{ route('members.show', ['user' => $lastPost['authorId']]) }}">{{ $lastPost['author'] }}</a>
                                    @else
                                        {{ $lastPost['author'] }}
                                    @endif
                                    <br>
                                    {{ $lastPost['at'] ? \App\Support\LocalTime::format(\Carbon\Carbon::parse($lastPost['at'])) : 'Unknown' }}
                                </p>
                            @else
                                <p>No posts yet</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach

    @if(empty($groupRows))
        <p>No forums are currently available.</p>
    @endif

        {{--  Spamtrap Honeypot link (created with/sends-to projecthoneypot.com) --}}
        <a href="https://www.zen-cart.com/priestlyambiguous.php"><div style="height: 0px; width: 0px;"></div></a>

    </div>
@endsection
