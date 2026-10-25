@if($canModerateQueue ?? false)
@php
    $decodeLegacyText = static fn (?string $value): string => \App\Support\LegacyTextDecoder::decode($value);
@endphp
    <details class="mb-6 overflow-hidden border border-[#d8dce2] bg-white shadow-[0_2px_6px_rgba(15,23,42,0.04)]">
        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 border-b border-[#d8dce2] bg-white px-4 py-3 text-[#243342] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f] [&::-webkit-details-marker]:hidden">
            <strong>Forum Moderation Queue</strong>
            <small class="text-xs text-[#5c6570]">
                ({{ $pendingThreads->count() }} threads, {{ $pendingPosts->count() }} posts reviewable, {{ ($reportedPosts ?? collect())->sum('count') }} open reports)
            </small>
        </summary>

        <h4 class="mt-4 px-4 text-sm font-semibold uppercase tracking-[0.04em] text-[#304458]">Reviewable Threads</h4>
        <ul class="grid gap-3 p-4">
            @forelse($pendingThreads as $pendingThread)
                @php
                    $threadStatus = $pendingThread->moderation_status?->label() ?? ucfirst((string) $pendingThread->moderation_status);
                    $threadAuthor = $pendingThread->author?->username ?: ($pendingThread->author?->name ?: ($pendingThread->author?->email ?: 'Unknown author'));
                @endphp
                <li class="border border-[#d8dce2] bg-white p-3">
                    <div class="mb-2 grid gap-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <strong><a class="text-[#8f3f1f] hover:underline" href="{{ route('threads.show', $pendingThread) }}">{{ $decodeLegacyText($pendingThread->title) }}</a></strong>
                            <span class="inline-flex items-center rounded-pill border border-[#d8dce2] bg-white px-2.5 py-1 text-xs font-semibold text-[#304458]">{{ $threadStatus }}</span>
                        </div>
                        <small class="text-xs text-[#5c6570]">{{ $pendingThread->forum?->name ?? 'Unknown forum' }} · submitted {{ \App\Support\LocalTime::format($pendingThread->created_at) }}</small>
                        <small class="text-xs text-[#5c6570]">Author: {{ $threadAuthor }}</small>
                        @if($pendingThread->moderation_reason)
                            <small class="text-xs text-[#5c6570]">Latest reason: {{ $pendingThread->moderation_reason }}</small>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <form method="post" action="{{ route('threads.moderate', $pendingThread) }}">
                            @csrf
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                            <button type="submit" class="inline-flex items-center border border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Approve Thread</button>
                        </form>
                        @include('forum.partials.thread_delete_button', [
                            'thread' => $pendingThread,
                            'label' => __('forum.thread_delete.reject_open'),
                            'buttonClass' => 'inline-flex items-center border border-[#8f3f1f] bg-white px-3 py-2 text-sm font-semibold text-[#8f3f1f] transition hover:bg-[#f7e8df] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]',
                        ])
                    </div>
                </li>
            @empty
                <li><small>No reviewable threads.</small></li>
            @endforelse
        </ul>

        <h4 class="mt-2 px-4 text-sm font-semibold uppercase tracking-[0.04em] text-[#304458]">Reviewable Posts</h4>
        <ul class="grid gap-3 p-4 pt-2" id="forum-moderation-queue">
            @forelse($pendingPosts as $pendingPost)
                @php
                    $postStatus = $pendingPost->moderation_status?->label() ?? ucfirst((string) $pendingPost->moderation_status);
                    $postAuthor = $pendingPost->author?->username ?: ($pendingPost->author?->name ?: ($pendingPost->author?->email ?: 'Unknown author'));
                @endphp
                <li class="border border-[#d8dce2] bg-white p-3">
                    <div class="mb-2 grid gap-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <strong><a class="text-[#8f3f1f] hover:underline" href="{{ $pendingPost->moderation_queue_show_url }}">{{ $decodeLegacyText($pendingPost->thread?->title ?? 'Thread') }}</a></strong>
                            <span class="inline-flex items-center rounded-pill border border-[#d8dce2] bg-white px-2.5 py-1 text-xs font-semibold text-[#304458]">{{ $postStatus }}</span>
                        </div>
                        <small class="text-xs text-[#5c6570]">{{ $pendingPost->thread?->forum?->name ?? 'Unknown forum' }} · posted {{ \App\Support\LocalTime::format($pendingPost->created_at) }}</small>
                        <small class="text-xs text-[#5c6570]">Author: {{ $postAuthor }}</small>
                        <p class="text-sm text-[#304458]">{{ \Illuminate\Support\Str::limit(app(\App\Support\FormattedContentRenderer::class)->plainText($pendingPost->body), 180) }}</p>
                        @if($pendingPost->moderation_reason)
                            <small class="text-xs text-[#5c6570]">Latest reason: {{ $pendingPost->moderation_reason }}</small>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <form method="post" action="{{ route('posts.moderate', $pendingPost) }}">
                            @csrf
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                            <button type="submit" class="inline-flex items-center border border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Approve Post</button>
                        </form>
                        <form method="post" action="{{ route('posts.moderate', $pendingPost) }}" class="flex-1 min-w-[240px] grid gap-2">
                            @csrf
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                            <textarea name="reason" rows="2" class="w-full border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" placeholder="Optional rejection reason"></textarea>
                            @include('forum.partials.ban_author_fields', ['authorName' => $postAuthor, 'collapsed' => true])
                            <button type="submit" class="inline-flex items-center border border-[#8f3f1f] bg-[#8f3f1f] px-3 py-2 text-sm font-semibold text-white transition hover:border-[#7f3219] hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Reject Post</button>
                        </form>
                        <form method="post" action="{{ route('posts.moderate', $pendingPost) }}" class="flex-1 min-w-[240px] grid gap-2" onsubmit="return confirm('Reject this post permanently? It will no longer appear in the moderation queue.');">
                            @csrf
                            <input type="hidden" name="action" value="reject_permanently">
                            <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                            <textarea name="reason" rows="2" class="w-full border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" placeholder="Optional rejection reason"></textarea>
                            @include('forum.partials.ban_author_fields', ['authorName' => $postAuthor, 'collapsed' => true])
                            <button type="submit" class="inline-flex items-center border border-[#8f3f1f] bg-white px-3 py-2 text-sm font-semibold text-[#8f3f1f] transition hover:bg-[#f7e8df] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Reject Permanently</button>
                        </form>
                    </div>
                </li>
            @empty
                <li><small>No reviewable posts.</small></li>
            @endforelse
        </ul>

        <h4 class="mt-2 px-4 text-sm font-semibold uppercase tracking-[0.04em] text-[#304458]">Reported Posts</h4>
        <ul class="grid gap-3 p-4 pt-2">
            @forelse($reportedPosts ?? [] as $group)
                @php
                    $reportedReply = $group['post'];
                    $reportedAuthor = $reportedReply->author?->username ?: ($reportedReply->author?->name ?: ($reportedReply->author?->email ?: 'Unknown author'));
                    $firstReportId = $group['reports']->first()->getKey();
                @endphp
                <li class="border border-[#d8dce2] bg-white p-3">
                    <div class="mb-2 grid gap-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <strong><a class="text-[#8f3f1f] hover:underline" href="{{ $group['post_show_url'] }}">{{ $decodeLegacyText($reportedReply->thread?->title ?? 'Thread') }}</a></strong>
                            <span class="inline-flex items-center rounded-pill border border-[#d99a9a] bg-[#fdeeee] px-2.5 py-1 text-xs font-semibold text-[#9f2230]">{{ trans_choice(':count report|:count reports', $group['count'], ['count' => $group['count']]) }}</span>
                        </div>
                        <small class="text-xs text-[#5c6570]">{{ $reportedReply->thread?->forum?->name ?? 'Unknown forum' }} · reply by {{ $reportedAuthor }}</small>
                        @if($group['is_only_post'])
                            <small class="text-xs text-[#8f7a1f]">This is the only reply in its thread.</small>
                        @endif
                        <p class="text-sm text-[#304458]">&ldquo;{{ \Illuminate\Support\Str::limit(app(\App\Support\FormattedContentRenderer::class)->plainText($reportedReply->body), 180) }}&rdquo;</p>
                        <div class="grid gap-0.5 text-xs text-[#5c6570]">
                            @foreach($group['reports'] as $reportRow)
                                <span><strong class="text-[#304458]">{{ $reportRow->reporter?->username ?: ($reportRow->reporter?->name ?: 'Unknown') }}</strong> · &ldquo;{{ \Illuminate\Support\Str::limit(trim((string) $reportRow->reason), 100) }}&rdquo; · {{ \App\Support\LocalTime::format($reportRow->created_at) }}</span>
                            @endforeach
                        </div>
                    </div>
                    <form method="post" action="{{ route('moderation.reports.resolve', $firstReportId) }}" class="grid gap-3 border-t border-[#eceae5] pt-3" data-report-resolve-form onsubmit="return confirm('Resolve this report? Every open report on this reply is closed.');">
                        @csrf
                        <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">

                        <fieldset class="grid gap-1.5">
                            <legend class="text-sm font-semibold text-[#304458]">Outcome</legend>
                            <label class="flex items-start gap-2 text-sm text-[#304458]"><input type="radio" name="outcome" value="dismiss" class="mt-1" checked> <span><strong class="font-semibold">Dismiss</strong> — not a violation. The report is closed.</span></label>
                            <label class="flex items-start gap-2 text-sm text-[#304458]"><input type="radio" name="outcome" value="keep" class="mt-1"> <span><strong class="font-semibold">Keep</strong> the reply. The report is closed with your note.</span></label>
                            <label class="flex items-start gap-2 text-sm text-[#304458]"><input type="radio" name="outcome" value="remove" class="mt-1"> <span><strong class="font-semibold">Remove the reply</strong> (soft delete).</span></label>
                            @if($group['is_only_post'])
                                <label class="flex items-start gap-2 text-sm text-[#304458]"><input type="radio" name="outcome" value="remove_thread" class="mt-1"> <span><strong class="font-semibold">Remove the reply and its thread</strong> — this is the only reply, so the empty thread is deleted too.</span></label>
                            @endif
                        </fieldset>

                        <label class="grid gap-1 text-sm font-semibold text-[#304458]">
                            Resolution note <span class="font-normal text-[#5c6570]">(optional; kept on the report record)</span>
                            <textarea name="note" rows="2" maxlength="4000" class="w-full border border-[#d8dce2] bg-white px-3 py-2 text-sm font-normal text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" placeholder="Advertising / spam. Removed."></textarea>
                        </label>

                        @include('forum.partials.ban_author_fields', ['authorName' => $reportedAuthor])

                        <div>
                            <button type="submit" class="inline-flex items-center border border-[#8f3f1f] bg-[#8f3f1f] px-3 py-2 text-sm font-semibold text-white transition hover:border-[#7f3219] hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Resolve report</button>
                        </div>
                    </form>
                </li>
            @empty
                <li><small>No open reports.</small></li>
            @endforelse
        </ul>
    </details>
@endif
