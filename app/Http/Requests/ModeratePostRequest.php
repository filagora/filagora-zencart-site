<?php

namespace App\Http\Requests;

use App\Actions\BanUserAction;
use Filagora\Forums\Services\ForumAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModeratePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(ForumAccess::class)->canModerate($this->user());
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('new_thread_title'))) {
            $this->merge(['new_thread_title' => trim($this->input('new_thread_title'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:delete,restore,move,promote,approve,unapprove,reject,reject_permanently'],
            'destination_thread_id' => ['nullable', 'integer', 'required_if:action,move', 'exists:threads,id'],
            'destination_forum_id' => ['nullable', 'integer', 'required_if:action,promote', 'exists:forums,id'],
            'new_thread_title' => ['nullable', 'string', 'required_if:action,promote', 'min:3', 'max:255'],
            'reason' => ['nullable', 'string', 'required_if:action,delete', 'max:5000'],
            'return_to' => ['nullable', 'string', 'max:2048'],
            'ban' => ['sometimes', 'boolean'],
            'ban_reason' => ['exclude_unless:ban,1', 'nullable', 'string', 'max:4000'],
            'ban_duration' => ['exclude_unless:ban,1', 'nullable', Rule::in([...array_keys(BanUserAction::DURATIONS), 'permanent', 'custom'])],
            'ban_until' => ['exclude_unless:ban,1', 'nullable', 'required_if:ban_duration,custom', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required_if' => 'The reason field is required when action is :value.',
            'destination_thread_id.required_if' => 'The destination thread id field is required when action is :value.',
            'destination_forum_id.required_if' => 'The destination forum field is required when action is :value.',
            'new_thread_title.required_if' => 'The new thread title field is required when action is :value.',
            'ban_until.required_if' => 'Choose an end date for a custom-length ban.',
        ];
    }
}
