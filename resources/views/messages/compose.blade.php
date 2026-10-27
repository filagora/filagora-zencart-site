@extends('layouts.main')

@section('title', 'Compose Message')
@section('main_class', 'bg-white')

@section('content')
    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
        @include('partials.site_guest_intro')

        <section class="mt-[18px] grid gap-3">
        <header class="flex items-center justify-between gap-2.5">
            <div>
                <h1 class="ui-page-title">Compose Message</h1>
                <p class="ui-page-subtitle mt-2">Start a new conversation with one or more members.</p>
            </div>
            <a href="{{ route('messages.index') }}" class="inline-flex items-center rounded-pill border border-[#933c1f] bg-[#933c1f] px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Back to Inbox</a>
        </header>

        <form method="post" action="{{ route('messages.store') }}" class="grid max-w-[760px] gap-2.5">
            @csrf
            <p id="compose-required-help" class="mb-1 text-sm text-[#5c6570]">Fields marked “(required)” must be completed.</p>
            <label class="relative grid gap-1.5">
                <span class="text-sm font-semibold text-[#5c6570]">To (comma-separated usernames) (required)</span>
                <input
                    class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                    type="text"
                    name="to"
                    id="messages-to-input"
                    value="{{ old('to', trim((string) request()->query('to', ''))) }}"
                    autocomplete="off"
                    required
                    aria-required="true"
                    aria-describedby="compose-required-help{{ $errors->has('to') ? ' compose-to-error' : '' }}"
                    @if($errors->has('to')) aria-invalid="true" @endif
                >
                <div id="messages-recipient-lookup" class="absolute left-0 right-0 top-[calc(100%+4px)] z-30 max-h-[220px] overflow-auto border border-[#d8dce2] bg-white shadow-[0_8px_18px_rgba(0,0,0,0.14)]" hidden></div>
            </label>
            @error('to')
                <p id="compose-to-error" class="text-sm text-[#dc3545]">{{ $message }}</p>
            @enderror

            <label class="grid gap-1.5">
                <span class="text-sm font-semibold text-[#5c6570]">Subject (required)</span>
                <input
                    class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                    type="text"
                    name="subject"
                    value="{{ old('subject') }}"
                    required
                    aria-required="true"
                    aria-describedby="compose-required-help{{ $errors->has('subject') ? ' compose-subject-error' : '' }}"
                    @if($errors->has('subject')) aria-invalid="true" @endif
                >
            </label>
            @error('subject')
                <p id="compose-subject-error" class="text-sm text-[#dc3545]">{{ $message }}</p>
            @enderror

            <label class="grid gap-1.5">
                <span class="text-sm font-semibold text-[#5c6570]">Message (required)</span>
                <div class="overflow-hidden border border-[#d8dce2] bg-white">
                    <x-markdown-toolbar target="compose-body" />
                    <div class="grid md:grid-cols-[minmax(0,1fr)_136px]">
                        <textarea
                            id="compose-body"
                            name="body"
                            rows="8"
                            class="min-h-[200px] w-full resize-y border-0 bg-transparent px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#8f3f1f] md:border-r md:border-[#cabca8]"
                            required
                            aria-required="true"
                            aria-describedby="compose-required-help{{ $errors->has('body') ? ' compose-body-error' : '' }}"
                            @if($errors->has('body')) aria-invalid="true" @endif
                        >{{ old('body') }}</textarea>
                        <div class="grid grid-cols-3 content-start gap-1 border-t border-[#cabca8] p-2 md:border-l md:border-t-0 md:border-[#cabca8]">
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="compose-body" data-smiley="🙂" title=":)">🙂</button>
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="compose-body" data-smiley="🙁" title=":(">🙁</button>
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="compose-body" data-smiley="😉" title=";)">😉</button>
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="compose-body" data-smiley="😄" title=":D">😄</button>
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="compose-body" data-smiley="😛" title=":P">😛</button>
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="compose-body" data-smiley="😮" title=":o">😮</button>
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="compose-body" data-smiley="👍" title=":thumbsup:">👍</button>
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="compose-body" data-smiley="👎" title=":thumbsdown:">👎</button>
                        </div>
                    </div>
                </div>
            </label>
            @error('body')
                <p id="compose-body-error" class="text-sm text-[#dc3545]">{{ $message }}</p>
            @enderror

            <button class="w-fit rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-markdown-preview data-markdown-source="compose-body" data-markdown-preview-panel="compose-body-preview">Preview</button>
            <section id="compose-body-preview" class="rounded-surface border border-[#cabca8] bg-[#f8f6f1] p-3" hidden>
                <h3 class="text-sm font-semibold text-[#304458]">Preview</h3>
                <div class="mt-2 text-sm text-[#304458]" data-reply-preview-content></div>
            </section>

            <button type="submit" class="w-fit rounded-surface border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Send Message</button>
        </form>
        </section>
    </div>

@endsection
