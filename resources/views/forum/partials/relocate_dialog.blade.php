@php
    /**
     * One adaptive dialog for every moderator relocate action — move thread,
     * rename thread, report-as-bug, move reply, promote reply, and the bulk
     * variants. `resources/js/modules/moderation-relocate.js` shows/hides the
     * fields per mode and points the submit at the right endpoint.
     *
     * On a single-thread page the caller passes $relocateThread and the dialog
     * carries that thread's context as defaults. On a thread list it is
     * included once with no thread; each row's opener button then carries its
     * own data-thread-* attributes.
     */
    $relocateThread = $relocateThread ?? ($thread ?? null);
    $relocateFieldClass = 'w-full rounded-control border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
    $relocateChipClass = 'inline-flex items-center rounded-pill border border-[#cabca8] bg-white px-2.5 py-1 text-xs font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
    $relocateCancelClass = $threadActionButtonClass ?? 'inline-flex items-center rounded-control border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
    $relocateConfirmClass = $threadPrimaryActionClass ?? 'inline-flex items-center rounded-control border border-[#8f3f1f] bg-[#8f3f1f] px-3 py-2 text-sm font-semibold text-white transition hover:border-[#7f3219] hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]';
@endphp
<div
    class="fixed inset-0 z-[1200] flex items-center justify-center p-4"
    id="relocate-dialog"
    data-relocate-dialog
    @if($relocateThread)
        data-thread-id="{{ $relocateThread->id }}"
        data-forum-id="{{ $relocateThread->forum_id }}"
        data-thread-title="{{ $decodedThreadTitle ?? \App\Support\LegacyTextDecoder::decode($relocateThread->title) }}"
        data-thread-moderate-url="{{ route('threads.moderate', $relocateThread) }}"
        data-bulk-url="{{ route('posts.moderate-bulk', $relocateThread) }}"
        data-forum-name="{{ $decodedForumName ?? \App\Support\LegacyTextDecoder::decode($relocateThread->forum?->name ?? '') }}"
    @endif
    data-thread-search-url="{{ route('threads.search-for-move') }}"
    data-forum-search-url="{{ route('forums.search-for-move') }}"
    data-undo-url="{{ route('moderation.relocate.undo') }}"
    data-return-to="{{ request()->getRequestUri() }}"
    hidden
>
    <script>
        window.forumRelocateStrings = @json(__('forum.relocate'));
    </script>
    <div class="absolute inset-0 bg-[rgba(12,10,8,0.58)]" data-relocate-close></div>
    <div
        class="relative z-[1] w-full max-w-lg border border-[#d8dce2] bg-white p-4 shadow-[0_30px_60px_rgba(0,0,0,0.32)]"
        role="dialog"
        aria-modal="true"
        aria-labelledby="relocate-dialog-title"
        aria-describedby="relocate-dialog-context"
        tabindex="-1"
        data-relocate-panel
    >
        <h2 class="text-lg font-semibold text-[#0f2133]" id="relocate-dialog-title" data-relocate-title>{{ __('forum.relocate.dialog.thread_move_title') }}</h2>
        <p class="mt-1 text-sm text-[#5c6570]" id="relocate-dialog-context" data-relocate-context></p>
        <p class="mt-3 border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]" role="alert" data-relocate-error hidden></p>

        <div class="relative mt-3 grid gap-1.5" data-relocate-destination hidden>
            <label class="text-sm font-semibold text-[#5c6570]" for="relocate-search" data-relocate-destination-label>{{ __('forum.relocate.dialog.destination_thread_label') }}</label>
            <input
                class="{{ $relocateFieldClass }}"
                id="relocate-search"
                type="search"
                autocomplete="off"
                role="combobox"
                aria-expanded="false"
                aria-controls="relocate-results"
                aria-autocomplete="list"
                data-relocate-search
                placeholder="{{ __('forum.relocate.dialog.search_placeholder') }}"
            >
            <input type="hidden" data-relocate-destination-id>
            <p class="text-xs font-semibold text-[#1f5130]" data-relocate-selected hidden></p>
            <div class="flex flex-wrap items-center gap-1.5" data-relocate-recent hidden>
                <span class="text-xs font-semibold text-[#5c6570]">{{ __('forum.relocate.dialog.recent_label') }}</span>
            </div>
            <ul class="max-h-64 divide-y divide-[#eceae5] overflow-y-auto border border-[#d8dce2] bg-white" id="relocate-results" role="listbox" data-relocate-results hidden></ul>
            <p class="text-[11px] text-[#5c6570]" data-relocate-hint hidden>{{ __('forum.relocate.dialog.keyboard_hint') }}</p>
        </div>

        <div class="mt-3 grid gap-1.5" data-relocate-title-field hidden>
            <label class="text-sm font-semibold text-[#5c6570]" for="relocate-new-title" data-relocate-title-label>{{ __('forum.relocate.dialog.title_label') }}</label>
            <input class="{{ $relocateFieldClass }}" id="relocate-new-title" type="text" maxlength="255" data-relocate-new-title>
        </div>

        <div class="mt-3 grid gap-1.5" data-relocate-reason-field hidden>
            <label class="text-sm font-semibold text-[#5c6570]" for="relocate-reason" data-relocate-reason-label>
                {{ __('forum.relocate.dialog.reason_label') }}
                <span class="font-normal text-[#5c6570]" data-relocate-reason-optional>({{ __('forum.relocate.dialog.reason_optional') }})</span>
            </label>
            <div class="flex flex-wrap gap-1.5" data-relocate-reason-chips>
                @foreach(__('forum.relocate.dialog.reason_chips') as $chip)
                    <button type="button" class="{{ $relocateChipClass }}" data-relocate-reason-chip>{{ $chip }}</button>
                @endforeach
            </div>
            <textarea class="{{ $relocateFieldClass }}" id="relocate-reason" rows="2" maxlength="5000" data-relocate-reason></textarea>
        </div>

        <div class="mt-4 flex items-center justify-end gap-2">
            <button type="button" class="{{ $relocateCancelClass }}" data-relocate-close>{{ __('forum.relocate.dialog.cancel') }}</button>
            <button type="button" class="{{ $relocateConfirmClass }}" data-relocate-confirm>{{ __('forum.relocate.dialog.confirm_move_thread') }}</button>
        </div>
    </div>
</div>
