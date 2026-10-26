<?php

namespace App\Http\Requests;

use Filagora\Forums\Models\Poll;
use Illuminate\Foundation\Http\FormRequest;

class SubmitPollVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every question is required (a vote is all-or-nothing), and each
     * question's answer is validated against that question's own option
     * IDs only — so an option belonging to a different question can't be
     * submitted under the wrong key. size:1 vs a min:1 array is what
     * enforces the question's single-/multi-select rule.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Poll $poll */
        $poll = $this->route('poll');

        $rules = ['poll_answers' => ['required', 'array']];

        foreach ($poll->questions as $question) {
            $key = "poll_answers.{$question->id}";
            $optionIds = $question->options->pluck('id')->implode(',');

            $rules[$key] = $question->is_multi_select
                ? ['required', 'array', 'min:1']
                : ['required', 'array', 'size:1'];
            $rules["{$key}.*"] = ['integer', "in:{$optionIds}"];
        }

        return $rules;
    }
}
