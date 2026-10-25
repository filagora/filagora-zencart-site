@extends('layouts.main')

@section('title', \App\Support\LegacyTextDecoder::decode((string) $forum->name))
@section('main_class', 'bg-white')
@section('meta_description', $metaDescription ?? '')
@section('canonical', $canonicalUrl ?? '')

@section('content')
    @php
        $decodeLegacyText = static fn (?string $value): string => \App\Support\LegacyTextDecoder::decode($value);
        $renderLegacyDescription = static fn (?string $value): string => \App\Support\LegacyTextDecoder::renderDescription($value);

        $canManageSubscriptions = auth()->check()
            && auth()->user()?->hasVerifiedEmail();
        $canReplyFromForumPage = $canPostThread
            && auth()->check()
            && auth()->user()?->hasVerifiedEmail();
        $threadActionButtonClass = 'inline-flex items-center rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $threadActionCompactClass = 'inline-flex items-center rounded-control border border-[#d8dce2] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $threadPrimaryActionClass = 'inline-flex items-center rounded-control border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $threadPinBadgeClass = 'inline-flex items-center rounded-pill border border-[#933c1f] bg-[#f7e8df] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.03em] text-[#933c1f]';
        $threadStatusClass = 'mb-3 rounded-surface border border-[#b9d6bd] bg-[#f1fbf2] px-3 py-2 text-sm text-[#1f5130]';
        $threadErrorClass = 'mb-3 rounded-surface border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]';
    @endphp

    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
    @include('forum.partials.unverified_banner')
    @include('forum.partials.header_nav')

    @include('forum.partials.moderation_queue')

    <div class="mb-3 text-sm text-[#5c6570]">
        <a class="text-[#8f3f1f] hover:underline" href="{{ route('forums.index') }}">Forums</a>
        / {{ $decodeLegacyText($forum->group?->name ?? 'Group') }}
    </div>

    <header class="mb-4 border border-[#d8dce2] bg-white p-4">
        <div class="flex flex-col items-start justify-between gap-3 lg:flex-row lg:items-center">
            <div>
                <h1 class="text-3xl font-semibold text-[#0f2133]">{{ $decodeLegacyText($forum->name) }}</h1>
                @if($forum->description)
                    <p class="mt-1 text-sm text-[#5c6570]">{!! $renderLegacyDescription($forum->description) !!}</p>
                @endif
            </div>
            <div class="inline-flex flex-wrap items-center gap-2">
                @if($canReplyFromForumPage)
                    <a href="{{ route('threads.create.form', $forum) }}" class="{{ $threadPrimaryActionClass }}">Start New Thread</a>
                @elseif(auth()->check() && !auth()->user()?->hasVerifiedEmail())
                    <a
                        href="{{ route('verification.notice', ['intended' => request()->getRequestUri()]) }}"
                        class="{{ $threadActionButtonClass }}"
                    >Verify email to start thread</a>
                @elseif(auth()->check() && !$canPostThread)
                    <span class="{{ $threadPinBadgeClass }}">Posting is disabled in this forum.</span>
                @else
                    <button type="button" class="{{ $threadPrimaryActionClass }}" data-auth-modal-url="/login">Start New Thread</button>
                @endif
            </div>
        </div>
    </header>

    <div class="mb-6">
        @include('forum.partials.search_bar', ['searchForum' => $forum])
    </div>

    @if($canHideResolvedBugThreads ?? false)
        <form method="post" action="{{ route('profile.hide-resolved-bug-threads.update') }}" class="mb-4 flex items-center justify-end">
            @csrf
            <input type="hidden" name="hidden" value="{{ ($hideResolvedBugThreads ?? false) ? '0' : '1' }}">
            <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
            <button type="submit" class="inline-flex items-center gap-2 rounded-control border px-3 py-1.5 text-xs font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f] {{ ($hideResolvedBugThreads ?? false) ? 'border-[#933c1f] bg-[#933c1f] text-white hover:bg-[#7f3219]' : 'border-[#d8dce2] bg-white text-[#5c6570] hover:border-[#b9a68d] hover:text-[#8f3f1f]' }}">
                <span aria-hidden="true">{{ ($hideResolvedBugThreads ?? false) ? '☑' : '☐' }}</span>
                Hide threads with all bug reports resolved
            </button>
        </form>
    @endif

    @if($threadRequiresApproval || $replyRequiresApproval)
        <div class="{{ $threadErrorClass }}" role="status" aria-live="polite">
            <strong>Moderated forum.</strong>
            @if($threadRequiresApproval && $replyRequiresApproval)
                New threads and replies are reviewed by staff before they appear publicly.
            @elseif($threadRequiresApproval)
                New threads are reviewed by staff before they appear publicly.
            @else
                Replies are reviewed by staff before they appear publicly.
            @endif
        </div>
    @endif

    @if(in_array($forum->visibility?->value, ['paid_only', 'paid_or_author'], true))
        <div class="mb-4 border border-[#d8dce2] bg-[#f8f6f1] px-4 py-3 text-sm text-[#5c6570]">
            This forum may require an active membership for full access.
            <a href="{{ route('billing.index') }}" class="font-semibold text-[#8f3f1f] hover:underline">Manage memberships</a>
        </div>
    @endif

    @if(session('thread_status'))
        <div class="{{ $threadStatusClass }}" role="status" aria-live="polite">{{ session('thread_status') }}</div>
    @endif
    @if($errors->has('reason'))
        <div class="{{ $threadErrorClass }}" role="alert" aria-live="assertive">{{ $errors->first('reason') }}</div>
    @endif

    @include('forum.partials.thread_pagination', ['threads' => $threads, 'position' => 'top'])
    @include('forum.partials.thread_list', [
        'threads' => $threads,
        'subscribedThreadIds' => $subscribedThreadIds,
        'threadPostsPerPage' => $threadPostsPerPage,
        'showForumBadge' => false,
        'showModerationActions' => $showModerationActions ?? false,
        'emptyStateTitle' => 'No threads yet in this forum.',
    ])

    @include('forum.partials.thread_pagination', ['threads' => $threads, 'position' => 'bottom'])
    @if(config('features.forum_totals_widget'))
        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div class="border border-[#d8dce2] bg-white p-3">
                <div class="text-xs font-semibold uppercase tracking-[0.04em] text-[#5c6570]">Threads</div>
                <div class="mt-1 text-2xl font-semibold text-[#0f2133]">{{ number_format((int) $totals['threads']) }}</div>
            </div>
            <div class="border border-[#d8dce2] bg-white p-3">
                <div class="text-xs font-semibold uppercase tracking-[0.04em] text-[#5c6570]">Posts</div>
                <div class="mt-1 text-2xl font-semibold text-[#0f2133]">{{ number_format((int) $totals['posts']) }}</div>
            </div>
        </div>
    @endif
    </div>

    @if($showModerationActions ?? false)
        @include('forum.partials.relocate_dialog')
    @endif
@endsection
