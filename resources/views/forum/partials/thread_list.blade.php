@php
    $decodeLegacyText = static fn (?string $value): string => \App\Support\LegacyTextDecoder::decode($value);

    $showForumBadge = $showForumBadge ?? true;
    $showModerationActions = $showModerationActions ?? false;
    $showSubscribedIndicator = $showSubscribedIndicator ?? false;
@endphp

<section class="overflow-hidden border border-[#d8dce2] bg-white">
    @foreach($threads as $thread)
        @php
            $postCount = (int) ($thread->posts_count ?? 0);
            $replyCount = max(0, $postCount - 1);
            $author = $thread->author;
            $lastPostAuthor = $thread->lastPostAuthor ?? $author;
            $authorName = $author?->username ?? $author?->name ?? $author?->email ?? 'Unknown';
            $lastPostAuthorName = $lastPostAuthor?->username ?? $lastPostAuthor?->name ?? $lastPostAuthor?->email ?? 'Unknown';
            $userPostsCount = (int) ($thread->user_posts_count ?? 0);
            $isUnreadForUser = (bool) ($thread->is_unread_for_user ?? false);
            $hasVisibleAttachments = (bool) ($thread->has_visible_attachments ?? false);
            /** @var \Illuminate\Database\Eloquent\Collection<int, \Filagora\Forums\Models\PostAttachment> $listingAttachments */
            $listingAttachments = $thread->relationLoaded('listingAttachments')
                ? $thread->getRelation('listingAttachments')
                : new \Illuminate\Database\Eloquent\Collection();
            $visibleAttachmentsCount = (int) ($thread->visible_attachments_count ?? $listingAttachments->count());
            $firstPostId = (int) ($thread->posts_min_id ?? 0);
            $latestPostId = (int) ($thread->posts_max_id ?? 0);
            $userLatestPostAt = filled($thread->user_posts_latest_at ?? null)
                ? \Illuminate\Support\Carbon::parse($thread->user_posts_latest_at)
                : null;
            $userPostsIndicatorLabel = $userPostsCount === 1
                ? 'You have 1 post in this thread.'
                : 'You have '.$userPostsCount.' posts in this thread.';
            $userPostsIndicatorTitle = $userLatestPostAt instanceof \Illuminate\Support\Carbon
                ? $userPostsIndicatorLabel.' Your latest post was on '.\App\Support\LocalTime::format($userLatestPostAt).'.'
                : $userPostsIndicatorLabel;
            $latestPostPage = max(1, (int) ceil($postCount / max(1, (int) ($threadPostsPerPage ?? 20))));
            $firstPostUrl = $firstPostId > 0
                ? route('threads.show', ['thread' => $thread, 'page' => 1]).'#post-'.$firstPostId
                : route('threads.show', $thread);
            $lastPostUrl = $latestPostId > 0
                ? route('threads.show', ['thread' => $thread, 'page' => $latestPostPage]).'#post-'.$latestPostId
                : route('threads.show', $thread);
            $returnTo = request()->getRequestUri();
            $latestBugReportStatus = $thread->latest_bug_report_status ?? null;
            $bugReportIconClass = match($latestBugReportStatus) {
                \App\BugReportStatus::REPORTED => 'border-[#933c1f] text-[#933c1f]',
                \App\BugReportStatus::CONFIRMED => 'border-[#8f7a1f] text-[#8f7a1f]',
                \App\BugReportStatus::COMPLETED => 'border-[#1f8f4a] text-[#1f8f4a]',
                \App\BugReportStatus::FEATURE_REQUEST => 'border-[#1f4a8f] text-[#1f4a8f]',
                \App\BugReportStatus::WONT_FIX => 'border-[#5c6570] text-[#5c6570]',
                default => '',
            };
            $bugReportIconTitle = $latestBugReportStatus instanceof \App\BugReportStatus
                ? 'Bug status: '.$latestBugReportStatus->label()
                : null;
            $isSubscribed = in_array($thread->id, $subscribedThreadIds ?? [], true);
        @endphp
        <article class="border-t border-[#d8dce2] first:border-t-0 {{ $thread->is_pinned ? 'bg-[#fff8f3]' : 'bg-white' }}" data-thread-id="{{ $thread->id }}">
            <div class="grid gap-2 px-3 py-2.5 md:grid-cols-[minmax(0,1.9fr)_7rem_15rem] md:items-start md:gap-4">
                <div class="min-w-0">
                    <div class="flex items-start gap-3">
                        <span
                            class="relative mt-1 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-control border {{ $isUnreadForUser ? 'border-[#9aa9bc] bg-[linear-gradient(180deg,#ffffff_0%,#e4ebf3_100%)] text-[#5d6876] shadow-[inset_0_1px_0_rgba(255,255,255,0.92)]' : 'border-[#c9c2ba] bg-[linear-gradient(180deg,#f3f1ee_0%,#ddd8d2_100%)] text-[#8f8a83]' }}"
                            @if(auth()->check())
                                data-thread-read-state="{{ $isUnreadForUser ? 'unread' : 'read' }}"
                            @endif
                            aria-hidden="true"
                        >
                            <svg viewBox="0 0 20 20" fill="none" class="h-4.5 w-4.5" stroke="currentColor" stroke-width="1.5">
                                <rect x="2.5" y="4" width="15" height="12" rx="2"></rect>
                                <path d="M3.5 5l6.5 5 6.5-5"></path>
                            </svg>
                            @if($userPostsCount > 0)
                                <span
                                    class="absolute -bottom-1 -right-1 inline-flex h-4.5 w-4.5 items-center justify-center rounded-pill border border-[#3e8d43] bg-[#4cae4f] text-white shadow-sm"
                                    title="{{ $userPostsIndicatorTitle }}"
                                    aria-label="{{ $userPostsIndicatorTitle }}"
                                    data-thread-user-posts-indicator
                                >
                                    <svg viewBox="0 0 20 20" fill="none" class="h-3 w-3" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M12.5 5.5L7 10l5.5 4.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        <path d="M7.5 10h8" stroke-linecap="round"></path>
                                    </svg>
                                </span>
                            @endif
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="inline-flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                                @if($thread->is_pinned)
                                    <span class="text-[11px] font-semibold text-[#8f3f1f]">Sticky</span>
                                @endif
                                @if($thread->is_locked)
                                    <span class="text-[11px] font-semibold text-[#8f3f1f]">Locked</span>
                                @endif
                                @if($thread->isPending())
                                    <span class="text-[11px] font-semibold text-[#8f3f1f]">Pending Review</span>
                                @elseif($thread->isRejected())
                                    <span class="text-[11px] font-semibold text-[#8f3f1f]">Rejected</span>
                                @endif

                                <a class="min-w-0 text-[15px] leading-tight text-[#1a5fd0] hover:underline {{ $isUnreadForUser ? 'font-bold' : 'font-normal' }}" href="{{ $firstPostUrl }}">
                                    {{ $decodeLegacyText($thread->title) }}
                                </a>
                            </div>

                            <p class="mt-0.5 text-xs leading-tight text-[#5f6772]">
                                Started by
                                @if($author?->id)
                                    <a class="text-[#4b5159] hover:underline" href="{{ route('members.show', ['user' => $author->id]) }}">{{ $authorName }}</a>
                                @else
                                    {{ $authorName }}
                                @endif
                                , {{ \App\Support\LocalTime::format($thread->created_at) }}
                            </p>

                            <div class="mt-1 inline-flex flex-wrap items-center gap-2 text-[11px] leading-4 text-[#69717d]">
                                @if($userLatestPostAt instanceof \Illuminate\Support\Carbon)
                                    <span class="text-[#5f6772]">
                                        Your last post:
                                        <span class="text-[#4b5159]">{{ \App\Support\LocalTime::format($userLatestPostAt) }}</span>
                                    </span>
                                @endif

                                @if($isSubscribed && $showSubscribedIndicator)
                                    <span
                                        class="inline-flex h-5 w-5 items-center justify-center rounded-pill border border-[#1a5fd0] bg-white text-[#1a5fd0] shadow-sm"
                                        title="You are subscribed to this thread."
                                        aria-label="You are subscribed to this thread."
                                        data-thread-subscribed-indicator
                                    >
                                        <svg viewBox="0 0 20 20" fill="none" class="h-3.5 w-3.5" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                            <path d="M10 3.5c-2 0-3.5 1.6-3.5 3.6v2.4c0 .6-.2 1.2-.6 1.7L5 12.5h10l-.9-1.3c-.4-.5-.6-1.1-.6-1.7V7.1c0-2-1.5-3.6-3.5-3.6z" stroke-linecap="round" stroke-linejoin="round"></path>
                                            <path d="M8.5 14.5a1.5 1.5 0 0 0 3 0" stroke-linecap="round"></path>
                                        </svg>
                                    </span>
                                @endif

                                @if($hasVisibleAttachments)
                                    <a
                                        href="{{ route('legacy.attachments.index', $thread) }}"
                                        class="inline-flex h-5 w-5 items-center justify-center rounded-pill border border-[#7a746d] bg-white text-[#5f6772] shadow-sm transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                                        title="{{ $visibleAttachmentsCount === 1 ? 'This thread has 1 attachment.' : 'This thread has '.$visibleAttachmentsCount.' attachments.' }}"
                                        aria-label="{{ $visibleAttachmentsCount === 1 ? 'This thread has 1 attachment.' : 'This thread has '.$visibleAttachmentsCount.' attachments.' }}"
                                        data-thread-attachments-indicator
                                        data-thread-attachments-popup
                                    >
                                        <span class="sr-only">Show thread attachments</span>
                                        <svg viewBox="0 0 20 20" fill="none" class="h-3.5 w-3.5" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="M7.5 8.5v5a3 3 0 0 0 6 0V6.5a2 2 0 0 0-4 0v6.5a1 1 0 0 0 2 0V8" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                @endif

                                @if($bugReportIconTitle)
                                    <a
                                        href="{{ route('threads.show', $thread) }}#bug-reports"
                                        class="inline-flex h-5 w-5 items-center justify-center rounded-pill border bg-white shadow-sm transition {{ $bugReportIconClass }}"
                                        title="{{ $bugReportIconTitle }}"
                                        aria-label="{{ $bugReportIconTitle }}"
                                        data-thread-bug-report-indicator
                                    >
                                        <span class="sr-only">{{ $bugReportIconTitle }}</span>
                                        <svg viewBox="0 0 20 20" fill="none" class="h-3.5 w-3.5" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                            <ellipse cx="10" cy="11" rx="4" ry="5"></ellipse>
                                            <path d="M10 6V4M7 8L4 6M13 8l3-2M6 11H3M17 11h-3M7 14l-3 2M13 14l3 2" stroke-linecap="round"></path>
                                        </svg>
                                    </a>
                                @endif
                            </div>

                            <div class="mt-1 inline-flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] leading-4 text-[#69717d]">
                                @if($showForumBadge && $thread->forum)
                                    <a class="hover:underline" href="{{ route('forums.show', $thread->forum) }}">{{ $decodeLegacyText($thread->forum->name) }}</a>
                                @endif

                                @if($showModerationActions)
                                        <button
                                            type="button"
                                            class="hover:underline"
                                            data-relocate-open="thread-move"
                                            data-thread-id="{{ $thread->id }}"
                                            data-forum-id="{{ $thread->forum_id }}"
                                            data-thread-title="{{ $decodeLegacyText($thread->title) }}"
                                            data-forum-name="{{ $decodeLegacyText($forum->name ?? ($thread->forum->name ?? '')) }}"
                                            data-thread-moderate-url="{{ route('threads.moderate', $thread) }}"
                                        >Move</button>
                                        <form method="post" action="{{ route('threads.moderate', $thread) }}" data-thread-moderation-form data-thread-rename-title-default="{{ $decodeLegacyText($thread->title) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="rename">
                                            <input type="hidden" name="title" value="" data-thread-rename-title>
                                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                                            <button type="submit" class="hover:underline">Rename</button>
                                        </form>
                                        <form method="post" action="{{ route('threads.moderate', $thread) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="{{ $thread->is_pinned ? 'unpin' : 'pin' }}">
                                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                                            <button type="submit" class="hover:underline">{{ $thread->is_pinned ? 'Unpin' : 'Pin' }}</button>
                                        </form>
                                        <form method="post" action="{{ route('threads.moderate', $thread) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="{{ $thread->is_locked ? 'unlock' : 'lock' }}">
                                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                                            <button type="submit" class="hover:underline">{{ $thread->is_locked ? 'Unlock' : 'Lock' }}</button>
                                        </form>
                                        @if(!$thread->isApproved())
                                            <form method="post" action="{{ route('threads.moderate', $thread) }}">
                                                @csrf
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="return_to" value="{{ $returnTo }}">
                                                <button type="submit" class="hover:underline">Approve</button>
                                            </form>
                                            @include('forum.partials.thread_delete_button', [
                                                'thread' => $thread,
                                                'returnTo' => $returnTo,
                                                'buttonClass' => 'hover:underline',
                                                'label' => __('forum.thread_delete.reject_open'),
                                            ])
                                        @endif
                                        <form method="post" action="{{ route('threads.moderate', $thread) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="mark_unread">
                                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                                            <button type="submit" class="hover:underline">Mark unread</button>
                                        </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-[#ddd8d1] pt-2 text-right text-xs leading-5 text-[#4b5159] md:border-t-0 md:pt-0">
                    <div>Replies: {{ number_format($replyCount) }}</div>
                    <div>Posts: {{ number_format($postCount) }}</div>
                    <div>Views: {{ number_format((int) $thread->view_count) }}</div>
                </div>

                <div class="flex items-start justify-end gap-1.5 border-t border-[#ddd8d1] pt-2 text-xs leading-5 text-[#4b5159] md:justify-start md:border-t-0 md:pt-0">
                    <a
                        href="{{ $lastPostUrl }}"
                        class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-pill border border-[#7a746d] bg-white text-[#5f6772] shadow-sm transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                        title="Go to latest post"
                        aria-label="Go to latest post in {{ $decodeLegacyText($thread->title) }}"
                        data-thread-latest-post-link
                    >
                        <svg viewBox="0 0 20 20" fill="none" class="h-3.5 w-3.5" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M7 4l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </a>
                    <div class="min-w-0">
                        <p class="truncate">
                            @if($lastPostAuthor?->id)
                                <a class="text-[#1a5fd0] hover:underline" href="{{ route('members.show', ['user' => $lastPostAuthor->id]) }}">{{ $lastPostAuthorName }}</a>
                            @else
                                {{ $lastPostAuthorName }}
                            @endif
                        </p>
                        <p class="text-[#5f6772]">{{ \App\Support\LocalTime::format($thread->last_post_at ?? $thread->updated_at) }}</p>
                    </div>
                </div>
            </div>
        </article>
    @endforeach

    @if($threads->count() === 0)
        <article class="bg-white px-4 py-4">
            <h2 class="text-lg font-semibold text-[#0f2133]">{{ $emptyStateTitle ?? 'No threads found.' }}</h2>
            @if(!empty($emptyStateDescription))
                <p class="mt-1 text-sm text-[#5c6570]">{{ $emptyStateDescription }}</p>
            @endif
        </article>
    @endif
</section>
