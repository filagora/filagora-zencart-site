@extends('layouts.main')

@section('title')
AI Chat
@endsection

@section('main_class', 'bg-white')

@section('content')
    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
        <section class="mt-[18px] grid gap-3">
            <header class="flex items-center justify-between gap-2.5">
                <div>
                    <h1 class="ui-page-title">AI Chat</h1>
                    <p class="ui-page-subtitle mt-2">Ask about forum threads, posts, and plugins - answers are grounded in this site's own content where possible.</p>
                </div>
                <a href="{{ route('ai-chat.index') }}" class="inline-flex items-center rounded-pill border border-[#933c1f] bg-[#933c1f] px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">New chat</a>
            </header>

            <div class="grid gap-3 md:grid-cols-[260px_1fr]" data-ai-chat-root data-conversation-id="{{ $conversation?->id }}">
                <nav class="grid content-start gap-1.5 border border-[#d8dce2] bg-[#f8f6f1] p-2.5" aria-label="Your AI chat conversations">
                    @forelse($conversations as $conversationItem)
                        <div class="flex items-center gap-1">
                            <a
                                href="{{ route('ai-chat.show', $conversationItem) }}"
                                class="flex-1 truncate rounded-surface px-2.5 py-2 text-sm {{ $conversation?->id === $conversationItem->id ? 'bg-white font-semibold text-[#0f2133]' : 'text-[#5c6570] hover:bg-white hover:text-[#0f2133]' }}"
                                @if($conversation?->id === $conversationItem->id) aria-current="page" @endif
                            >
                                {{ $conversationItem->title ?: 'New conversation' }}
                            </a>
                            <form method="post" action="{{ route('ai-chat.destroy', $conversationItem) }}" data-lock-on-submit>
                                @csrf
                                <button type="submit" class="inline-flex h-7 w-7 items-center justify-center rounded-pill text-sm leading-none text-[#5c6570] transition hover:bg-white hover:text-[#933c1f]" aria-label="Delete conversation {{ $conversationItem->title ?: 'New conversation' }}">×</button>
                            </form>
                        </div>
                    @empty
                        <p class="px-2.5 py-2 text-sm text-[#5c6570]">No conversations yet.</p>
                    @endforelse
                </nav>

                <div class="flex min-h-[480px] flex-col border border-[#d8dce2] bg-white">
                    <div class="flex-1 space-y-2.5 overflow-y-auto p-3.5" data-ai-chat-messages>
                        @forelse($messages as $message)
                            <article class="max-w-[80ch] border px-3 py-2.5 {{ $message->role->value === 'user' ? 'ml-auto border-[#cabca8] bg-[#f8f6f1]' : 'border-[#d8dce2] bg-white' }}">
                                <p class="ui-small-meta m-0">{{ $message->role->label() }}</p>
                                <div class="mt-1.5 whitespace-pre-wrap text-sm text-[#0f2133] {{ $message->role->value === 'user' ? '' : 'forum-rendered-content' }}" @if($message->role->value !== 'user') data-ai-chat-markdown @endif>{{ $message->content }}</div>
                                @if(!empty($message->retrieved_context))
                                    <ul class="mt-2 grid gap-1 text-xs text-[#5c6570]">
                                        @foreach($message->retrieved_context as $citation)
                                            <li><a href="{{ $citation['url'] }}" class="underline underline-offset-2 hover:text-[#8f3f1f]">{{ $citation['title'] }}</a></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </article>
                        @empty
                            <p class="text-sm text-[#5c6570]" data-ai-chat-empty-state>Ask a question to start this conversation.</p>
                        @endforelse
                    </div>

                    <form
                        class="flex items-end gap-2 border-t border-[#d8dce2] p-3"
                        data-ai-chat-form
                        action="{{ $conversation ? route('ai-chat.messages.store', $conversation) : route('ai-chat.store') }}"
                    >
                        @csrf
                        <label class="flex-1">
                            <span class="sr-only">Message</span>
                            <textarea
                                name="message"
                                rows="2"
                                maxlength="4000"
                                required
                                class="w-full resize-none rounded-surface border border-[#d8dce2] px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                                placeholder="Ask about a thread, post, or plugin..."
                                data-ai-chat-input
                            ></textarea>
                        </label>
                        <button type="submit" class="rounded-pill border border-[#933c1f] bg-[#933c1f] px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-ai-chat-submit>
                            Send
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection
