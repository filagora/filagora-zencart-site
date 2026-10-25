@extends('layouts.main')

@section('title', 'Edit Post')
@section('main_class', 'bg-[#dddddf]')

@section('content')
    <div class="mx-auto max-w-[1100px] px-6 py-8 md:px-10">
        @include('forum.partials.header_nav')
        <div class="mb-3 text-sm text-[#5c6570]">
            <a class="text-[#8f3f1f] hover:underline" href="{{ route('forums.index') }}">Forums</a>
            / <a class="text-[#8f3f1f] hover:underline" href="{{ route('forums.show', $thread->forum) }}">{{ \App\Support\LegacyTextDecoder::decode($thread->forum->name) }}</a>
            / <a class="text-[#8f3f1f] hover:underline" href="{{ route('threads.show', $thread) }}">{{ \App\Support\LegacyTextDecoder::decode($thread->title) }}</a>
            / Edit Post
        </div>

        <header class="mb-4">
            <h1 class="text-3xl font-semibold text-[#0f2133]">Edit Post</h1>
            <p class="mt-1 text-sm text-[#5c6570]">
                Editing post by {{ $post->author?->username ?: $post->author?->name ?: $post->author?->email ?: 'Unknown author' }}.
            </p>
        </header>

        <div class="mb-6">
            @include('forum.partials.search_bar')
        </div>

        @if($errors->any())
            <div class="mb-3 rounded-surface border border-[#f1b6bc] bg-[#fff1f2] px-3 py-2 text-sm text-[#9f2230]" role="alert" aria-live="polite">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="post" action="{{ route('posts.update', $post) }}" enctype="multipart/form-data" class="rounded-surface border border-[#cabca8] bg-[#ececed] p-4" data-lock-on-submit>
            @csrf
            @method('PUT')
            <input type="hidden" name="return_to" value="{{ $returnTo }}">
            <p id="post-edit-required-help" class="text-sm text-[#5c6570]">Fields marked “(required)” must be completed.</p>

            <label for="post-body" class="text-sm font-semibold text-[#5c6570]">Post Body (required)</label>
            <div class="mt-2 overflow-hidden rounded-control border border-[#cabca8] bg-[#ececed]">
                <x-markdown-toolbar target="post-body" />
                <div class="grid md:grid-cols-[minmax(0,1fr)_136px]">
                    <textarea
                        id="post-body"
                        name="body"
                        rows="16"
                        class="min-h-[260px] w-full resize-y border-0 bg-transparent px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#8f3f1f] md:border-r md:border-[#cabca8]"
                        required
                        aria-required="true"
                        aria-describedby="post-edit-required-help{{ $errors->has('body') ? ' post-body-error' : '' }}"
                        @if($errors->has('body')) aria-invalid="true" @endif
                    >{{ old('body', $editorBody) }}</textarea>
                    <div class="grid grid-cols-3 content-start gap-1 border-t border-[#cabca8] p-2 md:border-l md:border-t-0 md:border-[#cabca8]">
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="post-body" data-smiley="🙂" title=":)">🙂</button>
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="post-body" data-smiley="🙁" title=":(">🙁</button>
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="post-body" data-smiley="😉" title=";)">😉</button>
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="post-body" data-smiley="😄" title=":D">😄</button>
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="post-body" data-smiley="😛" title=":P">😛</button>
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="post-body" data-smiley="😮" title=":o">😮</button>
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="post-body" data-smiley="👍" title=":thumbsup:">👍</button>
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="post-body" data-smiley="👎" title=":thumbsdown:">👎</button>
                    </div>
                </div>
            </div>
            <p class="mt-2 text-sm text-[#5c6570]">Uses Markdown, with supported extensions for underline, alignment, color, size, font, spoilers, video, and attachments.</p>
            @if($errors->has('body'))
                <p id="post-body-error" class="mt-2 text-sm text-[#dc3545]">{{ $errors->first('body') }}</p>
            @endif

            @if($post->attachments->isNotEmpty())
                <p class="mt-4 text-sm font-semibold text-[#5c6570]">Existing attachments</p>
                <ul class="mt-1 grid gap-1.5">
                    @foreach($post->attachments as $attachment)
                        <li class="flex items-center gap-2 text-sm text-[#304458]">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="remove_attachments[]" value="{{ $attachment->id }}">
                                <span>Remove</span>
                            </label>
                            <span class="truncate">{{ $attachment->original_filename ?: 'Attachment #'.$attachment->id }}</span>
                            <span class="shrink-0 text-xs text-[#5c6570]">({{ $attachment->displaySize() }})</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <label for="post-attachments" class="mt-4 inline-flex text-sm font-semibold text-[#5c6570]">Add attachments (optional)</label>
            <input
                class="mt-1 w-full rounded-surface border border-[#cabca8] bg-[#ececed] px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                id="post-attachments"
                type="file"
                name="attachments[]"
                multiple
                accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.zip,.log"
                aria-describedby="post-attachments-help{{ $errors->has('attachments') ? ' post-attachments-error' : '' }}"
                @if($errors->has('attachments')) aria-invalid="true" @endif
            >
            <p id="post-attachments-help" class="text-sm text-[#5c6570]">Up to 5 files, 10MB each. Allowed: jpg, jpeg, png, gif, webp, pdf, txt, zip, log.</p>
            @if($errors->has('attachments'))
                <p id="post-attachments-error" class="text-sm text-[#dc3545]">{{ $errors->first('attachments') }}</p>
            @endif

            <div class="mt-2 flex">
                <button
                    class="inline-flex items-center justify-center rounded-control border border-[#cabca8] bg-white px-2 py-1 text-xs font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                    data-markdown-trigger
                    type="button"
                    data-markdown-preview
                    data-markdown-source="post-body"
                    data-markdown-preview-panel="post-body-preview"
                >Preview</button>
            </div>
            <section id="post-body-preview" class="mt-3 rounded-control border border-[#cabca8] bg-[#f8f6f1] p-3" hidden>
                <h3 class="text-sm font-semibold text-[#304458]">Preview</h3>
                <div class="forum-rendered-content mt-2 text-sm text-[#304458]" data-reply-preview-content></div>
            </section>

            <div class="mt-4 flex items-center gap-2">
                <button type="submit" class="rounded-surface border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">
                    Save Changes
                </button>
                <a href="{{ $returnTo }}" class="inline-flex items-center rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
