@extends('layouts.main')

@section('title', 'Start New Thread')
@section('main_class', 'bg-white')

@section('content')
    @php
        $threadRequiresApproval = app(\Filagora\Forums\Services\ApprovalRules::class)->threadRequiresApproval($forum, auth()->user());
        $decodedForumName = \App\Support\LegacyTextDecoder::decode($forum->name);
    @endphp
    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
        @include('forum.partials.header_nav')
        @include('forum.partials.moderation_queue')

        <div class="mb-3 text-sm text-[#5c6570]">
            <a class="text-[#8f3f1f] hover:underline" href="{{ route('forums.index') }}">Forums</a>
            / <a href="{{ route('forums.show', $forum) }}">{{ $decodedForumName }}</a>
            / New Thread
        </div>

        <header class="mb-5">
            <h1 class="ui-page-title">Start New Thread</h1>
            <p class="ui-page-subtitle mt-2">Create a new discussion in "{{ $decodedForumName }}".</p>
        </header>

        <div class="mb-6">
            @include('forum.partials.search_bar')
        </div>

        @if($threadRequiresApproval)
            <div class="mb-3 rounded-surface border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]" role="status" aria-live="polite">
                <strong>Staff approval required.</strong> New threads in this forum are reviewed before they appear publicly.
            </div>
        @endif

        @if($errors->any())
            <div class="mb-3 rounded-surface border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]" role="alert" aria-live="assertive">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('threads.create', $forum) }}" enctype="multipart/form-data" class="grid max-w-[900px] gap-2 rounded-surface border border-[#d8dce2] bg-white p-4" data-lock-on-submit>
            @csrf
            <p id="thread-create-required-help" class="text-sm text-[#5c6570]">Fields marked “(required)” must be completed.</p>

            <label for="thread-title" class="text-sm font-semibold text-[#5c6570]">Title (required)</label>
            <input
                class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                id="thread-title"
                type="text"
                name="title"
                value="{{ old('title') }}"
                required
                aria-required="true"
                aria-describedby="thread-create-required-help{{ $errors->has('title') ? ' thread-title-error' : '' }}"
                @if($errors->has('title')) aria-invalid="true" @endif
            >
            @if($errors->has('title'))
                <p id="thread-title-error" class="text-sm text-[#dc3545]">{{ $errors->first('title') }}</p>
            @endif

            <label for="thread-body" class="text-sm font-semibold text-[#5c6570]">Message (required)</label>
            <div
                class="overflow-hidden rounded-control border border-[#d8dce2] bg-white"
                data-rich-editor data-rich-editor-target="thread-body" data-rich-editor-default="rich"
            >
                <x-markdown-toolbar target="thread-body" :classic="true" />
                <div class="grid md:grid-cols-[minmax(0,1fr)_136px]">
                    <textarea
                        class="min-h-[220px] w-full resize-y border-0 bg-transparent px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#8f3f1f] md:border-r md:border-[#d8dce2]"
                        id="thread-body"
                        name="body"
                        required
                        aria-required="true"
                        aria-describedby="thread-create-required-help{{ $errors->has('body') ? ' thread-body-error' : '' }}"
                        @if($errors->has('body')) aria-invalid="true" @endif
                    >{{ old('body') }}</textarea>
                    <div class="grid grid-cols-3 content-start gap-1 border-t border-[#d8dce2] p-2 md:border-l md:border-t-0 md:border-[#d8dce2]">
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#d8dce2] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="thread-body" data-smiley="🙂" title=":)">🙂</button>
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#d8dce2] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="thread-body" data-smiley="🙁" title=":(">🙁</button>
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#d8dce2] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="thread-body" data-smiley="😉" title=";)">😉</button>
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#d8dce2] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="thread-body" data-smiley="😄" title=":D">😄</button>
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#d8dce2] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="thread-body" data-smiley="😛" title=":P">😛</button>
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#d8dce2] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="thread-body" data-smiley="😮" title=":o">😮</button>
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#d8dce2] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="thread-body" data-smiley="👍" title=":thumbsup:">👍</button>
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#d8dce2] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="thread-body" data-smiley="👎" title=":thumbsdown:">👎</button>
                    </div>
                </div>
            </div>
            <p class="text-sm text-[#5c6570]">Uses Markdown, with supported extensions for underline, alignment, color, size, font, spoilers, video, and attachments.</p>
            @if($errors->has('body'))
                <p id="thread-body-error" class="text-sm text-[#dc3545]">{{ $errors->first('body') }}</p>
            @endif
            <div class="flex">
                <button
                    class="inline-flex items-center justify-center rounded-control border border-[#d8dce2] bg-white px-2 py-1 text-xs font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                    data-markdown-trigger
                    type="button"
                    data-markdown-preview
                    data-markdown-source="thread-body"
                    data-markdown-preview-panel="thread-body-preview"
                >Preview</button>
            </div>
            <section id="thread-body-preview" class="rounded-control border border-[#d8dce2] bg-[#f8f6f1] p-3" hidden>
                <h3 class="text-sm font-semibold text-[#304458]">Preview</h3>
                <div class="mt-2 text-sm text-[#304458]" data-reply-preview-content></div>
            </section>

            <label for="thread-attachments" class="text-sm font-semibold text-[#5c6570]">Attachments (optional)</label>
            <input
                class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                id="thread-attachments"
                type="file"
                name="attachments[]"
                multiple
                accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.zip,.log"
                aria-describedby="thread-attachments-help{{ $errors->has('attachments') ? ' thread-attachments-error' : '' }}"
                @if($errors->has('attachments')) aria-invalid="true" @endif
            >
            <p id="thread-attachments-help" class="text-sm text-[#5c6570]">Up to 5 files, 10MB each. Allowed: jpg, jpeg, png, gif, webp, pdf, txt, zip, log.</p>
            @if($errors->has('attachments'))
                <p id="thread-attachments-error" class="text-sm text-[#dc3545]">{{ $errors->first('attachments') }}</p>
            @endif

            <label class="mt-3 flex items-center gap-3 rounded-surface border border-[#cabca8] bg-[#f7e8df] px-3.5 py-3 text-base font-bold text-[#0f2133]">
                <input type="hidden" name="subscribe" value="0">
                <input
                    class="h-5 w-5 shrink-0 rounded-control border-[#933c1f] text-[#933c1f] focus:ring-[#8f3f1f]"
                    type="checkbox"
                    name="subscribe"
                    value="1"
                    @checked((bool) old('subscribe', true))
                >
                <span>Subscribe to this thread</span>
            </label>

            @if($canCreateThreadAsUnapproved ?? false)
                <label class="mt-2 flex items-start gap-2 rounded-surface border border-[#d8dce2] bg-[#f8f6f1] px-3 py-2 text-sm text-[#304458]">
                    <input
                        class="mt-1 rounded-control border-[#d8dce2] text-[#933c1f] focus:ring-[#8f3f1f]"
                        type="checkbox"
                        name="post_as_unapproved"
                        value="1"
                        @checked(old('post_as_unapproved'))
                    >
                    <span>
                        <span class="block font-semibold text-[#0f2133]">Post as unapproved</span>
                        <span class="block text-[#5c6570]">Create this thread in the moderation queue instead of publishing it immediately.</span>
                    </span>
                </label>
            @endif

            @if($canLockThreadOnCreate ?? false)
                <label class="mt-2 flex items-start gap-2 rounded-surface border border-[#d8dce2] bg-[#f8f6f1] px-3 py-2 text-sm text-[#304458]">
                    <input
                        class="mt-1 rounded-control border-[#d8dce2] text-[#933c1f] focus:ring-[#8f3f1f]"
                        type="checkbox"
                        name="lock_thread"
                        value="1"
                        @checked(old('lock_thread'))
                    >
                    <span>
                        <span class="block font-semibold text-[#0f2133]">Lock thread</span>
                        <span class="block text-[#5c6570]">Publish the thread with replies disabled. It can be unlocked later from the moderation menu.</span>
                    </span>
                </label>
            @endif

            @if($canCreatePollWithThread ?? false)
                <label class="mt-2 flex items-start gap-2 rounded-surface border border-[#d8dce2] bg-[#f8f6f1] px-3 py-2 text-sm text-[#304458]">
                    <input
                        class="mt-1 rounded-control border-[#d8dce2] text-[#933c1f] focus:ring-[#8f3f1f]"
                        type="checkbox"
                        name="create_poll"
                        value="1"
                        data-poll-toggle
                        @checked(old('create_poll'))
                    >
                    <span>
                        <span class="block font-semibold text-[#0f2133]">Add a poll to this thread</span>
                        <span class="block text-[#5c6570]">Attach a poll with one or more questions.</span>
                    </span>
                </label>
                <div data-poll-toggle-fields @unless(old('create_poll')) hidden @endunless class="mt-1 rounded-surface border border-[#d8dce2] bg-[#f8f6f1] p-3">
                    @include('thread.partials.poll_builder_fields', ['pollFieldsDisabled' => ! old('create_poll')])
                </div>
            @endif

            <div class="mt-2 flex items-center gap-2">
                <button type="submit" class="rounded-surface border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ $threadRequiresApproval ? 'Submit Thread for Approval' : 'Create Thread' }}</button>
                <a href="{{ route('forums.show', $forum) }}" class="inline-flex items-center rounded-surface border border-[#d8dce2] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Cancel</a>
            </div>
        </form>
    </div>
@endsection
