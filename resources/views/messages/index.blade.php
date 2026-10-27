@extends('layouts.main')

@section('title')
Messages
@endsection

@section('main_class', 'bg-white')

@section('content')
    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
        @include('partials.site_guest_intro')

        <section class="mt-[18px] grid gap-3">
            <header class="flex items-center justify-between gap-2.5">
                <div>
                    <h1 class="ui-page-title">Private Messages</h1>
                    <p class="ui-page-subtitle mt-2">Manage conversations, organize folders, and keep important replies easy to find.</p>
                </div>
                <a href="{{ route('messages.compose') }}" class="inline-flex items-center rounded-pill border border-[#933c1f] bg-[#933c1f] px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Compose</a>
            </header>

            @if(session('message_status'))
                <div class="border border-[#b9d6bd] bg-[#f1fbf2] px-3 py-2 text-sm text-[#1f5130]" role="status" aria-live="polite">{{ session('message_status') }}</div>
            @endif

            <nav class="inline-flex flex-wrap gap-2">
                @foreach($folders as $folderItem)
                    <div class="inline-flex items-center gap-1">
                        <a
                            href="{{ route('messages.index', ['folder' => $folderItem->slug]) }}"
                            class="inline-flex items-center gap-1.5 rounded-pill border px-3 py-1.5 text-xs font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f] {{ $folder->id === $folderItem->id ? 'border-[#933c1f] text-[#933c1f]' : 'border-[#cabca8] text-[#5c6570] hover:border-[#b9a68d] hover:text-[#0f2133]' }}"
                            @if($folder->id === $folderItem->id) aria-current="page" @endif
                        >
                            {{ $folderItem->name }}
                        </a>
                        @if(!$folderItem->is_system)
                            <form method="post" action="{{ route('messages.folders.delete', $folderItem) }}">
                                @csrf
                                <button type="submit" class="inline-flex h-6 w-6 items-center justify-center rounded-pill border border-[#d8dce2] bg-white text-sm leading-none text-[#5c6570] transition hover:border-[#b9a68d] hover:text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" aria-label="Delete folder {{ $folderItem->name }}">×</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </nav>

            <form method="post" action="{{ route('messages.folders.store') }}" class="inline-flex items-center gap-2">
                @csrf
                <label for="messages-folder-name" class="sr-only">New folder name</label>
                <input
                    id="messages-folder-name"
                    class="rounded-surface border border-[#d8dce2] bg-white px-2.5 py-2 text-sm text-[#0f2133] placeholder:text-[#5c6570] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                    type="text"
                    name="name"
                    placeholder="New folder name"
                    required
                    aria-required="true"
                    @if($errors->has('name')) aria-describedby="messages-folder-name-error" @endif
                    @if($errors->has('name')) aria-invalid="true" @endif
                >
                <button type="submit" class="rounded-surface border border-[#933c1f] bg-[#933c1f] px-2.5 py-2 text-sm font-semibold text-white transition hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Add Folder</button>
            </form>
            @error('name')
                <p id="messages-folder-name-error" class="text-sm text-[#dc3545]">{{ $message }}</p>
            @enderror

            @if($isTrashView)
                <p class="text-sm text-[#5c6570]">{{ __('private_messages.trash.retention_notice', ['days' => $trashRetentionDays]) }}</p>
                @if($participants->isNotEmpty())
                    <form method="post" action="{{ route('messages.trash.empty') }}" class="inline-flex" data-pm-confirm-form data-pm-confirm="{{ __('private_messages.confirm.empty_trash') }}">
                        @csrf
                        <button type="submit" class="rounded-surface border border-[#933c1f] bg-white px-3 py-2 text-sm font-semibold text-[#933c1f] transition hover:bg-[#f7ece7] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ __('private_messages.actions.empty_trash') }}</button>
                    </form>
                @endif
            @endif

            <form method="post" action="{{ route('messages.bulk') }}" id="messages-inbox" data-pm-inbox data-pm-trash-view="{{ $isTrashView ? '1' : '0' }}">
                @csrf
                <input type="hidden" name="return_folder" value="{{ $folder->slug }}">
                <input type="hidden" name="action" value="" data-pm-action>

                @if($participants->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-2 border border-[#d8dce2] bg-[#f8f6f1] px-3 py-2" data-pm-toolbar>
                        <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#5c6570]">
                            <input type="checkbox" class="h-4 w-4" data-pm-select-all aria-label="{{ __('private_messages.actions.select_all') }}">
                            {{ __('private_messages.actions.select_all') }}
                        </label>
                        <span class="text-xs font-semibold text-[#933c1f]" data-pm-count hidden></span>

                        <span class="ml-auto inline-flex flex-wrap items-center gap-1.5">
                            @unless($isTrashView)
                                <label class="sr-only" for="pm-bulk-folder">{{ __('private_messages.actions.move_to') }}</label>
                                <select id="pm-bulk-folder" name="folder_id" class="rounded-surface border border-[#d8dce2] bg-white px-2 py-1.5 text-xs text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">
                                    @foreach($moveFolders as $moveFolder)
                                        <option value="{{ $moveFolder->id }}">{{ __('private_messages.actions.move_to') }}: {{ $moveFolder->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" name="action" value="move" class="rounded-surface border border-[#cabca8] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ __('private_messages.actions.move') }}</button>
                                <button type="submit" name="action" value="mark_read" class="rounded-surface border border-[#cabca8] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ __('private_messages.actions.mark_read') }}</button>
                                <button type="submit" name="action" value="mark_unread" class="rounded-surface border border-[#cabca8] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ __('private_messages.actions.mark_unread') }}</button>
                                <button type="submit" name="action" value="trash" class="rounded-surface border border-[#933c1f] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#933c1f] transition hover:bg-[#f7ece7] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ __('private_messages.actions.trash') }}</button>
                            @else
                                <button type="submit" name="action" value="restore" class="rounded-surface border border-[#cabca8] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#304458] transition hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ __('private_messages.actions.restore') }}</button>
                                <button type="submit" name="action" value="delete" data-pm-confirm="{{ __('private_messages.confirm.delete_permanently') }}" class="rounded-surface border border-[#933c1f] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#933c1f] transition hover:bg-[#f7ece7] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">{{ __('private_messages.actions.delete_permanently') }}</button>
                            @endunless
                            <button type="button" data-pm-clear class="rounded-surface border border-[#d8dce2] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#5c6570] transition hover:border-[#b9a68d] hover:text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" hidden>{{ __('private_messages.actions.clear_selection') }}</button>
                        </span>
                    </div>
                    @error('conversation_ids')
                        <p class="mt-2 text-sm text-[#dc3545]">{{ $message }}</p>
                    @enderror
                    @error('action')
                        <p class="mt-2 text-sm text-[#dc3545]">{{ $message }}</p>
                    @enderror
                @endif

                <div class="mt-2 grid gap-2" data-pm-list>
                    @forelse($participants as $participant)
                        @php
                            $conversation = $participant->conversation;
                            $latestMessage = $conversation?->messages->first();
                            $sender = $latestMessage?->sender;
                            $otherNames = $conversation?->participants
                                ?->reject(fn ($p) => (int) $p->user_id === (int) $participant->user_id)
                                ->map(fn ($p) => $p->user?->username ?? $p->user?->name ?? $p->user?->email)
                                ->filter()
                                ->implode(', ');
                            $snippet = \Illuminate\Support\Str::limit(trim(strip_tags((string) $latestMessage?->body)), 140);
                        @endphp
                        <article class="flex min-w-0 items-start gap-3 border px-3 py-2.5 {{ !$participant->is_read ? 'border-[#bc8a72] bg-[#f7ece7]' : 'border-[#d8dce2] bg-white' }}">
                            <label class="pt-0.5">
                                <span class="sr-only">Select conversation {{ $conversation?->subject }}</span>
                                <input type="checkbox" class="h-4 w-4" name="conversation_ids[]" value="{{ $conversation?->id }}" data-pm-checkbox>
                            </label>

                            <div class="min-w-0 flex-1">
                                <a class="ui-card-title no-underline transition hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" href="{{ route('messages.show', $conversation) }}">{{ $conversation?->subject ?? 'Conversation' }}</a>
                                <p class="ui-small-meta mt-1">
                                    {{ $otherNames !== '' ? $otherNames : 'Just you' }}
                                    · {{ trans_choice('{1} 1 message|[2,*] :count messages', (int) ($conversation?->messages_count ?? 0), ['count' => (int) ($conversation?->messages_count ?? 0)]) }}
                                </p>
                                @if($snippet !== '')
                                    <p class="ui-small-meta mt-1 truncate">
                                        <strong>{{ $sender?->username ?? $sender?->name ?? $sender?->email ?? 'Unknown' }}:</strong>
                                        {{ $snippet }}
                                        · {{ \App\Support\LocalTime::format($latestMessage?->created_at) }}
                                    </p>
                                @endif
                            </div>

                            <details class="relative shrink-0" data-pm-row-menu>
                                <summary class="inline-flex h-7 w-7 cursor-pointer list-none items-center justify-center rounded-pill border border-[#d8dce2] bg-white text-[#5c6570] transition hover:border-[#b9a68d] hover:text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f] [&::-webkit-details-marker]:hidden" aria-label="{{ __('private_messages.actions.row_actions') }}">⋯</summary>
                                <div class="absolute right-0 top-[calc(100%+4px)] z-20 grid w-52 gap-0.5 rounded-surface border border-[#d6c7b3] bg-white p-1 shadow-[0_16px_34px_rgba(32,20,10,0.16)]">
                                    @unless($isTrashView)
                                        <button type="button" data-pm-row-action="mark_read" data-pm-id="{{ $conversation?->id }}" class="rounded-control px-2.5 py-1.5 text-left text-xs font-semibold text-[#0f2133] transition hover:bg-[#f7e8df]">{{ __('private_messages.actions.mark_read') }}</button>
                                        <button type="button" data-pm-row-action="mark_unread" data-pm-id="{{ $conversation?->id }}" class="rounded-control px-2.5 py-1.5 text-left text-xs font-semibold text-[#0f2133] transition hover:bg-[#f7e8df]">{{ __('private_messages.actions.mark_unread') }}</button>
                                        <span class="mt-1 px-2.5 pt-1 text-[10px] font-bold uppercase tracking-[0.06em] text-[#8a94a0]">{{ __('private_messages.actions.move_to') }}</span>
                                        @foreach($moveFolders as $moveFolder)
                                            <button type="button" data-pm-row-action="move" data-pm-folder-id="{{ $moveFolder->id }}" data-pm-id="{{ $conversation?->id }}" class="rounded-control px-2.5 py-1.5 text-left text-xs font-semibold text-[#0f2133] transition hover:bg-[#f7e8df]">{{ $moveFolder->name }}</button>
                                        @endforeach
                                        <button type="button" data-pm-row-action="trash" data-pm-id="{{ $conversation?->id }}" class="mt-1 rounded-control px-2.5 py-1.5 text-left text-xs font-semibold text-[#933c1f] transition hover:bg-[#f7ece7]">{{ __('private_messages.actions.trash') }}</button>
                                    @else
                                        <button type="button" data-pm-row-action="restore" data-pm-id="{{ $conversation?->id }}" class="rounded-control px-2.5 py-1.5 text-left text-xs font-semibold text-[#0f2133] transition hover:bg-[#f7e8df]">{{ __('private_messages.actions.restore') }}</button>
                                        <button type="button" data-pm-row-action="delete" data-pm-id="{{ $conversation?->id }}" data-pm-confirm="{{ __('private_messages.confirm.delete_permanently') }}" class="rounded-control px-2.5 py-1.5 text-left text-xs font-semibold text-[#933c1f] transition hover:bg-[#f7ece7]">{{ __('private_messages.actions.delete_permanently') }}</button>
                                    @endunless
                                </div>
                            </details>
                        </article>
                    @empty
                        <article class="border border-[#d8dce2] bg-white px-3 py-2.5">
                            <p>No conversations in this folder.</p>
                        </article>
                    @endforelse
                </div>

                @if($participants->hasPages())
                    <div class="mt-3">
                        {{ $participants->links() }}
                    </div>
                @endif
            </form>
        </section>
    </div>
@endsection
