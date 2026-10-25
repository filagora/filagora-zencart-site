{{--
    Opens the "delete this thread permanently" dialog for $thread. Pass
    $buttonClass to match the surrounding controls, and optionally $label and
    $returnTo (defaults to the current page). The dialog is pushed to the
    layout's modals stack once per page, outside any collapsed <details>.
--}}
@php
    $deleteAuthor = $thread->author;
    $deleteAuthorName = $deleteAuthor?->username ?: ($deleteAuthor?->name ?: __('forum.thread_delete.unknown_author'));
    $deletePostCount = max(1, (int) $thread->reply_count + 1);
    $deleteCanBan = $deleteAuthor !== null && (int) $deleteAuthor->id !== (int) auth()->id();
@endphp
<button
    type="button"
    class="{{ $buttonClass }}"
    data-thread-delete-open
    data-action="{{ route('threads.moderate', $thread) }}"
    data-return-to="{{ $returnTo ?? request()->getRequestUri() }}"
    data-context="{{ __('forum.thread_delete.context', ['title' => \App\Support\LegacyTextDecoder::decode($thread->title), 'author' => $deleteAuthorName]) }}"
    data-summary="{{ trans_choice('forum.thread_delete.summary', $deletePostCount, ['count' => $deletePostCount]) }}"
    data-ban-label="{{ $deleteCanBan ? __('forum.thread_delete.ban_label', ['author' => $deleteAuthorName]) : '' }}"
>{{ $label ?? __('forum.thread_delete.open') }}</button>
@once
    @push('modals')
        @include('forum.partials.thread_delete_dialog')
    @endpush
@endonce
