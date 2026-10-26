<?php

namespace App\Support;

/**
 * Shared rule fragment for validating a poll's questions/options, used both
 * by StorePollRequest (dedicated "add poll to thread" form) and
 * ForumController::storeThread()'s inline validate() call (poll bolted onto
 * new-thread creation). Laravel's array validator tolerates sparse/
 * non-contiguous keys, so the add/remove-row poll builder JS never needs to
 * reindex on removal.
 */
class PollValidationRules
{
    /**
     * @return array<string, mixed>
     */
    public static function required(): array
    {
        return [
            'poll_questions' => ['required', 'array', 'min:1', 'max:20'],
            'poll_questions.*.prompt' => ['required', 'string', 'max:500'],
            'poll_questions.*.is_multi_select' => ['nullable', 'boolean'],
            'poll_questions.*.options' => ['required', 'array', 'min:2', 'max:20'],
            'poll_questions.*.options.*.label' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Same shape as required(), but only enforced when $field is truthy —
     * for forms where the poll is an optional add-on (e.g. the "create a
     * poll with this thread" checkbox on thread/new.blade.php). Every rule,
     * not just the top-level array, is conditioned on $field: the builder
     * fields stay in the DOM (just hidden) while the checkbox is off, so
     * blank poll_questions.*.prompt/options values are still submitted —
     * an unconditional 'required' on those would break plain thread
     * creation whenever the checkbox is left unchecked.
     *
     * @return array<string, mixed>
     */
    public static function optional(string $field = 'create_poll'): array
    {
        return [
            'poll_questions' => ["required_if:{$field},1", 'array', 'min:1', 'max:20'],
            'poll_questions.*.prompt' => ["required_if:{$field},1", 'string', 'max:500'],
            'poll_questions.*.is_multi_select' => ['nullable', 'boolean'],
            'poll_questions.*.options' => ["required_if:{$field},1", 'array', 'min:2', 'max:20'],
            'poll_questions.*.options.*.label' => ["required_if:{$field},1", 'string', 'max:255'],
        ];
    }
}
