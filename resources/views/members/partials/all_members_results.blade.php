<div id="members-results" data-members-total="{{ (int) $members->total() }}">
    @if ($search !== '')
        <p class="text-sm text-[#5c6570]">Search results for "{{ $search }}".</p>
    @endif

    @if ($members->isEmpty())
        <p class="mb-[10px] text-sm text-[#5c6570]">No members found.</p>
    @else
        <div class="overflow-hidden border border-[#d8dce2] bg-white">
            <div class="hidden gap-3 border-b border-[#d8dce2] bg-[#f8f6f1] px-[14px] py-[11px] text-[11px] font-bold uppercase tracking-[0.07em] text-[#5c6570] md:grid md:grid-cols-[minmax(0,1.15fr)_minmax(0,1.7fr)_120px]">
                <div>Username</div>
                <div>Member</div>
                <div>Profile</div>
            </div>
            @foreach ($members as $member)
                <article class="grid gap-2 border-t border-[#d8dce2] bg-white px-[14px] py-[11px] first:border-t-0 md:grid-cols-[minmax(0,1.15fr)_minmax(0,1.7fr)_120px] md:items-center md:gap-3">
                    <h3 class="m-0 text-base font-bold leading-[1.2] text-[#0f2133]">
                        <a class="text-[#0f2133] hover:text-[#8f3f1f] hover:underline focus-visible:text-[#8f3f1f] focus-visible:underline" href="{{ route('members.show', $member) }}">
                            {{ $member->username ?: $member->name ?: 'User #'.$member->id }}
                        </a>
                    </h3>
                    <p class="m-0 text-sm leading-[1.25] text-[#5c6570]">
                        <strong class="font-semibold text-[#304458]">Name:</strong> {{ $member->name ?: 'Member' }}
                    </p>
                    <div class="inline-flex flex-wrap items-center justify-start gap-1.5 md:justify-end">
                        @php($memberUsername = trim((string) ($member->username ?? '')))
                        @if ((int) $member->id !== (int) $viewerUserId && $memberUsername !== '')
                            <a class="inline-flex items-center justify-center rounded-control border border-[#cabca8] bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-[0.04em] text-[#5c6570] hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" href="{{ route('messages.compose', ['to' => $memberUsername]) }}">PM</a>
                        @endif
                        <a class="inline-flex items-center justify-center rounded-control border border-[#cabca8] bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-[0.04em] text-[#5c6570] hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]" href="{{ route('members.show', $member) }}">View</a>

                        @if ((int) $member->id !== (int) $viewerUserId)
                            @if (in_array((int) $member->id, $contactUserIds ?? [], true))
                                <button type="button" class="inline-flex cursor-not-allowed items-center justify-center rounded-control border border-[#cabca8] bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-[0.04em] text-[#5c6570] opacity-65" disabled>Added</button>
                            @else
                                <form method="POST" action="{{ route('members.contacts.store', $member) }}" class="m-0">
                                    @csrf
                                    <input type="hidden" name="return_q" value="{{ $search }}">
                                    <input type="hidden" name="return_exact" value="{{ ($exactMatch ?? false) ? '1' : '' }}">
                                    <input type="hidden" name="return_page" value="{{ $members->currentPage() }}">
                                    <button type="submit" class="inline-flex items-center justify-center rounded-control border border-[#cabca8] bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-[0.04em] text-[#5c6570] hover:border-[#8f3f1f] hover:text-[#8f3f1f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]">Add Contact</button>
                                </form>
                            @endif
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    <div class="mt-[10px]">
        @include('members.partials.pagination', ['members' => $members])
    </div>
</div>
