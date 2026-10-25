@extends('layouts.main')

@section('title', \App\Support\LegacyTextDecoder::decode($thread->title))
@section('main_class', 'bg-white')
@section('meta_description', $metaDescription ?? '')
@section('canonical', $canonicalUrl ?? '')

@section('content')
    @php
        $contentRenderer = app(\App\Support\FormattedContentRenderer::class);
        $decodedThreadTitle = \App\Support\LegacyTextDecoder::decode($thread->title);
        $decodedForumName = \App\Support\LegacyTextDecoder::decode($thread->forum->name);
    @endphp
    @php
        $showReplyComposer = $errors->reply->has('body') || (string) old('body') !== '';
        $canManageSubscriptions = auth()->check()
            && auth()->user()?->hasVerifiedEmail();
        $threadActionButtonClass = 'inline-flex items-center rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $threadPrimaryActionClass = 'inline-flex items-center rounded-control border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $threadPinBadgeClass = 'inline-flex items-center rounded-pill border border-[#933c1f] bg-[#f7e8df] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.03em] text-[#933c1f]';
        $menuItemClass = 'block w-full cursor-pointer rounded-control px-2 py-1.5 text-left text-sm font-medium text-[#0f2133] transition hover:bg-[#f7e8df] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[#8f3f1f]';
        $menuHeadingClass = 'px-2 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-[0.04em] text-[#5c6570]';
        $menuSummaryClass = 'inline-flex cursor-pointer list-none items-center rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f] [&::-webkit-details-marker]:hidden';
        $threadStatusClass = 'mb-3 border border-[#b9d6bd] bg-[#f1fbf2] px-3 py-2 text-sm text-[#1f5130]';
        $threadErrorClass = 'mb-3 border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]';
        $replySmileyButtonClass = 'inline-flex h-10 w-10 items-center justify-center rounded-control border border-[#cabca8] bg-white text-base transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $replySecondaryActionClass = 'inline-flex min-h-10 items-center justify-center rounded-control border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
    @endphp

    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
    @include('forum.partials.unverified_banner')
    @include('forum.partials.header_nav')
    @include('forum.partials.moderation_queue')

    <div class="mb-3 text-sm text-[#5c6570]">
        <a class="text-[#8f3f1f] hover:underline" href="{{ route('forums.index') }}">Forums</a>
        / <a class="text-[#8f3f1f] hover:underline" href="{{ route('forums.show', $thread->forum) }}">{{ $decodedForumName }}</a>
        / {{ $decodedThreadTitle }}
    </div>

    <header class="mb-4 border border-[#d8dce2] bg-white p-4">
        <div class="flex flex-col items-start justify-between gap-3 lg:flex-row lg:items-center">
            <div>
                <h1 class="text-3xl font-semibold text-[#0f2133]">{{ $decodedThreadTitle }}</h1>
                <div class="mt-2 inline-flex flex-wrap items-center gap-2">
                    @if($thread->is_pinned)
                        <span class="{{ $threadPinBadgeClass }}">Sticky</span>
                    @endif
                    @if($thread->is_locked)
                        <span class="{{ $threadPinBadgeClass }}">Locked</span>
                    @endif
                    @if($thread->isPending())
                        <span class="{{ $threadPinBadgeClass }}">Pending Review</span>
                    @elseif($thread->moderation_status === \Filagora\Forums\Enums\ContentModerationStatus::REJECTED_FINAL)
                        <span class="{{ $threadPinBadgeClass }}">Deleted</span>
                    @elseif($thread->isRejected())
                        <span class="{{ $threadPinBadgeClass }}">Rejected</span>
                    @endif
                </div>
                <p class="mt-1 text-xs text-[#5c6570]">Views: {{ number_format((int) $thread->view_count) }}</p>
            </div>
            <div class="inline-flex flex-wrap items-center gap-2">
                @if($canReply)
                    <a href="#reply" class="{{ $threadPrimaryActionClass }}" data-thread-reply>Reply</a>
                @elseif(!auth()->check())
                    <button type="button" class="{{ $threadPrimaryActionClass }}" data-auth-modal-url="/login">Reply</button>
                @endif
                @if($canModerateThread ?? false)
                    <details class="relative" data-mod-menu>
                        <summary class="{{ $menuSummaryClass }}">{{ __('forum.relocate.menu') }} <span aria-hidden="true" class="ml-1">▾</span></summary>
                        <div class="absolute right-0 z-[40] mt-1 w-64 border border-[#d8dce2] bg-white p-1 shadow-[0_16px_36px_rgba(0,0,0,0.18)]">
                            <p class="{{ $menuHeadingClass }}">Organize</p>
                            <button type="button" class="{{ $menuItemClass }}" data-relocate-open="thread-move">Move Thread&hellip;</button>
                            <form method="post" action="{{ route('threads.moderate', $thread) }}" data-thread-moderation-form data-thread-rename-title-default="{{ $decodedThreadTitle }}">
                                @csrf
                                <input type="hidden" name="action" value="rename">
                                <input type="hidden" name="title" value="" data-thread-rename-title>
                                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                                <button type="submit" class="{{ $menuItemClass }}">Rename Thread&hellip;</button>
                            </form>

                            <p class="{{ $menuHeadingClass }}">Visibility</p>
                            <form method="post" action="{{ route('threads.moderate', $thread) }}">
                                @csrf
                                <input type="hidden" name="action" value="{{ $thread->is_pinned ? 'unpin' : 'pin' }}">
                                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                                <button type="submit" class="{{ $menuItemClass }}">{{ $thread->is_pinned ? 'Unpin Thread' : 'Pin Thread' }}</button>
                            </form>
                            <form method="post" action="{{ route('threads.moderate', $thread) }}">
                                @csrf
                                <input type="hidden" name="action" value="{{ $thread->is_locked ? 'unlock' : 'lock' }}">
                                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                                <button type="submit" class="{{ $menuItemClass }}">{{ $thread->is_locked ? 'Unlock Thread' : 'Lock Thread' }}</button>
                            </form>
                            @if(!$thread->isApproved())
                                <form method="post" action="{{ route('threads.moderate', $thread) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                                    <button type="submit" class="{{ $menuItemClass }}">{{ $thread->isRejected() ? 'Restore Thread' : 'Approve Thread' }}</button>
                                </form>
                            @endif
                            @include('forum.partials.thread_delete_button', [
                                'thread' => $thread,
                                'buttonClass' => $menuItemClass,
                                'label' => $thread->isApproved() ? __('forum.thread_delete.open') : __('forum.thread_delete.reject_open'),
                            ])

                            <p class="{{ $menuHeadingClass }}">More</p>
                            <form method="post" action="{{ route('threads.bug-reports.store', $thread) }}" data-thread-moderation-form>
                                @csrf
                                <input type="hidden" name="action" value="report_bug">
                                <input type="hidden" name="description" value="" data-thread-moderation-reason>
                                <button type="submit" class="{{ $menuItemClass }}">Report as Bug&hellip;</button>
                            </form>
                            @unless($thread->poll)
                                <a href="{{ route('threads.poll.create', $thread) }}" class="{{ $menuItemClass }}">Add Poll</a>
                            @endunless
                            <button type="button" class="{{ $menuItemClass }}" data-select-posts-toggle>{{ __('forum.relocate.select.enter') }}&hellip;</button>
                        </div>
                    </details>
                @endif
            </div>
        </div>
        <div class="mt-3 flex flex-col items-start justify-between gap-2 border-t border-[#d8d1c8] pt-3 md:flex-row md:items-center">
            <div class="text-sm text-[#5c6570]">
                Results {{ number_format($posts->firstItem() ?? 0) }} to {{ number_format($posts->lastItem() ?? 0) }} of {{ number_format($postCount) }}
            </div>
            <div class="inline-flex items-center gap-2">
                @if($canManageSubscriptions)
                    @if($isSubscribed ?? false)
                        <form method="post" action="{{ route('threads.unsubscribe', $thread) }}">
                            @csrf
                            <button type="submit" class="{{ $threadActionButtonClass }}">Unsubscribe</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('threads.subscribe', $thread) }}">
                            @csrf
                            <button type="submit" class="{{ $threadActionButtonClass }}">Subscribe</button>
                        </form>
                    @endif
                @elseif(!auth()->check())
                    <button type="button" class="{{ $threadActionButtonClass }}" data-auth-modal-url="/login">Subscribe</button>
                @endif
            </div>
        </div>
    </header>

    <div class="mb-6">
        @include('forum.partials.search_bar', ['searchThread' => $thread, 'searchForum' => $thread->forum])
    </div>

    @if(in_array($thread->forum->visibility?->value, ['paid_only', 'paid_or_author'], true))
        <div class="mb-4 border border-[#d8dce2] bg-[#f8f6f1] px-4 py-3 text-sm text-[#5c6570]">
            This area may require an active membership for full access.
            <a href="{{ route('billing.index') }}" class="font-semibold text-[#8f3f1f] hover:underline">Manage memberships</a>
        </div>
    @endif

    @if(auth()->check() && !$canReply && !$thread->is_locked && $thread->isApproved() && filled($thread->forum->reply_entitlement_scope))
        <div class="mb-4 border border-[#d8dce2] bg-[#f8f6f1] px-4 py-3 text-sm text-[#5c6570]">
            Replying to other users' threads in this forum requires the <strong>{{ $thread->forum->reply_entitlement_scope }}</strong> entitlement.
            <a href="{{ route('billing.index') }}" class="font-semibold text-[#8f3f1f] hover:underline">Manage memberships</a>
        </div>
    @endif

    @if(session('thread_status'))
        <div class="{{ $threadStatusClass }}" role="status" aria-live="polite">{{ session('thread_status') }}</div>
    @endif
    @if($replyRequiresApproval && $thread->isApproved())
        <div class="{{ $threadErrorClass }}" role="status" aria-live="polite">
            <strong>Replies are moderated in this thread.</strong> New replies are reviewed by staff before they appear publicly.
        </div>
    @endif
    @if($thread->is_locked)
        <div class="{{ $threadErrorClass }}" role="status" aria-live="polite">
            This thread is locked. New replies are disabled.
        </div>
    @endif
    @if(!$thread->isApproved())
        <div class="{{ $threadErrorClass }}" role="status" aria-live="polite">
            @if($thread->isPending())
                This thread is pending staff approval. It is only visible to staff and the author.
            @else
                This thread has been rejected and is only visible to staff and the author.
            @endif
            @if($thread->moderation_reason)
                <br>
                <span>Reason: {{ $thread->moderation_reason }}</span>
            @endif
        </div>
    @endif

    @if($errors->has('reason'))
        <div class="{{ $threadErrorClass }}" role="alert" aria-live="assertive">{{ $errors->first('reason') }}</div>
    @endif

    @if(($canModerateThread ?? false) && (($moderationLogs?->isNotEmpty() ?? false) || ($postModerationLogs?->isNotEmpty() ?? false)))
        <details class="mb-4 border border-[#d8dce2] bg-white p-3">
            <summary>
                <strong>View Moderation History</strong>
                <span>
                    Threads: {{ (int) ($moderationLogs?->count() ?? 0) }},
                    Posts: {{ (int) ($postModerationLogs?->count() ?? 0) }}
                </span>
            </summary>

            @if($moderationLogs?->isNotEmpty() ?? false)
                <h2>Thread Moderation History</h2>
                <ul>
                    @foreach($moderationLogs as $log)
                        @php
                            $moderatorName = $log->moderator?->username
                                ?? $log->moderator?->name
                                ?? $log->moderator?->email
                                ?? 'System';
                        @endphp
                        <li>
                            <strong>{{ ucfirst((string) $log->action) }}</strong>
                            by {{ $moderatorName }}
                            on {{ $log->created_at ? \App\Support\LocalTime::format($log->created_at) : 'Unknown' }}
                            @if($log->reason)
                                <br>
                                <span>Reason: {{ $log->reason }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if($postModerationLogs?->isNotEmpty() ?? false)
                <h2>Post Moderation History</h2>
                <ul>
                    @foreach($postModerationLogs as $log)
                        @php
                            $moderatorName = $log->moderator?->username
                                ?? $log->moderator?->name
                                ?? $log->moderator?->email
                                ?? 'System';
                        @endphp
                        <li>
                            <strong>{{ ucfirst((string) $log->action) }}</strong>
                            post #{{ (int) $log->post_id }}
                            by {{ $moderatorName }}
                            on {{ $log->created_at ? \App\Support\LocalTime::format($log->created_at) : 'Unknown' }}
                            @if($log->reason)
                                <br>
                                <span>Reason: {{ $log->reason }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </details>
    @endif

    @if($bugReports?->isNotEmpty() ?? false)
        <section id="bug-reports" class="mb-4 border border-[#d8dce2] bg-white p-3">
            <h2>Bug Reports</h2>
            <ul class="grid gap-3">
                @foreach($bugReports as $bugReport)
                    @php
                        $bugReportStatusClass = match($bugReport->status) {
                            \App\BugReportStatus::REPORTED => 'inline-flex items-center rounded-pill border border-[#933c1f] bg-[#f7e8df] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.03em] text-[#933c1f]',
                            \App\BugReportStatus::CONFIRMED => 'inline-flex items-center rounded-pill border border-[#8f7a1f] bg-[#fbf6df] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.03em] text-[#8f7a1f]',
                            \App\BugReportStatus::COMPLETED => 'inline-flex items-center rounded-pill border border-[#1f8f4a] bg-[#dffbe8] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.03em] text-[#1f8f4a]',
                            \App\BugReportStatus::FEATURE_REQUEST => 'inline-flex items-center rounded-pill border border-[#1f4a8f] bg-[#dfe8fb] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.03em] text-[#1f4a8f]',
                            \App\BugReportStatus::WONT_FIX => 'inline-flex items-center rounded-pill border border-[#5c6570] bg-[#eceef0] px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.03em] text-[#5c6570]',
                        };
                        $bugReportedByName = $bugReport->reportedBy?->username ?? $bugReport->reportedBy?->name ?? 'Unknown';
                        $bugAssignedToName = $bugReport->assignedTo?->username ?? $bugReport->assignedTo?->name;
                    @endphp
                    <li>
                        <details class="border border-[#d8dce2] bg-[#f8f6f1] p-3" data-bug-report-details>
                            <summary class="flex cursor-pointer list-none flex-wrap items-center gap-2 [&::-webkit-details-marker]:hidden">
                                <span class="{{ $bugReportStatusClass }}">{{ $bugReport->status->label() }}</span>
                                <span class="text-xs text-[#5c6570]">Reported on {{ \App\Support\LocalTime::format($bugReport->created_at) }}</span>
                                @if($bugReport->description)
                                    <span class="text-sm text-[#0f2133]">{{ Illuminate\Support\Str::limit($bugReport->description, 80) }}</span>
                                @endif
                            </summary>
                            @if($bugReport->description)
                                <p class="mt-2 text-sm text-[#0f2133]">{{ $bugReport->description }}</p>
                            @endif
                            @if($bugReport->resolution_note)
                                <p class="mt-1 text-sm text-[#5c6570]">Note: {{ $bugReport->resolution_note }}</p>
                            @endif

                            @if($canModerateThread ?? false)
                                <p class="mt-1 text-xs text-[#5c6570]">Reported by {{ $bugReportedByName }} &middot; Assigned to: {{ $bugAssignedToName ?? 'Unassigned' }}</p>

                                <form method="post" action="{{ route('bug-reports.update', $bugReport) }}" class="mt-3 grid gap-2 md:grid-cols-[auto_auto_1fr_auto] md:items-end">
                                    @csrf
                                    <div>
                                        <label for="bug-report-status-{{ $bugReport->id }}" class="block text-xs font-semibold text-[#5c6570]">Status</label>
                                        <select id="bug-report-status-{{ $bugReport->id }}" name="status" class="rounded-control border border-[#d8dce2] bg-white px-2 py-1.5 text-sm text-[#0f2133]">
                                            @foreach(\App\BugReportStatus::cases() as $statusOption)
                                                <option value="{{ $statusOption->value }}" @selected($bugReport->status === $statusOption)>{{ $statusOption->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="bug-report-assignee-{{ $bugReport->id }}" class="block text-xs font-semibold text-[#5c6570]">Assign to</label>
                                        <select id="bug-report-assignee-{{ $bugReport->id }}" name="assigned_to_id" class="rounded-control border border-[#d8dce2] bg-white px-2 py-1.5 text-sm text-[#0f2133]">
                                            <option value="">Unassigned</option>
                                            @foreach($assignableStaff ?? [] as $staffMember)
                                                <option value="{{ $staffMember->id }}" @selected((int) $bugReport->assigned_to_id === (int) $staffMember->id)>{{ $staffMember->username ?? $staffMember->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="bug-report-note-{{ $bugReport->id }}" class="block text-xs font-semibold text-[#5c6570]">Resolution note</label>
                                        <input id="bug-report-note-{{ $bugReport->id }}" type="text" name="resolution_note" class="w-full rounded-control border border-[#d8dce2] bg-white px-2 py-1.5 text-sm text-[#0f2133]">
                                    </div>
                                    <button type="submit" class="{{ $threadActionButtonClass }}">Update</button>
                                </form>
                            @endif
                        </details>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($thread->poll)
        @include('thread.partials.poll', ['poll' => $thread->poll])
    @endif

    @include('thread.partials.post_pagination', ['posts' => $posts, 'position' => 'top'])

    @if($canReply)
    @php
        $replySmileys = [
            ['🙂', ':)', 'Insert smiley 🙂'],
            ['🙁', ':(', 'Insert smiley 🙁'],
            ['😉', ';)', 'Insert smiley 😉'],
            ['😄', ':D', 'Insert smiley 😄'],
            ['😛', ':P', 'Insert smiley 😛'],
            ['😮', ':o', 'Insert smiley 😮'],
            ['👍', ':thumbsup:', 'Insert smiley 👍'],
            ['👎', ':thumbsdown:', 'Insert smiley 👎'],
        ];
    @endphp
    <section id="reply" class="mt-4 border border-[#d8dce2] bg-white p-4" @if(!$showReplyComposer) hidden @endif>
        <h2 class="text-xl font-semibold text-[#0f2133]">Post Reply</h2>
            <form method="post" action="{{ route('threads.reply', $thread) }}" enctype="multipart/form-data" data-lock-on-submit>
                @csrf
                <p id="thread-reply-required-help" class="mt-2 text-sm text-[#5c6570]">Message is required.</p>
                <label for="reply-body" class="mt-2 inline-flex text-sm font-semibold text-[#5c6570]">Message</label>
                <div
                    class="mt-1 overflow-hidden border border-[#d8dce2] bg-white"
                    data-rich-editor data-rich-editor-target="reply-body" data-rich-editor-default="{{ $richComposerDefault ?? 'rich' }}"
                >
                    <x-markdown-toolbar target="reply-body" :classic="true" />
                    <div class="grid md:grid-cols-[minmax(0,1fr)_136px]">
                        <textarea
                            id="reply-body"
                            name="body"
                            class="min-h-[220px] w-full resize-y border-0 bg-transparent px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#8f3f1f] md:border-r md:border-[#d8dce2]"
                            required
                            aria-required="true"
                            aria-describedby="thread-reply-required-help{{ $errors->reply->has('body') ? ' thread-reply-body-error' : '' }}"
                            @if($errors->reply->has('body')) aria-invalid="true" @endif
                        >{{ old('body') }}</textarea>
                        <div class="grid grid-cols-3 content-start gap-2 border-t border-[#d8dce2] p-2 md:border-l md:border-t-0 md:border-[#d8dce2]">
                            @foreach($replySmileys as [$emoji, $legacyCode, $label])
                                <button
                                    class="{{ $replySmileyButtonClass }}"
                                    type="button"
                                    data-smiley="{{ $emoji }}"
                                    data-smiley-target="reply-body"
                                    aria-label="{{ $label }}"
                                    title="{{ $emoji }} ({{ $legacyCode }})"
                                >{{ $emoji }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
                <p class="text-sm text-[#5c6570]">Uses Markdown, with supported extensions for underline, alignment, color, size, font, spoilers, video, and attachments.</p>
                @if($errors->reply->has('body'))
                    <p id="thread-reply-body-error" class="text-sm text-[#dc3545]">{{ $errors->reply->first('body') }}</p>
                @endif

                <label for="reply-attachments" class="mt-2 inline-flex text-sm font-semibold text-[#5c6570]">Attachments (optional)</label>
                <input
                    class="mt-1 w-full rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                    id="reply-attachments"
                    type="file"
                    name="attachments[]"
                    multiple
                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.zip,.log"
                    aria-describedby="reply-attachments-help{{ $errors->reply->has('attachments') ? ' thread-reply-attachments-error' : '' }}"
                    @if($errors->reply->has('attachments')) aria-invalid="true" @endif
                >
                <p id="reply-attachments-help" class="text-sm text-[#5c6570]">Up to 5 files, 10MB each. Allowed: jpg, jpeg, png, gif, webp, pdf, txt, zip, log.</p>
                @if($errors->reply->has('attachments'))
                    <p id="thread-reply-attachments-error" class="text-sm text-[#dc3545]">{{ $errors->reply->first('attachments') }}</p>
                @endif

                @unless($isSubscribed ?? false)
                <label class="mt-3 flex items-center gap-3 rounded-surface border border-[#cabca8] bg-[#f7e8df] px-3.5 py-3 text-base font-bold text-[#0f2133]">
                    <input type="hidden" name="subscribe" value="0">
                    <input
                        class="h-5 w-5 shrink-0 rounded-control border-[#933c1f] text-[#933c1f] focus:ring-[#8f3f1f]"
                        type="checkbox"
                        name="subscribe"
                        value="1"
                        @checked((bool) old('subscribe', auth()->user()?->auto_subscribe_on_post ?? false))
                    >
                    <span>Subscribe to this thread</span>
                </label>
                @endunless

                <div class="mt-2 mb-4 flex flex-wrap items-center gap-2">
                    <button
                        class="{{ $replySecondaryActionClass }}"
                        data-markdown-trigger
                        type="button"
                        data-markdown-preview
                        data-markdown-source="reply-body"
                        data-markdown-preview-panel="reply-body-preview"
                        aria-label="Preview reply"
                    >Preview</button>
                    <button
                        type="button"
                        class="{{ $replySecondaryActionClass }}"
                        data-thread-reply-cancel
                        aria-label="Cancel reply"
                    >Cancel</button>
                </div>
                <section id="reply-body-preview" class="mt-3 mb-4 border border-[#d8dce2] bg-[#f8f6f1] p-3" hidden>
                    <h3 class="text-sm font-semibold text-[#304458]">Preview</h3>
                    <div class="forum-rendered-content mt-2 text-sm text-[#304458] [&_blockquote]:my-3 [&_blockquote]:rounded-r-lg [&_blockquote]:border-l-4 [&_blockquote]:border-[#c7b08d] [&_blockquote]:bg-[#f6efe3] [&_blockquote]:px-4 [&_blockquote]:py-3 [&_blockquote]:text-[#4a3e32] [&_blockquote]:italic [&_blockquote_p]:m-0 [&_blockquote_p+*]:mt-2 [&_blockquote_strong]:not-italic [&_blockquote_strong]:text-[#8f3f1f] [&_code]:font-mono [&_code]:text-[0.92em] [&_pre]:my-2 [&_pre]:overflow-x-auto [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:rounded-surface [&_pre]:border [&_pre]:border-[#cabca8] [&_pre]:bg-[#e6eaef] [&_pre]:px-3 [&_pre]:py-2.5" data-reply-preview-content></div>
                </section>
                <button type="submit" class="{{ $threadPrimaryActionClass }}">{{ $replyRequiresApproval ? 'Submit Reply for Approval' : 'Submit Reply' }}</button>
            </form>
    </section>
    @endif

    @php
        $postActionButtonClass = 'inline-flex items-center justify-center rounded-pill border border-[#cabca8] bg-white px-2.5 py-1 text-xs font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
        $renderedPostContentClass = 'forum-rendered-content m-0 whitespace-normal leading-[1.5] [&_a]:font-semibold [&_a]:underline-offset-2 [&_a]:text-[color:var(--color-site-brand-dark)] [&_a:hover]:underline [&_a:hover]:text-[color:var(--color-site-brand-text)] [&_blockquote]:my-3 [&_blockquote]:rounded-r-lg [&_blockquote]:border-l-4 [&_blockquote]:border-[#c7b08d] [&_blockquote]:bg-[#f6efe3] [&_blockquote]:px-4 [&_blockquote]:py-3 [&_blockquote]:text-[#4a3e32] [&_blockquote]:italic [&_blockquote_p]:m-0 [&_blockquote_p+*]:mt-2 [&_blockquote_strong]:not-italic [&_blockquote_strong]:text-[#8f3f1f] [&_code]:font-mono [&_code]:text-[0.92em] [&_pre]:my-2.5 [&_pre]:overflow-x-auto [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:rounded-surface [&_pre]:border [&_pre]:border-[#cabca8] [&_pre]:bg-[#e6eaef] [&_pre]:px-3.5 [&_pre]:py-3 [&_pre]:leading-[1.45]';
    @endphp
    <section class="mt-3 grid gap-3" data-select-root>
        @foreach($posts as $index => $post)
            @php
                $author = $post->author;
                $authorName = $author?->username ?? $author?->name ?? $author?->email ?? 'Unknown';
                $editedByName = $post->editedBy?->username ?? $post->editedBy?->name ?? $post->editedBy?->email;
                $canReact = auth()->check() && auth()->user()?->hasVerifiedEmail();
                $hasLikedPost = in_array((int) $post->id, $reactedPostIds ?? [], true);
                $likeCount = (int) ($post->like_reactions_count ?? 0);
                $canReportPost = auth()->check()
                    && auth()->user()?->hasVerifiedEmail()
                    && (int) (auth()->id() ?? 0) !== (int) ($post->author_id ?? 0);
                $hasOpenReport = in_array((int) $post->id, $reportedPostIds ?? [], true);
                $isUnderReview = $hasOpenReport
                    || (($canModerateThread ?? false) && in_array((int) $post->id, $openReportPostIds ?? [], true));
                $postPermalink = ($posts->currentPage() <= 1
                    ? route('threads.show', $thread)
                    : route('threads.show', ['thread' => $thread, 'page' => $posts->currentPage()])
                ) . '#post-' . $post->id;
            @endphp
            <article class="overflow-hidden border border-[#d8dce2] bg-white" id="post-{{ $post->id }}">
                @php $postNumber = ($posts->firstItem() ?? 1) + $index; @endphp
                <div class="flex items-start justify-between gap-3 border-b border-[#d8dce2] px-3.5 py-2.5 text-xs text-[#5c6570]">
                    <div>
                        @can('moderate', $post)
                            <label class="mr-2 inline-flex items-center gap-1 align-middle" data-post-select hidden>
                                <input type="checkbox" value="{{ $post->id }}" data-post-checkbox class="h-4 w-4 accent-[#8f3f1f]">
                                <span class="text-[11px] font-semibold uppercase tracking-[0.03em]">Select</span>
                            </label>
                        @endcan
                        {{ \App\Support\LocalTime::format($post->created_at) }}
                        @if($post->shouldShowEditedMarker())
                            <span
                                class="font-semibold text-[#304458]"
                                title="Edited {{ \App\Support\LocalTime::format($post->edited_at) }}{{ $editedByName ? ' by '.$editedByName : '' }}"
                            > [edited]</span>
                        @endif
                    </div>
                    <div class="inline-flex flex-wrap items-center justify-end gap-2">
                        @if($canReply)
                            <button type="button" class="{{ $postActionButtonClass }}" data-thread-reply>Reply</button>
                            <button
                                type="button"
                                class="{{ $postActionButtonClass }}"
                                data-thread-quote
                                data-quote-author="{{ $authorName }}"
                                data-quote-source="quote-body-{{ $post->id }}"
                            >Reply with Quote</button>
                            <textarea id="quote-body-{{ $post->id }}" hidden>{{ $post->body }}</textarea>
                        @elseif(!auth()->check())
                            <button type="button" class="{{ $postActionButtonClass }}" data-auth-modal-url="/login">Reply</button>
                            <button type="button" class="{{ $postActionButtonClass }}" data-auth-modal-url="/login">Reply with Quote</button>
                        @endif
                        @if(config('features.post_likes') && !$post->is_deleted && $post->isApproved())
                            @if($canReact)
                                <form method="post" action="{{ route('posts.reactions.toggle', $post) }}" class="m-0">
                                    @csrf
                                    <input type="hidden" name="type" value="like">
                                    <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                                    <button type="submit" class="{{ $postActionButtonClass }} {{ $hasLikedPost ? 'border-[#8f3f1f] bg-[#f7e8df] text-[#8f3f1f]' : '' }}">
                                        {{ $hasLikedPost ? 'Liked' : 'Like' }} ({{ number_format($likeCount) }})
                                    </button>
                                </form>
                            @elseif(!auth()->check())
                                <button type="button" class="{{ $postActionButtonClass }}" data-auth-modal-url="/login">
                                    Like ({{ number_format($likeCount) }})
                                </button>
                            @else
                                <span class="inline-flex items-center justify-center rounded-pill border border-[#cabca8] bg-[#f8f6f1] px-2.5 py-1 text-xs font-semibold text-[#5c6570]">
                                    Likes: {{ number_format($likeCount) }}
                                </span>
                            @endif
                        @endif
                        @if(!$post->is_deleted && $post->isApproved() && $canReportPost)
                            @if($hasOpenReport)
                                <span class="inline-flex items-center justify-center rounded-pill border border-[#cabca8] bg-[#f8f6f1] px-2.5 py-1 text-xs font-semibold text-[#5c6570]">
                                    Reported
                                </span>
                            @else
                                <button
                                    type="button"
                                    class="{{ $postActionButtonClass }}"
                                    data-report-open
                                    data-report-action="{{ route('posts.report', $post) }}"
                                    data-report-post-number="{{ (($posts->firstItem() ?? 1) + $index) }}"
                                >Report Post</button>
                            @endif
                        @endif
                        @canany(['update', 'moderate'], $post)
                            <details class="relative" data-post-menu>
                                <summary class="{{ $postActionButtonClass }} cursor-pointer list-none [&::-webkit-details-marker]:hidden" aria-label="Post actions" title="Post actions">&ctdot;</summary>
                                <div class="absolute right-0 z-[40] mt-1 w-60 border border-[#d8dce2] bg-white p-1 text-left shadow-[0_16px_36px_rgba(0,0,0,0.18)]">
                                    @can('update', $post)
                                        <a href="{{ route('posts.edit', ['post' => $post, 'return_to' => request()->getRequestUri()]) }}" class="{{ $menuItemClass }}">Edit</a>
                                    @endcan
                                    @can('moderate', $post)
                                        @if(!$post->is_deleted)
                                            <form method="post" action="{{ route('posts.moderate', $post) }}" class="m-0" data-post-move-form data-post-id="{{ $post->id }}" data-post-number="{{ $postNumber }}" data-post-author="{{ $authorName }}">
                                                @csrf
                                                <input type="hidden" name="action" value="move">
                                                <input type="hidden" name="destination_thread_id" value="" data-post-move-thread-id>
                                                <input type="hidden" name="reason" value="" data-post-move-reason>
                                                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                                                <button type="submit" class="{{ $menuItemClass }}">Move to another thread…</button>
                                            </form>
                                            <form method="post" action="{{ route('posts.moderate', $post) }}" class="m-0" data-post-promote-form data-post-id="{{ $post->id }}" data-post-promote-title-default="Re: {{ $decodedThreadTitle }}" data-post-number="{{ $postNumber }}" data-post-author="{{ $authorName }}">
                                                @csrf
                                                <input type="hidden" name="action" value="promote">
                                                <input type="hidden" name="destination_forum_id" value="" data-post-promote-forum-id>
                                                <input type="hidden" name="new_thread_title" value="" data-post-promote-title>
                                                <input type="hidden" name="reason" value="" data-post-promote-reason>
                                                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                                                <button type="submit" class="{{ $menuItemClass }}">Promote to thread…</button>
                                            </form>
                                        @endif
                                        <form method="post" action="{{ route('posts.moderate', $post) }}" class="m-0" data-post-moderation-form data-post-number="{{ $postNumber }}" data-post-author="{{ $authorName }}">
                                            @csrf
                                            <input type="hidden" name="action" value="{{ $post->is_deleted ? 'restore' : 'delete' }}">
                                            <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                                            <input type="hidden" name="reason" value="" data-post-moderation-reason>
                                            <button type="submit" class="{{ $menuItemClass }}" @if(!$post->is_deleted) data-post-delete-button @endif>{{ $post->is_deleted ? 'Restore' : 'Delete…' }}</button>
                                        </form>
                                        @if(!$post->isApproved())
                                            <form method="post" action="{{ route('posts.moderate', $post) }}" class="m-0">
                                                @csrf
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                                                <button type="submit" class="{{ $menuItemClass }}">Approve</button>
                                            </form>
                                            <form method="post" action="{{ route('posts.moderate', $post) }}" class="m-0">
                                                @csrf
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                                                <button type="submit" class="{{ $menuItemClass }}">Reject</button>
                                            </form>
                                        @elseif(!$post->is_deleted)
                                            <form method="post" action="{{ route('posts.moderate', $post) }}" class="m-0">
                                                @csrf
                                                <input type="hidden" name="action" value="unapprove">
                                                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                                                <button type="submit" class="{{ $menuItemClass }}">Unapprove (send to moderation)</button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </details>
                        @endcanany
                        @if($post->is_deleted)
                            <span class="inline-flex items-center justify-center rounded-pill border border-[#d99a9a] bg-[#fdeeee] px-2 py-0.5 text-[11px] font-bold uppercase tracking-[0.04em] text-[#732f2f]">Deleted</span>
                        @endif
                        @if($post->isPending())
                            <span class="inline-flex items-center justify-center rounded-pill border border-[#cabca8] bg-[#f8f6f1] px-2 py-0.5 text-[11px] font-bold uppercase tracking-[0.04em] text-[#5c6570]">Pending</span>
                        @elseif($post->isRejected())
                            <span class="inline-flex items-center justify-center rounded-pill border border-[#d99a9a] bg-[#fdeeee] px-2 py-0.5 text-[11px] font-bold uppercase tracking-[0.04em] text-[#732f2f]">Rejected</span>
                        @endif
                        <a
                            href="{{ $postPermalink }}"
                            class="text-xs text-[#5c6570] hover:underline focus-visible:underline"
                            title="Link to this post"
                        >#{{ (($posts->firstItem() ?? 1) + $index) }}</a>
                    </div>
                </div>
                @php
                    $authorUserTitle = filled($author?->user_title) ? $author->user_title : 'Member';
                    $authorLocation = is_string($author?->location) && trim($author->location) !== '' ? $author->location : null;
                    $authorJoinedAt = $author?->joined_at;
                    $authorPostsCount = (int) ($author?->forum_posts_count ?? 0);
                    $authorPluginCount = (int) ($author?->forum_plugins_count ?? 0);
                @endphp
                <div class="border-b border-[#d8dce2] bg-[#f8f6f1] px-3.5 py-3">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div class="flex items-start gap-3">
                            <div class="shrink-0">
                                @if($author?->id)
                                    <img
                                        src="{{ $author->avatarUrl(96) }}"
                                        alt="{{ $authorName }} avatar"
                                        class="h-16 w-16 rounded-control border border-[#cabca8] bg-white object-cover"
                                        loading="lazy"
                                    >
                                @else
                                    <div class="flex h-16 w-16 items-center justify-center rounded-control border border-[#cabca8] bg-white text-lg font-semibold text-[#5c6570]">
                                        {{ strtoupper(substr($authorName, 0, 1)) }}
                                    </div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="m-0 mb-1 text-[1.6rem] leading-none font-bold tracking-[-0.03em] text-[#0d5fc8]">
                                    @if($author?->id)
                                        <a class="hover:underline focus-visible:underline" href="{{ route('members.show', ['user' => $author->id]) }}">{{ $authorName }}</a>
                                    @else
                                        {{ $authorName }}
                                    @endif
                                </p>
                                <p class="m-0 text-sm font-semibold text-[#304458]">{{ $authorUserTitle }}</p>
                            </div>
                        </div>

                        <dl class="grid gap-0.5 text-sm text-[#304458] md:min-w-[260px]">
                            <div class="grid grid-cols-[auto_1fr] gap-x-2">
                                <dt class="font-medium text-[#5c6570]">Join Date:</dt>
                                <dd class="text-right">{{ $authorJoinedAt ? \App\Support\LocalTime::format($authorJoinedAt, 'month') : 'Unknown' }}</dd>
                            </div>
                            @if($authorLocation)
                                <div class="grid grid-cols-[auto_1fr] gap-x-2">
                                    <dt class="font-medium text-[#5c6570]">Location:</dt>
                                    <dd class="text-right">{{ $authorLocation }}</dd>
                                </div>
                            @endif
                            <div class="grid grid-cols-[auto_1fr] gap-x-2">
                                <dt class="font-medium text-[#5c6570]">Posts:</dt>
                                <dd class="text-right">{{ number_format($authorPostsCount) }}</dd>
                            </div>
                            <div class="grid grid-cols-[auto_1fr] gap-x-2">
                                <dt class="font-medium text-[#5c6570]">Plugin Contributions:</dt>
                                <dd class="text-right">{{ number_format($authorPluginCount) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
                <div class="px-3.5 py-3">
                        <h2 class="ui-card-title mb-2 text-[1.08rem]">{{ $index === 0 ? $decodedThreadTitle : 'Re: '.$decodedThreadTitle }}</h2>
                        @if($isUnderReview && !$post->is_deleted && $post->isApproved())
                            <div class="mb-3 flex items-center gap-2 border border-[#e7d3b8] bg-[#fbf1e6] px-3 py-2 text-sm text-[#7a4a1d]" role="status">
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                                <span><strong class="font-semibold">This post has been reported</strong> and is awaiting staff review.</span>
                            </div>
                        @endif
                        @if($post->is_deleted)
                            <div class="{{ $threadErrorClass }} mb-0">
                                <strong>This post was deleted by staff.</strong>
                                @if($post->deletedBy || $post->deleted_at)
                                    <br>
                                    <span>
                                        @if($post->deletedBy)
                                            Deleted by {{ $post->deletedBy->username ?: $post->deletedBy->name ?: $post->deletedBy->email }}.
                                        @endif
                                        @if($post->deleted_at)
                                            {{ $post->deletedBy ? ' ' : '' }}{{ \App\Support\LocalTime::format($post->deleted_at) }}.
                                        @endif
                                    </span>
                                @endif
                                @if($post->deleted_reason)
                                    <br>
                                    <span>Reason: {{ $post->deleted_reason }}</span>
                                @endif
                            </div>
                        @elseif(!$post->isApproved())
                            <div class="{{ $threadErrorClass }} mb-0">
                                <strong>
                                    @if($post->isPending())
                                        This post is pending staff approval.
                                    @else
                                        This post has been rejected.
                                    @endif
                                </strong>
                                @if($post->moderation_reason)
                                    <br>
                                    <span>Reason: {{ $post->moderation_reason }}</span>
                                @endif
                            </div>
                            <div class="mt-3 {{ $renderedPostContentClass }}">{!! $contentRenderer->render($post->body) !!}</div>
                        @else
                            <div class="{{ $renderedPostContentClass }}">{!! $contentRenderer->render($post->body) !!}</div>
                            @include('forum.partials.post_attachments', ['post' => $post])
                            @php
                                $currentSignature = $post->author?->signature;
                            @endphp
                            @if(is_string($currentSignature) && trim($currentSignature) !== '')
                                <div class="forum-rendered-content mt-3 border-t border-[#d8dce2] pt-2.5 text-sm text-[#304458] [&_blockquote]:my-3 [&_blockquote]:rounded-r-lg [&_blockquote]:border-l-4 [&_blockquote]:border-[#c7b08d] [&_blockquote]:bg-[#f6efe3] [&_blockquote]:px-4 [&_blockquote]:py-3 [&_blockquote]:text-[#4a3e32] [&_blockquote]:italic [&_blockquote_p]:m-0 [&_blockquote_p+*]:mt-2 [&_blockquote_strong]:not-italic [&_blockquote_strong]:text-[#8f3f1f] [&_code]:font-mono [&_code]:text-[0.92em] [&_pre]:my-2.5 [&_pre]:overflow-x-auto [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:rounded-surface [&_pre]:border [&_pre]:border-[#cabca8] [&_pre]:bg-[#e6eaef] [&_pre]:px-3.5 [&_pre]:py-3 [&_pre]:leading-[1.45]">
                                    {!! $contentRenderer->render($currentSignature) !!}
                                </div>
                            @endif
                        @endif
                    </div>
            </article>
        @endforeach

        @if($posts->count() === 0)
            <article class="overflow-hidden border border-[#d8dce2] bg-white">
                <div class="px-3.5 py-3">
                    <p class="m-0 whitespace-normal leading-[1.5] text-[#304458]">No posts found in this thread.</p>
                </div>
            </article>
        @endif
    </section>

    @include('thread.partials.post_pagination', ['posts' => $posts, 'position' => 'bottom'])
    </div>

    @php
        $canRelocateContent = ($canModerateThread ?? false)
            || (bool) (auth()->user()?->can('viewAny', \Filagora\Forums\Models\Post::class));
    @endphp

    @if($canModerateThread ?? false)
        <div class="fixed inset-x-0 bottom-0 z-[60] border-t border-[#d8dce2] bg-white px-4 py-3 shadow-[0_-12px_28px_rgba(0,0,0,0.14)]" data-select-bar hidden>
            <div class="mx-auto flex max-w-[1320px] flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-[#0f2133]" data-select-count>{{ __('forum.relocate.select.count', ['count' => 0]) }}</span>
                <span class="grow"></span>
                <button type="button" class="{{ $threadActionButtonClass }}" data-relocate-open="bulk-move" disabled>{{ __('forum.relocate.select.move') }}</button>
                <button type="button" class="{{ $threadActionButtonClass }}" data-relocate-open="bulk-promote" disabled>{{ __('forum.relocate.select.promote') }}</button>
                <button type="button" class="{{ $threadActionButtonClass }}" data-relocate-open="bulk-delete" disabled>{{ __('forum.relocate.select.delete') }}</button>
                <button type="button" class="{{ $threadActionButtonClass }}" data-select-clear>{{ __('forum.relocate.select.clear') }}</button>
            </div>
        </div>
    @endif

    @if($canRelocateContent)
        @include('forum.partials.relocate_dialog')
    @endif

    <div class="fixed inset-0 z-[1210] flex items-center justify-center p-4" id="report-post-modal" hidden>
        <div class="absolute inset-0 bg-[rgba(12,10,8,0.58)]" data-report-close></div>
        <div class="relative z-[1] w-full max-w-lg border border-[#d8dce2] bg-white p-4 shadow-[0_30px_60px_rgba(0,0,0,0.32)]" role="dialog" aria-modal="true" aria-labelledby="report-post-modal-title" aria-describedby="report-post-modal-description" tabindex="-1" data-report-dialog>
            <h2 class="text-lg font-semibold text-[#0f2133]" id="report-post-modal-title">Report Post</h2>
            <p class="mt-1 text-sm text-[#5c6570]" id="report-post-modal-description">Tell staff why this post should be reviewed.</p>
            <form method="post" id="report-post-modal-form" class="mt-3 grid gap-2">
                @csrf
                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                <label class="text-sm font-semibold text-[#5c6570]" for="report-post-modal-reason">Reason (required)</label>
                <textarea
                    id="report-post-modal-reason"
                    name="reason"
                    rows="4"
                    required
                    maxlength="4000"
                    class="w-full rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                ></textarea>
                <p id="report-post-modal-error" class="text-sm text-[#dc3545]" role="alert" hidden></p>
                <div class="mt-2 flex items-center justify-end gap-2">
                    <button type="button" class="{{ $threadActionButtonClass }}" data-report-close>Cancel</button>
                    <button type="submit" class="{{ $threadPrimaryActionClass }}">Submit Report</button>
                </div>
            </form>
        </div>
    </div>
@endsection
