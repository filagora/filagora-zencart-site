@extends('layouts.main')

@section('title')
{{ $conversation->subject }}
@endsection

@section('main_class', 'bg-white')

@section('content')
    @php
        $contentRenderer = app(\App\Support\FormattedContentRenderer::class);
    @endphp

    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
        <section class="mt-[18px] grid gap-3">
            <header class="flex items-center justify-between gap-2.5">
                <div>
                    <h1 class="ui-page-title">{{ $conversation->subject }}</h1>
                    <p class="ui-page-subtitle mt-2">Review the conversation and reply from the same thread.</p>
                </div>
                <a href="{{ route('messages.index', ['folder' => $participant->folder?->slug ?? 'inbox']) }}" class="inline-flex items-center rounded-pill border border-[#933c1f] bg-[#933c1f] px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Back</a>
            </header>

            @if(session('message_status'))
                <div class="border border-[#b9d6bd] bg-[#f1fbf2] px-3 py-2 text-sm text-[#1f5130]" role="status" aria-live="polite">{{ session('message_status') }}</div>
            @endif

            <div class="flex flex-wrap items-end gap-2 border border-[#d8dce2] bg-[#f8f6f1] px-3 py-2">
                <form method="post" action="{{ route('messages.move', $conversation) }}" class="inline-flex items-end gap-2">
                    @csrf
                    <label class="grid gap-1 text-xs text-[#5c6570]">
                        <span>{{ __('private_messages.actions.move_to') }}</span>
                        <select
                            class="rounded-surface border border-[#d8dce2] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                            name="folder_id"
                            required
                            aria-required="true"
                            @if($errors->has('folder_id')) aria-invalid="true" aria-describedby="message-move-folder-error" @endif
                        >
                            @foreach($moveFolders as $moveFolder)
                                <option value="{{ $moveFolder->id }}" @selected($participant->folder_id === $moveFolder->id)>{{ $moveFolder->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ __('private_messages.actions.move') }}</button>
                </form>
                @if($trashFolder)
                    <form method="post" action="{{ route('messages.move', $conversation) }}" class="inline-flex">
                        @csrf
                        <input type="hidden" name="folder_id" value="{{ $trashFolder->id }}">
                        <button type="submit" class="rounded-surface border border-[#933c1f] bg-white px-3 py-2 text-sm font-semibold text-[#933c1f] transition hover:bg-[#f7ece7] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ __('private_messages.actions.trash') }}</button>
                    </form>
                @endif
                @if($errors->has('folder_id'))
                    <p id="message-move-folder-error" class="w-full text-sm text-[#dc3545]">{{ $errors->first('folder_id') }}</p>
                @endif
            </div>

            <div class="grid gap-2.5">
                @foreach($conversation->messages as $message)
                    @php
                        $sender = $message->sender;
                    @endphp
                    <article class="border border-[#d8dce2] bg-white px-3 py-2.5">
                        <p class="ui-small-meta m-0">
                            <strong>{{ $sender?->username ?? $sender?->name ?? $sender?->email ?? 'Unknown' }}</strong>
                            · {{ \App\Support\LocalTime::format($message->created_at) }}
                        </p>
                        <div class="forum-rendered-content mt-2 whitespace-normal [&_a]:font-semibold [&_a]:underline-offset-2 [&_a]:text-[color:var(--color-site-brand-dark)] [&_a:hover]:underline [&_a:hover]:text-[color:var(--color-site-brand-text)] [&_code]:font-mono [&_code]:text-[0.92em] [&_pre]:my-2.5 [&_pre]:overflow-x-auto [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:rounded-surface [&_pre]:border [&_pre]:border-[#cabca8] [&_pre]:bg-[#e6eaef] [&_pre]:px-3.5 [&_pre]:py-3 [&_pre]:leading-[1.45]">{!! $contentRenderer->render($message->body) !!}</div>
                    </article>
                @endforeach
            </div>

            <form method="post" action="{{ route('messages.reply', $conversation) }}" class="grid max-w-[760px] gap-2.5">
                @csrf
                <p id="message-reply-required-help" class="mb-1 text-sm text-[#5c6570]">Reply message is required.</p>
                <label class="grid gap-1.5">
                    <span class="text-sm font-semibold text-[#5c6570]">Reply</span>
                <div class="overflow-hidden border border-[#d8dce2] bg-white">
                    <x-markdown-toolbar target="message-reply-body" />
                    <div class="grid md:grid-cols-[minmax(0,1fr)_136px]">
                        <textarea
                            id="message-reply-body"
                            name="body"
                            rows="6"
                            class="min-h-[180px] w-full resize-y border-0 bg-transparent px-3 py-2.5 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#8f3f1f] md:border-r md:border-[#cabca8]"
                            required
                            aria-required="true"
                            aria-describedby="message-reply-required-help{{ $errors->reply->has('body') ? ' message-reply-body-error' : '' }}"
                            @if($errors->reply->has('body')) aria-invalid="true" @endif
                        >{{ old('body') }}</textarea>
                        <div class="grid grid-cols-3 content-start gap-1 border-t border-[#cabca8] p-2 md:border-l md:border-t-0 md:border-[#cabca8]">
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="message-reply-body" data-smiley="🙂" title=":)">🙂</button>
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="message-reply-body" data-smiley="🙁" title=":(">🙁</button>
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="message-reply-body" data-smiley="😉" title=";)">😉</button>
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="message-reply-body" data-smiley="😄" title=":D">😄</button>
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="message-reply-body" data-smiley="😛" title=":P">😛</button>
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="message-reply-body" data-smiley="😮" title=":o">😮</button>
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="message-reply-body" data-smiley="👍" title=":thumbsup:">👍</button>
                            <button class="inline-flex h-8 w-8 items-center justify-center rounded-control border border-[#cabca8] bg-white text-sm transition hover:border-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-smiley-target="message-reply-body" data-smiley="👎" title=":thumbsdown:">👎</button>
                        </div>
                    </div>
                </div>
                </label>
                @error('body', 'reply')
                    <p id="message-reply-body-error" class="text-sm text-[#dc3545]">{{ $message }}</p>
                @enderror
                <button class="w-fit rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" type="button" data-markdown-preview data-markdown-source="message-reply-body" data-markdown-preview-panel="message-reply-body-preview">Preview</button>
                <section id="message-reply-body-preview" class="rounded-surface border border-[#cabca8] bg-[#f8f6f1] p-3" hidden>
                    <h3 class="text-sm font-semibold text-[#304458]">Preview</h3>
                    <div class="mt-2 text-sm text-[#304458]" data-reply-preview-content></div>
                </section>
                <button type="submit" class="w-fit rounded-surface border border-[#933c1f] bg-[#933c1f] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Send Reply</button>
            </form>
        </section>
    </div>

@endsection
