<?php

namespace App\Http\Requests;

use App\Support\PollValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return PollValidationRules::required();
    }

    /**
     * Flags duplicate option labels within the same question (trimmed,
     * case-insensitive) — not a hard product requirement, but cheap and
     * prevents an obviously-broken poll from being created.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('poll_questions', []) as $questionKey => $question) {
                $labels = collect((array) ($question['options'] ?? []))
                    ->map(fn ($option) => mb_strtolower(trim((string) ($option['label'] ?? ''))))
                    ->filter(fn (string $label): bool => $label !== '');

                if ($labels->count() !== $labels->unique()->count()) {
                    $validator->errors()->add(
                        "poll_questions.{$questionKey}.options",
                        'Options within a question must not repeat.'
                    );
                }
            }
        });
    }
}
