{{--
    Reusable poll question/option builder, included from thread/new.blade.php
    (poll bolted onto new-thread creation), poll/create.blade.php (poll added
    to an existing thread), and poll/edit.blade.php (editing a poll's
    questions/options before it has any votes) — so the field-naming
    contract and the poll-builder.js module are only written once.

    Data-driven: renders old('poll_questions') if a submission just failed
    validation, else the passed-in $poll's existing questions/options if
    editing one, else a single blank starter question with two blank
    options (the server-side minimums — StorePollRequest /
    PollValidationRules). Callers: pass ['poll' => $poll] to pre-fill from
    an existing poll (poll/edit.blade.php); omit it to get the blank
    starter (thread/new.blade.php, poll/create.blade.php).

    Field names use a running "next index" counter embedded in the two
    <template> blocks below (__QUESTION_INDEX__ / __OPTION_INDEX__),
    swapped in by the JS on clone. Laravel's array validator tolerates the
    resulting sparse/non-contiguous keys once rows are removed, so no
    client-side reindexing is needed.
--}}
@php
    $pollQuestionsData = old('poll_questions');
    if (! is_array($pollQuestionsData)) {
        if ((isset($poll) ? $poll : null)?->relationLoaded('questions')) {
            $pollQuestionsData = $poll->questions->map(fn ($question) => [
                'prompt' => $question->prompt,
                'is_multi_select' => $question->is_multi_select,
                'options' => $question->options->map(fn ($option) => ['label' => $option->label])->all(),
            ])->all();
        } else {
            $pollQuestionsData = [
                ['prompt' => '', 'is_multi_select' => false, 'options' => [['label' => ''], ['label' => '']]],
            ];
        }
    }
@endphp
<div data-poll-builder class="grid gap-3">
    <div data-poll-questions class="grid gap-3">
        @foreach($pollQuestionsData as $questionIndex => $questionData)
            @php
                $optionsData = (array) ($questionData['options'] ?? []);
            @endphp
            <fieldset data-poll-question data-question-index="{{ $questionIndex }}" data-next-option-index="{{ count($optionsData) }}" class="grid gap-2 rounded-surface border border-[#cabca8] bg-[#ececed] p-3">
                <label class="text-sm font-semibold text-[#5c6570]">Question {{ $loop->iteration }}</label>
                <input
                    class="rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                    type="text"
                    name="poll_questions[{{ $questionIndex }}][prompt]"
                    value="{{ $questionData['prompt'] ?? '' }}"
                    required
                    @disabled($pollFieldsDisabled ?? false)
                >
                <label class="flex items-center gap-2 text-sm text-[#304458]">
                    <input class="rounded-control border-[#cabca8] text-[#933c1f] focus:ring-[#8f3f1f]" type="checkbox" name="poll_questions[{{ $questionIndex }}][is_multi_select]" value="1" @checked($questionData['is_multi_select'] ?? false) @disabled($pollFieldsDisabled ?? false)>
                    Allow selecting multiple answers
                </label>

                <div data-poll-options class="grid gap-2">
                    @foreach($optionsData as $optionIndex => $optionData)
                        <div data-poll-option class="flex items-center gap-2">
                            <input
                                class="w-full rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                                type="text"
                                name="poll_questions[{{ $questionIndex }}][options][{{ $optionIndex }}][label]"
                                value="{{ $optionData['label'] ?? '' }}"
                                placeholder="Option {{ $loop->iteration }}"
                                required
                                @disabled($pollFieldsDisabled ?? false)
                            >
                            <button type="button" class="text-sm text-[#5c6570] hover:text-[#9f2230]" data-poll-remove-option @if(count($optionsData) <= 2) disabled @endif>Remove</button>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="justify-self-start rounded-surface border border-[#cabca8] bg-white px-2 py-1 text-xs font-semibold text-[#304458] hover:border-[#8f3f1f] hover:text-[#8f3f1f]" data-poll-add-option>Add option</button>

                <button type="button" class="justify-self-start text-sm text-[#5c6570] hover:text-[#9f2230]" data-poll-remove-question @if(count($pollQuestionsData) <= 1) disabled @endif>Remove question</button>
            </fieldset>
        @endforeach
    </div>

    <button type="button" class="justify-self-start rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm font-semibold text-[#304458] hover:border-[#8f3f1f] hover:text-[#8f3f1f]" data-poll-add-question>Add question</button>

    <template data-poll-question-template>
        <fieldset data-poll-question data-question-index="__QUESTION_INDEX__" data-next-option-index="2" class="grid gap-2 rounded-surface border border-[#cabca8] bg-[#ececed] p-3">
            <label class="text-sm font-semibold text-[#5c6570]">Question</label>
            <input
                class="rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                type="text"
                name="poll_questions[__QUESTION_INDEX__][prompt]"
                required
            >
            <label class="flex items-center gap-2 text-sm text-[#304458]">
                <input class="rounded-control border-[#cabca8] text-[#933c1f] focus:ring-[#8f3f1f]" type="checkbox" name="poll_questions[__QUESTION_INDEX__][is_multi_select]" value="1">
                Allow selecting multiple answers
            </label>

            <div data-poll-options class="grid gap-2">
                <div data-poll-option class="flex items-center gap-2">
                    <input
                        class="w-full rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                        type="text"
                        name="poll_questions[__QUESTION_INDEX__][options][0][label]"
                        placeholder="Option 1"
                        required
                    >
                    <button type="button" class="text-sm text-[#5c6570] hover:text-[#9f2230]" data-poll-remove-option disabled>Remove</button>
                </div>
                <div data-poll-option class="flex items-center gap-2">
                    <input
                        class="w-full rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                        type="text"
                        name="poll_questions[__QUESTION_INDEX__][options][1][label]"
                        placeholder="Option 2"
                        required
                    >
                    <button type="button" class="text-sm text-[#5c6570] hover:text-[#9f2230]" data-poll-remove-option disabled>Remove</button>
                </div>
            </div>
            <button type="button" class="justify-self-start rounded-surface border border-[#cabca8] bg-white px-2 py-1 text-xs font-semibold text-[#304458] hover:border-[#8f3f1f] hover:text-[#8f3f1f]" data-poll-add-option>Add option</button>

            <button type="button" class="justify-self-start text-sm text-[#5c6570] hover:text-[#9f2230]" data-poll-remove-question>Remove question</button>
        </fieldset>
    </template>

    <template data-poll-option-template>
        <div data-poll-option class="flex items-center gap-2">
            <input
                class="w-full rounded-surface border border-[#cabca8] bg-white px-3 py-2 text-sm text-[#0f2133] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f3f1f]"
                type="text"
                name="poll_questions[__QUESTION_INDEX__][options][__OPTION_INDEX__][label]"
                placeholder="Option"
                required
            >
            <button type="button" class="text-sm text-[#5c6570] hover:text-[#9f2230]" data-poll-remove-option>Remove</button>
        </div>
    </template>
</div>
