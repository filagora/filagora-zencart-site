@extends('layouts.main')

@section('title', 'Members / Contact')
@section('main_class', 'bg-white')

@section('content')
    <div class="mx-auto max-w-[1320px] px-6 py-8 md:px-10">
        <header class="mb-5">
            <h1 class="text-3xl font-semibold text-[#0f2133]">Members / Contact</h1>
            <p class="mt-1 text-sm text-[#5c6570]">Browse member profiles and reach out through private messages from each member profile.</p>
        </header>

        @if(session('members_status'))
            <div class="border border-[#b9d6bd] bg-[#f1fbf2] px-3 py-2 text-sm text-[#1f5130]" role="status" aria-live="polite">{{ session('members_status') }}</div>
        @endif

        <section class="mt-4 grid gap-[14px]">
            <details class="overflow-hidden border border-[#d8dce2] bg-white" open>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 border-b border-[#d8dce2] bg-white px-4 py-[14px] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f] [&::-webkit-details-marker]:hidden">
                    <span class="inline-flex items-center gap-2 text-base font-bold text-[#243342] before:text-[13px] before:opacity-85 before:content-['▾'] md:text-lg">Contacts</span>
                    <span class="inline-flex h-7 min-w-9 items-center justify-center rounded-pill border border-[#bf9676] bg-[#fbf5ef] px-[10px] text-xs font-bold tracking-[0.02em] text-[#8f3f1f]">{{ number_format((int) $contacts->count()) }}</span>
                </summary>
                <div class="p-3 sm:p-[14px]">
                    @if ($contacts->isEmpty())
                        <p class="mb-[10px] text-sm text-[#5c6570]">You have no contacts yet.</p>
                    @else
                        <div class="overflow-hidden border border-[#d8dce2] bg-white">
                            <div class="hidden gap-3 border-b border-[#d8dce2] bg-[#f8f6f1] px-[14px] py-[11px] text-[11px] font-bold uppercase tracking-[0.07em] text-[#5c6570] md:grid md:grid-cols-[minmax(0,1.15fr)_minmax(0,1.7fr)_120px]">
                                <div>Username</div>
                                <div>Contact</div>
                                <div>Profile</div>
                            </div>
                            @foreach ($contacts as $contact)
                                @php($contactUser = $contact->contactUser)
                                @if ($contactUser)
                                    <article class="grid gap-2 border-t border-[#d8dce2] bg-white px-[14px] py-[11px] first:border-t-0 md:grid-cols-[minmax(0,1.15fr)_minmax(0,1.7fr)_120px] md:items-center md:gap-3">
                                        <h3 class="m-0 text-base font-bold leading-[1.2] text-[#0f2133]">
                                            <a class="text-[#0f2133] hover:text-[#8f3f1f] hover:underline focus-visible:text-[#8f3f1f] focus-visible:underline" href="{{ route('members.show', $contactUser) }}">
                                                {{ $contactUser->username ?: $contactUser->name ?: 'User #'.$contactUser->id }}
                                            </a>
                                        </h3>
                                        <p class="m-0 text-sm leading-[1.25] text-[#5c6570]">
                                            <strong class="font-semibold text-[#304458]">Name:</strong> {{ $contactUser->name ?: 'Member' }}
                                        </p>
                                        <div class="inline-flex flex-wrap items-center justify-start gap-1.5 md:justify-end">
                                            @php($contactUsername = trim((string) ($contactUser->username ?? '')))
                                            @if ((int) $contactUser->id !== (int) $viewerUserId && $contactUsername !== '' && $contactUser->allow_private_messages)
                                                <a class="inline-flex items-center justify-center rounded-control border border-[#cabca8] bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-[0.04em] text-[#5c6570] hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" href="{{ route('messages.compose', ['to' => $contactUsername]) }}">PM</a>
                                            @endif
                                            <a class="inline-flex items-center justify-center rounded-control border border-[#cabca8] bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-[0.04em] text-[#5c6570] hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" href="{{ route('members.show', $contactUser) }}">View</a>
                                            <form
                                                method="POST"
                                                action="{{ route('members.contacts.destroy', $contactUser) }}"
                                                class="m-0"
                                                data-contact-remove-form
                                                data-contact-name="{{ $contactUser->username ?: $contactUser->name ?: 'this contact' }}"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="return_q" value="{{ request()->query('q', '') }}">
                                                <input type="hidden" name="return_page" value="{{ request()->query('page', 1) }}">
                                                <button type="submit" class="inline-flex items-center justify-center rounded-control border border-[#c94a41] bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-[0.04em] text-[#c94a41] hover:bg-[#c94a41] hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#bf1f2f]">Remove</button>
                                            </form>
                                        </div>
                                    </article>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </details>

            <details class="overflow-hidden border border-[#d8dce2] bg-white" open>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 border-b border-[#d8dce2] bg-white px-4 py-[14px] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f] [&::-webkit-details-marker]:hidden">
                    <span class="inline-flex items-center gap-2 text-base font-bold text-[#243342] before:text-[13px] before:opacity-85 before:content-['▾'] md:text-lg">All Members</span>
                    <span class="inline-flex h-7 min-w-9 items-center justify-center rounded-pill border border-[#bf9676] bg-[#fbf5ef] px-[10px] text-xs font-bold tracking-[0.02em] text-[#8f3f1f]" id="members-total-count">{{ number_format((int) $members->total()) }}</span>
                </summary>
                <div class="p-3 sm:p-[14px]">
                    <form method="GET" action="{{ route('members.index') }}" class="mb-3 flex flex-col items-stretch gap-2 sm:flex-row sm:flex-wrap sm:items-center" id="members-search-form">
                        <label for="members-search-input" class="sr-only">Search members</label>
                        <input
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Search by username or name"
                            class="w-full rounded-surface border border-[#cabca8] bg-white px-3 py-2.5 text-[#0f2133] sm:min-w-[280px] sm:max-w-[560px] sm:flex-1"
                            id="members-search-input"
                            aria-controls="members-results"
                            autocomplete="off"
                        />
                        <button type="submit" class="inline-flex items-center justify-center rounded-surface border border-[#8f3f1f] bg-[#8f3f1f] px-3.5 py-2.5 text-sm font-bold text-white transition hover:border-[#7f3219] hover:bg-[#7f3219] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Search</button>
                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-[#304458]">
                            <input type="checkbox" name="exact" value="1" id="members-search-exact" @checked($exactMatch)>
                            Exact match
                        </label>
                    </form>
                    <p id="members-search-status" class="sr-only" role="status" aria-live="polite"></p>
                    @include('members.partials.all_members_results', [
                        'members' => $members,
                        'search' => $search,
                        'exactMatch' => $exactMatch,
                        'contactUserIds' => $contactUserIds,
                        'viewerUserId' => $viewerUserId,
                    ])
                </div>
            </details>
        </section>

        <div id="contact-remove-modal" class="fixed inset-0 z-[1200] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="contact-remove-modal-title" aria-describedby="contact-remove-modal-description">
            <div class="absolute inset-0 bg-[rgba(12,10,8,0.58)]" data-contact-remove-cancel></div>
            <div class="relative z-[1] w-full max-w-md border border-[#d8dce2] bg-white p-4 shadow-[0_30px_60px_rgba(0,0,0,0.32)]">
                <h2 id="contact-remove-modal-title" class="text-lg font-semibold text-[#0f2133]">Remove Contact</h2>
                <p id="contact-remove-modal-description" class="mt-2 text-sm text-[#5c6570]">Are you sure you want to remove this contact?</p>
                <div class="mt-4 flex items-center justify-end gap-2">
                    <button type="button" class="inline-flex items-center rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#0f2133] transition hover:border-[#b9a68d] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" data-contact-remove-cancel>Cancel</button>
                    <button type="button" class="inline-flex items-center rounded-surface border border-[#bf1f2f] bg-[#dc3545] px-3 py-2 text-sm font-semibold text-white transition hover:border-[#9d1826] hover:bg-[#bf1f2f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#bf1f2f]" id="contact-remove-confirm">Remove</button>
                </div>
            </div>
        </div>
    </div>
@endsection
