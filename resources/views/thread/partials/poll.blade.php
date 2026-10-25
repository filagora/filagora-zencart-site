@php
    $canSeeResults = ($hasVotedInPoll ?? false) || ($pollResultsVisible ?? false) || $poll->isClosed();
    $canVotePoll = auth()->check() && ! ($hasVotedInPoll ?? false) && $poll->isOpen();
@endphp
<section id="poll" class="mb-4 border border-[#d8dce2] bg-white p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-xl font-semibold text-[#0f2133]">Poll</h2>
        <span class="{{ $threadPinBadgeClass }}">{{ $poll->status->label() }}</span>
    </div>

    @if($canSeeResults)
        <div class="mt-3 grid gap-4">
            @foreach($poll->questions as $question)
                <div>
                    <p class="font-semibold text-[#0f2133]">{{ $question->prompt }}</p>
                    @php
                        $questionVoteCount = max(1, $question->options->sum('answers_count'));
                    @endphp
                    <ul class="mt-2 grid gap-1.5">
                        @foreach($question->options as $option)
                            @php
                                $optionVoteCount = (int) ($option->answers_count ?? 0);
                                $percentage = (int) round(($optionVoteCount / $questionVoteCount) * 100);
                            @endphp
                            <li class="text-sm text-[#304458]">
                                <div class="flex items-center justify-between gap-2">
                                    <span>{{ $option->label }}</span>
                                    <span class="text-xs text-[#5c6570]">{{ $optionVoteCount }} ({{ $percentage }}%)</span>
                                </div>
                                <div class="mt-1 h-2 rounded-pill bg-[#ececed]">
                                    <div class="h-2 rounded-pill bg-[#933c1f]" style="width: {{ $percentage }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif

    @if($canVotePoll)
        {{-- Staff previewing results early ($canSeeResults above) still get
             the voting form here — seeing the tally shouldn't be the thing
             that stops you from casting a vote. --}}
        @if($canSeeResults)
            <h3 class="mt-4 text-sm font-semibold text-[#5c6570]">Cast your vote</h3>
        @endif
        <form method="post" action="{{ route('polls.vote', $poll) }}" class="mt-3 grid gap-4" data-lock-on-submit>
            @csrf
            @foreach($poll->questions as $question)
                <fieldset class="grid gap-1.5">
                    <legend class="font-semibold text-[#0f2133]">
                        {{ $question->prompt }}
                        @if($question->is_multi_select)
                            <span class="text-xs font-normal text-[#5c6570]">(select all that apply)</span>
                        @endif
                    </legend>
                    @foreach($question->options as $option)
                        <label class="flex items-center gap-2 text-sm text-[#304458]">
                            <input
                                type="{{ $question->is_multi_select ? 'checkbox' : 'radio' }}"
                                name="poll_answers[{{ $question->id }}][]"
                                value="{{ $option->id }}"
                                {{ $question->is_multi_select ? '' : 'required' }}
                                class="{{ $question->is_multi_select ? 'rounded-control' : 'rounded-pill' }} border-[#cabca8] text-[#933c1f] focus:ring-[#8f3f1f]"
                            >
                            {{ $option->label }}
                        </label>
                    @endforeach
                </fieldset>
            @endforeach
            <div>
                <button type="submit" class="{{ $threadPrimaryActionClass }}">Vote</button>
            </div>
        </form>
    @elseif(!auth()->check() && !$canSeeResults)
        <p class="mt-3 text-sm text-[#5c6570]">
            <button type="button" class="{{ $threadActionButtonClass }}" data-auth-modal-url="/login">Log in to vote</button>
        </p>
    @endif

    @if($canModerateThread ?? false)
        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-[#d8d1c8] pt-3">
            @unless($poll->hasVotes())
                <a href="{{ route('polls.edit', $poll) }}" class="{{ $threadActionButtonClass }}">Edit Poll</a>
            @endunless
            <form method="post" action="{{ route('polls.moderate', $poll) }}">
                @csrf
                <input type="hidden" name="action" value="{{ $poll->isOpen() ? 'close' : 'reopen' }}">
                <button type="submit" class="{{ $threadActionButtonClass }}">
                    {{ $poll->isOpen() ? 'Close Poll' : 'Reopen Poll' }}
                </button>
            </form>
        </div>
    @endif
</section>
